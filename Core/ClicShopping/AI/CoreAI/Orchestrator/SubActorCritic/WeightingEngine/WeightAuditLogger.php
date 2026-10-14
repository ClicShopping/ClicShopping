<?php
declare(strict_types=1);

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Models\WeightResult;

/**
 * WeightAuditLogger - Maintains complete audit trail of weight decisions
 * 
 * Responsibilities:
 * - Store weight calculations with full context
 * - Log LLM explanations and reasoning
 * - Track weight history per critic
 * - Support querying and analysis with multi-domain filtering
 */
class WeightAuditLogger
{
    private $db;
    private string $prefix;

    public function __construct()
    {
        $this->db = Registry::get('Db');
        $this->prefix = (string)CLICSHOPPING::getConfig('db_table_prefix');
    }
    
    /**
     * Log weight calculation with full context and multi-domain analysis
     * 
     * Stores weight calculation results including:
     * - Raw and normalized weights
     * - LLM explanations
     * - Factor analysis (JSON)
     * - Domain match analysis (JSON)
     * 
     * Requirements: 9.5, 13.1, 13.2
     * 
     * @param string $evaluationId Evaluation identifier
     * @param WeightResult $result Weight calculation result
     * @return bool True if successful
     */
    public function logWeightCalculation(string $evaluationId, WeightResult $result): bool
    {
        try {
            // Get data from WeightResult using getters
            $weights = $result->getWeights();
            $normalizedWeights = $result->getNormalizedWeights();
            $explanations = $result->getExplanations();
            $overallRationale = $result->getOverallRationale();
            $factorAnalysis = $result->getFactorAnalysis();
            $bounds = $result->getBounds();
            
            // Insert weight records for each critic
            foreach ($weights as $criticId => $rawWeight) {
                $normalizedWeight = $normalizedWeights[$criticId] ?? 0.0;
                $explanation = $explanations[$criticId] ?? '';
                
                // Prepare factor analysis JSON
                $factorAnalysisJson = json_encode([
                    'overall_rationale' => $overallRationale,
                    'dominant_factors' => $factorAnalysis,
                    'bounds' => $bounds
                ]);
                
                // Prepare data array for save()
                // Note: created_at has DEFAULT CURRENT_TIMESTAMP, so we don't include it
                $data = [
                    'evaluation_id' => $evaluationId,
                    'critic_id' => $criticId,
                    'raw_weight' => $rawWeight,
                    'normalized_weight' => $normalizedWeight,
                    'llm_explanation' => $explanation,
                    'factor_analysis' => $factorAnalysisJson
                ];
                
                // Use save() method - automatically adds table prefix
                $this->db->save('rag_agent_adaptive_weights', $data);
                
                // Also insert into weight history for trend tracking
                $this->logWeightHistory($criticId, $evaluationId, $normalizedWeight);
            }
            
            return true;
        } catch (\Exception $e) {
            // Log error
            error_log("WeightAuditLogger::logWeightCalculation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log weight to history table for trend analysis
     * 
     * @param string $criticId Critic identifier
     * @param string $evaluationId Evaluation identifier
     * @param float $weight Normalized weight
     * @return bool True if successful
     */
    private function logWeightHistory(string $criticId, string $evaluationId, float $weight): bool
    {
        try {
            // Prepare data array for save()
            $data = [
                'critic_id' => $criticId,
                'evaluation_id' => $evaluationId,
                'weight' => $weight,
                'timestamp' => 'now()'  // Special value handled by save()
            ];
            
            // Use save() method - automatically adds table prefix
            $rowCount = $this->db->save('rag_agent_critic_weight_history', $data);
            
            return $rowCount > 0;
        } catch (\Exception $e) {
            error_log("WeightAuditLogger::logWeightHistory failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get weight history for a critic with optional date range and domain filtering
     * 
     * Requirements: 13.1, 13.2, 13.4
     * 
     * @param string $criticId Critic identifier
     * @param int $days Number of days of history to retrieve (default: 90)
     * @param string|null $domainContext Optional domain filter (e.g., 'Ecommerce', 'Security')
     * @return array Array of weight history records
     */
    public function getWeightHistory(
        string $criticId,
        int $days = 90,
        ?string $domainContext = null
    ): array {
        try {
            $sql = "
                SELECT 
                    h.critic_id,
                    h.evaluation_id,
                    h.weight,
                    h.timestamp,
                    w.llm_explanation,
                    w.factor_analysis
                FROM {$this->prefix}rag_agent_critic_weight_history h
                LEFT JOIN {$this->prefix}rag_agent_adaptive_weights w
                    ON h.evaluation_id = w.evaluation_id 
                    AND h.critic_id = w.critic_id
                WHERE h.critic_id = :critic_id
                  AND h.timestamp > DATE_SUB(NOW(), INTERVAL :days DAY)
            ";
            
            // Add domain filtering if specified
            if ($domainContext !== null) {
                $sql .= " AND JSON_EXTRACT(w.factor_analysis, '$.domain_context') = :domain_context";
            }
            
            $sql .= " ORDER BY h.timestamp DESC";
            
            $stmt = $this->db->prepare($sql);
            $params = [
                'critic_id' => $criticId,
                'days' => $days
            ];
            
            if ($domainContext !== null) {
                $params['domain_context'] = $domainContext;
            }
            
            $stmt->execute($params);
            
            $history = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $history[] = [
                    'critic_id' => $row['critic_id'],
                    'evaluation_id' => $row['evaluation_id'],
                    'weight' => (float)$row['weight'],
                    'timestamp' => $row['timestamp'],
                    'llm_explanation' => $row['llm_explanation'] ?? '',
                    'factor_analysis' => $row['factor_analysis'] ? json_decode($row['factor_analysis'], true) : []
                ];
            }
            
            return $history;
        } catch (\Exception $e) {
            error_log("WeightAuditLogger::getWeightHistory failed: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get critic's weight history for specific domain evaluations
     * 
     * Retrieves weight history filtered by domain context to analyze
     * how a critic performs in specific domain evaluations.
     * 
     * Requirements: 13.1, 13.2
     * 
     * @param string $criticId Critic identifier
     * @param string $domain Domain to filter by (e.g., 'Ecommerce', 'Security', 'Analytics')
     * @param int $days Number of days of history to retrieve (default: 90)
     * @return array Array of weight history records for the specified domain
     */
    public function getWeightHistoryByDomain(
        string $criticId,
        string $domain,
        int $days = 90
    ): array {
        return $this->getWeightHistory($criticId, $days, $domain);
    }
    
    /**
     * Export weight audit data for analysis
     * 
     * Exports complete weight audit trail for an evaluation including
     * all critics, weights, explanations, and domain information.
     * 
     * Requirements: 13.4, 13.5
     * 
     * @param string $evaluationId Evaluation identifier
     * @param string $format Export format ('array', 'json', 'csv')
     * @return mixed Exported data in requested format
     */
    public function exportWeightAudit(string $evaluationId, string $format = 'array')
    {
        try {
            $sql = "
                SELECT 
                    evaluation_id,
                    critic_id,
                    raw_weight,
                    normalized_weight,
                    llm_explanation,
                    factor_analysis,
                    created_at
                FROM {$this->prefix}rag_agent_adaptive_weights
                WHERE evaluation_id = :evaluation_id
                ORDER BY normalized_weight DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['evaluation_id' => $evaluationId]);
            
            $data = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $record = [
                    'evaluation_id' => $row['evaluation_id'],
                    'critic_id' => $row['critic_id'],
                    'raw_weight' => (float)$row['raw_weight'],
                    'normalized_weight' => (float)$row['normalized_weight'],
                    'llm_explanation' => $row['llm_explanation'],
                    'factor_analysis' => json_decode($row['factor_analysis'], true),
                    'created_at' => $row['created_at']
                ];
                
                $data[] = $record;
            }
            
            // Format output based on requested format
            switch ($format) {
                case 'json':
                    return json_encode($data, JSON_PRETTY_PRINT);
                    
                case 'csv':
                    return $this->convertToCSV($data);
                    
                case 'array':
                default:
                    return $data;
            }
        } catch (\Exception $e) {
            error_log("WeightAuditLogger::exportWeightAudit failed: " . $e->getMessage());
            return $format === 'array' ? [] : '';
        }
    }
    
    /**
     * Convert audit data to CSV format
     * 
     * @param array $data Audit data array
     * @return string CSV formatted string
     */
    private function convertToCSV(array $data): string
    {
        if (empty($data)) {
            return '';
        }
        
        $csv = [];
        
        // Header row
        $csv[] = implode(',', [
            'evaluation_id',
            'critic_id',
            'raw_weight',
            'normalized_weight',
            'llm_explanation',
            'dominant_factors',
            'domain_context',
            'created_at'
        ]);
        
        // Data rows
        foreach ($data as $record) {
            $factorAnalysis = $record['factor_analysis'] ?? [];
            $dominantFactors = isset($factorAnalysis['dominant_factors']) 
                ? implode(';', $factorAnalysis['dominant_factors']) 
                : '';
            $domainContext = $factorAnalysis['domain_context'] ?? '';
            
            $csv[] = implode(',', [
                $this->escapeCsvValue($record['evaluation_id']),
                $this->escapeCsvValue($record['critic_id']),
                $record['raw_weight'],
                $record['normalized_weight'],
                $this->escapeCsvValue($record['llm_explanation']),
                $this->escapeCsvValue($dominantFactors),
                $this->escapeCsvValue($domainContext),
                $record['created_at']
            ]);
        }
        
        return implode("\n", $csv);
    }
    
    /**
     * Escape CSV value
     * 
     * @param string $value Value to escape
     * @return string Escaped value
     */
    private function escapeCsvValue(string $value): string
    {
        // Escape quotes and wrap in quotes if contains comma, quote, or newline
      if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
        return '"' . str_replace('"', '""', $value) . '"';
      }
        return $value;
    }
    
    /**
     * Get weight statistics for a critic
     * 
     * Calculates aggregate statistics for a critic's weight history.
     * 
     * @param string $criticId Critic identifier
     * @param int $days Number of days to analyze (default: 90)
     * @return array Statistics including average, min, max, trend
     */
    public function getWeightStatistics(string $criticId, int $days = 90): array
    {
        try {
            $sql = "
                SELECT 
                    AVG(weight) as avg_weight,
                    MIN(weight) as min_weight,
                    MAX(weight) as max_weight,
                    COUNT(*) as evaluation_count,
                    STDDEV(weight) as weight_stddev
                FROM {$this->prefix}rag_agent_critic_weight_history
                WHERE critic_id = :critic_id
                  AND timestamp > DATE_SUB(NOW(), INTERVAL :days DAY)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'critic_id' => $criticId,
                'days' => $days
            ]);
            
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$row || $row['evaluation_count'] == 0) {
                return [
                    'avg_weight' => 0.0,
                    'min_weight' => 0.0,
                    'max_weight' => 0.0,
                    'evaluation_count' => 0,
                    'weight_stddev' => 0.0,
                    'trend' => 'no_data'
                ];
            }
            
            // Calculate trend (simple linear regression on recent data)
            $trend = $this->calculateWeightTrend($criticId, $days);
            
            return [
                'avg_weight' => (float)$row['avg_weight'],
                'min_weight' => (float)$row['min_weight'],
                'max_weight' => (float)$row['max_weight'],
                'evaluation_count' => (int)$row['evaluation_count'],
                'weight_stddev' => (float)($row['weight_stddev'] ?? 0.0),
                'trend' => $trend
            ];
        } catch (\Exception $e) {
            error_log("WeightAuditLogger::getWeightStatistics failed: " . $e->getMessage());
            return [
                'avg_weight' => 0.0,
                'min_weight' => 0.0,
                'max_weight' => 0.0,
                'evaluation_count' => 0,
                'weight_stddev' => 0.0,
                'trend' => 'error'
            ];
        }
    }
    
    /**
     * Calculate weight trend for a critic
     * 
     * Uses simple linear regression to determine if weights are increasing,
     * decreasing, or stable over time.
     * 
     * @param string $criticId Critic identifier
     * @param int $days Number of days to analyze
     * @return string Trend direction ('increasing', 'decreasing', 'stable')
     */
    private function calculateWeightTrend(string $criticId, int $days): string
    {
        try {
            $sql = "
                SELECT 
                    weight,
                    UNIX_TIMESTAMP(timestamp) as ts
                FROM {$this->prefix}rag_agent_critic_weight_history
                WHERE critic_id = :critic_id
                  AND timestamp > DATE_SUB(NOW(), INTERVAL :days DAY)
                ORDER BY timestamp ASC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'critic_id' => $criticId,
                'days' => $days
            ]);
            
            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (count($data) < 2) {
                return 'insufficient_data';
            }
            
            // Simple linear regression
            $n = count($data);
            $sumX = 0;
            $sumY = 0;
            $sumXY = 0;
            $sumX2 = 0;
            
            foreach ($data as $i => $point) {
                $x = $i; // Use index as x-value
                $y = (float)$point['weight'];
                $sumX += $x;
                $sumY += $y;
                $sumXY += $x * $y;
                $sumX2 += $x * $x;
            }
            
            // Calculate slope
            $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
            
            // Determine trend based on slope
            if ($slope > 0.01) {
                return 'increasing';
            } elseif ($slope < -0.01) {
                return 'decreasing';
            } else {
                return 'stable';
            }
        } catch (\Exception $e) {
            error_log("WeightAuditLogger::calculateWeightTrend failed: " . $e->getMessage());
            return 'error';
        }
    }
    
    /**
     * Get all weight records for an evaluation
     * 
     * @param string $evaluationId Evaluation identifier
     * @return array Array of weight records
     */
    public function getEvaluationWeights(string $evaluationId): array
    {
        try {
            $sql = "
                SELECT 
                    evaluation_id,
                    critic_id,
                    raw_weight,
                    normalized_weight,
                    llm_explanation,
                    factor_analysis,
                    created_at
                FROM {$this->prefix}rag_agent_adaptive_weights
                WHERE evaluation_id = :evaluation_id
                ORDER BY normalized_weight DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['evaluation_id' => $evaluationId]);
            
            $weights = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $weights[] = [
                    'evaluation_id' => $row['evaluation_id'],
                    'critic_id' => $row['critic_id'],
                    'raw_weight' => (float)$row['raw_weight'],
                    'normalized_weight' => (float)$row['normalized_weight'],
                    'llm_explanation' => $row['llm_explanation'],
                    'factor_analysis' => json_decode($row['factor_analysis'], true),
                    'created_at' => $row['created_at']
                ];
            }
            
            return $weights;
        } catch (\Exception $e) {
            error_log("WeightAuditLogger::getEvaluationWeights failed: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Delete old weight audit records
     * 
     * Removes weight audit records older than the specified retention period.
     * Used for data cleanup and archival.
     * 
     * @param int $retentionDays Number of days to retain (default: 90)
     * @return int Number of records deleted
     */
    public function cleanupOldRecords(int $retentionDays = 90): int
    {
        try {
            $deletedCount = 0;
            
            // Delete from adaptive_weights table
            $sql = "
                DELETE FROM {$this->prefix}rag_agent_adaptive_weights
                WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['days' => $retentionDays]);
            $deletedCount += $stmt->rowCount();
            
            // Delete from weight_history table
            $sql = "
                DELETE FROM {$this->prefix}rag_agent_critic_weight_history
                WHERE timestamp < DATE_SUB(NOW(), INTERVAL :days DAY)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['days' => $retentionDays]);
            $deletedCount += $stmt->rowCount();
            
            return $deletedCount;
        } catch (\Exception $e) {
            error_log("WeightAuditLogger::cleanupOldRecords failed: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get weight history for anomaly detection
     * 
     * Retrieves weight history for all critics or specific critics over a time period.
     * Returns data formatted for LLM anomaly analysis.
     * 
     * Requirements: 20.1, 29.1
     * 
     * @param int $days Number of days of history to retrieve
     * @param array|null $criticIds Optional array of specific critic IDs to analyze
     * @return array Array of weight history records with evaluation context
     */
    public function getWeightHistoryForAnomalyDetection(int $days = 30, ?array $criticIds = null): array
    {
        try {
            $sql = "
                SELECT 
                    h.critic_id,
                    h.evaluation_id,
                    h.weight,
                    h.timestamp,
                    w.llm_explanation,
                    w.factor_analysis
                FROM {$this->prefix}rag_agent_critic_weight_history h
                LEFT JOIN {$this->prefix}rag_agent_adaptive_weights w
                    ON h.evaluation_id = w.evaluation_id 
                    AND h.critic_id = w.critic_id
                WHERE h.timestamp > DATE_SUB(NOW(), INTERVAL {$days} DAY)
            ";

            // Add critic ID filtering if specified. Use positional (?) placeholders only —
            // mixing named (:days) and positional in one statement triggers PDO HY093.
            // $days is an int (typed param) so it is interpolated safely above.
            $params = [];
            if ($criticIds !== null && !empty($criticIds)) {
                $placeholders = implode(',', array_fill(0, count($criticIds), '?'));
                $sql .= " AND h.critic_id IN ({$placeholders})";
                $params = array_values($criticIds);
            }

            $sql .= " ORDER BY h.timestamp DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $history = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $factorAnalysis = $row['factor_analysis'] ? json_decode($row['factor_analysis'], true) : [];
                
                $history[] = [
                    'critic_id' => $row['critic_id'],
                    'evaluation_id' => $row['evaluation_id'],
                    'weight' => (float)$row['weight'],
                    'timestamp' => $row['timestamp'],
                    'llm_explanation' => $row['llm_explanation'] ?? '',
                    'context' => $factorAnalysis['context'] ?? []
                ];
            }
            
            return $history;
        } catch (\Exception $e) {
            error_log("WeightAuditLogger::getWeightHistoryForAnomalyDetection failed: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Log detected anomaly to database
     * 
     * Stores anomaly information in rag_agent_weight_anomalies table.
     * 
     * Requirements: 29.1, 29.2, 29.3, 29.4
     * 
     * @param string $anomalyType Type of anomaly detected
     * @param string|null $criticId Critic ID (null if evaluation-wide anomaly)
     * @param string $severity Severity level (low, medium, high)
     * @param string $llmAnalysis LLM analysis text
     * @return int Anomaly ID
     */
    public function logAnomaly(
        string $anomalyType,
        ?string $criticId,
        string $severity,
        string $llmAnalysis
    ): int {
        try {
            // Prepare data array for save()
            $data = [
                'anomaly_type' => $anomalyType,
                'critic_id' => $criticId,
                'severity' => $severity,
                'llm_analysis' => $llmAnalysis,
                'detected_at' => 'now()'  // Special value handled by save()
            ];
            
            // Use save() method - automatically adds table prefix
            $this->db->save('rag_agent_weight_anomalies', $data);
            
            // Get the inserted ID
            return (int)$this->db->lastInsertId();
        } catch (\Exception $e) {
            error_log("WeightAuditLogger::logAnomaly failed: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get anomalies by severity
     * 
     * Retrieves anomalies filtered by severity level.
     * 
     * Requirements: 29.1, 29.2
     * 
     * @param string $severity Severity level (low, medium, high)
     * @param int $days Number of days to look back (default: 30)
     * @return array Array of anomaly records
     */
    public function getAnomaliesBySeverity(string $severity, int $days = 30): array
    {
        try {
            $sql = "
                SELECT 
                    id,
                    anomaly_type,
                    critic_id,
                    severity,
                    llm_analysis,
                    detected_at
                FROM {$this->prefix}rag_agent_weight_anomalies
                WHERE severity = :severity
                  AND detected_at > DATE_SUB(NOW(), INTERVAL :days DAY)
                ORDER BY detected_at DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'severity' => $severity,
                'days' => $days
            ]);
            
            $anomalies = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $anomalies[] = [
                    'id' => (int)$row['id'],
                    'anomaly_type' => $row['anomaly_type'],
                    'critic_id' => $row['critic_id'],
                    'severity' => $row['severity'],
                    'llm_analysis' => $row['llm_analysis'],
                    'detected_at' => $row['detected_at']
                ];
            }
            
            return $anomalies;
        } catch (\Exception $e) {
            error_log("WeightAuditLogger::getAnomaliesBySeverity failed: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get anomalies for a specific critic
     * 
     * Retrieves all anomalies associated with a specific critic.
     * 
     * Requirements: 29.1, 29.3
     * 
     * @param string $criticId Critic identifier
     * @param int $days Number of days to look back (default: 90)
     * @return array Array of anomaly records
     */
    public function getCriticAnomalies(string $criticId, int $days = 90): array
    {
        try {
            $sql = "
                SELECT 
                    id,
                    anomaly_type,
                    critic_id,
                    severity,
                    llm_analysis,
                    detected_at
                FROM {$this->prefix}rag_agent_weight_anomalies
                WHERE critic_id = :critic_id
                  AND detected_at > DATE_SUB(NOW(), INTERVAL :days DAY)
                ORDER BY detected_at DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'critic_id' => $criticId,
                'days' => $days
            ]);
            
            $anomalies = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $anomalies[] = [
                    'id' => (int)$row['id'],
                    'anomaly_type' => $row['anomaly_type'],
                    'critic_id' => $row['critic_id'],
                    'severity' => $row['severity'],
                    'llm_analysis' => $row['llm_analysis'],
                    'detected_at' => $row['detected_at']
                ];
            }
            
            return $anomalies;
        } catch (\Exception $e) {
            error_log("WeightAuditLogger::getCriticAnomalies failed: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all recent anomalies
     * 
     * Retrieves all anomalies within the specified time period.
     * 
     * Requirements: 29.1
     * 
     * @param int $days Number of days to look back (default: 30)
     * @return array Array of anomaly records
     */
    public function getRecentAnomalies(int $days = 30): array
    {
        try {
            $sql = "
                SELECT 
                    id,
                    anomaly_type,
                    critic_id,
                    severity,
                    llm_analysis,
                    detected_at
                FROM {$this->prefix}rag_agent_weight_anomalies
                WHERE detected_at > DATE_SUB(NOW(), INTERVAL :days DAY)
                ORDER BY detected_at DESC, severity DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['days' => $days]);
            
            $anomalies = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $anomalies[] = [
                    'id' => (int)$row['id'],
                    'anomaly_type' => $row['anomaly_type'],
                    'critic_id' => $row['critic_id'],
                    'severity' => $row['severity'],
                    'llm_analysis' => $row['llm_analysis'],
                    'detected_at' => $row['detected_at']
                ];
            }
            
            return $anomalies;
        } catch (\Exception $e) {
            error_log("WeightAuditLogger::getRecentAnomalies failed: " . $e->getMessage());
            return [];
        }
    }
}

