<?php
declare(strict_types=1);

namespace ClicShopping\AI\CoreAI\Orchestrator\SubReputation;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models\ReputationAlert;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models\ReputationScore;

/**
 * ReputationAlerter - Generates alerts for reputation issues
 * 
 * Monitors reputation thresholds and generates alerts for:
 * - Low reputation (< 0.6)
 * - Rapid changes (> 0.2 in 24h)
 * - Gaming detection
 * - Anomalies
 * 
 * Requirements: 8.1, 8.2, 8.3, 8.4
 */
class ReputationAlerter
{
    private $db;
    private string $prefix;
    private ReputationStore $store;
    
    // Alert thresholds (configurable)
    private float $lowReputationThreshold = 0.6;
    private float $rapidChangeThreshold = 0.2;
    private int $rapidChangeWindowHours = 24;
    
    public function __construct(?ReputationStore $store = null)
    {
        $this->db = Registry::get('Db');
        $this->prefix = CLICSHOPPING::getConfig('db_table_prefix', 'Database');
        $this->store = $store ?? new ReputationStore();
    }
    
    /**
     * Check reputation thresholds and generate alerts
     * 
     * Monitors all critics and generates alerts for:
     * - Reputation below threshold
     * - Rapid reputation changes
     * 
     * Requirements: 8.1, 8.2
     * 
     * @return array Array of generated alerts
     */
    public function checkThresholds(): array
    {
        $alerts = [];
        
        // Get all reputation scores
        $reputations = $this->store->getAllReputations();
        
        foreach ($reputations as $reputation) {
            // Check for low reputation
            if ($reputation->reputationScore < $this->lowReputationThreshold) {
                $alert = $this->generateLowReputationAlert($reputation);
                if ($alert) {
                    $alerts[] = $alert;
                }
            }
            
            // Check for rapid changes
            $rapidChangeAlert = $this->checkRapidChange($reputation);
            if ($rapidChangeAlert) {
                $alerts[] = $rapidChangeAlert;
            }
        }
        
        return $alerts;
    }
    
    /**
     * Generate alert for low reputation
     * 
     * Requirements: 8.1, 8.4
     * 
     * @param ReputationScore $reputation Reputation score
     * @return ReputationAlert|null Alert or null if already alerted recently
     */
    private function generateLowReputationAlert(ReputationScore $reputation): ?ReputationAlert
    {
        // Check if we already have a recent unacknowledged alert
        if ($this->hasRecentAlert($reputation->criticId, 'low_reputation', 24)) {
            return null; // Don't spam alerts
        }
        
        // Determine severity based on how low the reputation is
        $severity = $this->calculateLowReputationSeverity($reputation->reputationScore);
        
        $message = sprintf(
            "Critic '%s' has low reputation score: %.2f (threshold: %.2f). " .
            "Total evaluations: %d, Status: %s",
            $reputation->criticId,
            $reputation->reputationScore,
            $this->lowReputationThreshold,
            $reputation->totalEvaluations,
            $reputation->status
        );
        
        $context = [
            'reputation_score' => $reputation->reputationScore,
            'threshold' => $this->lowReputationThreshold,
            'consensus_alignment' => $reputation->consensusAlignment,
            'feedback_quality' => $reputation->feedbackQuality,
            'consistency_score' => $reputation->consistencyScore,
            'expertise_accuracy' => $reputation->expertiseAccuracy,
            'total_evaluations' => $reputation->totalEvaluations,
            'status' => $reputation->status
        ];
        
        $alert = ReputationAlert::create(
            $reputation->criticId,
            'low_reputation',
            $severity,
            $message,
            $context
        );
        
        // Save alert to database
        $this->saveAlert($alert);
        
        // Log alert
        $this->logAlert($alert);
        
        return $alert;
    }
    
    /**
     * Check for rapid reputation changes
     * 
     * Requirements: 8.2, 8.4
     * 
     * @param ReputationScore $reputation Reputation score
     * @return ReputationAlert|null Alert or null if no rapid change
     */
    private function checkRapidChange(ReputationScore $reputation): ?ReputationAlert
    {
        // Get reputation history for the time window
        $history = $this->store->getHistory($reputation->criticId, 1); // Last 24 hours
        
        if (empty($history)) {
            return null; // No history to compare
        }
        
        // Calculate total change in the window
        $oldestReputation = end($history)->oldReputation;
        $currentReputation = $reputation->reputationScore;
        $change = abs($currentReputation - $oldestReputation);
        
        if ($change < $this->rapidChangeThreshold) {
            return null; // Change is within acceptable range
        }
        
        // Check if we already have a recent unacknowledged alert
        if ($this->hasRecentAlert($reputation->criticId, 'rapid_change', 24)) {
            return null; // Don't spam alerts
        }
        
        // Determine severity based on magnitude of change
        $severity = $this->calculateRapidChangeSeverity($change);
        
        $direction = $currentReputation > $oldestReputation ? 'increased' : 'decreased';
        
        $message = sprintf(
            "Critic '%s' reputation %s rapidly by %.2f in %d hours " .
            "(from %.2f to %.2f, threshold: %.2f)",
            $reputation->criticId,
            $direction,
            $change,
            $this->rapidChangeWindowHours,
            $oldestReputation,
            $currentReputation,
            $this->rapidChangeThreshold
        );
        
        $context = [
            'old_reputation' => $oldestReputation,
            'new_reputation' => $currentReputation,
            'change' => $change,
            'direction' => $direction,
            'threshold' => $this->rapidChangeThreshold,
            'window_hours' => $this->rapidChangeWindowHours,
            'history_count' => count($history)
        ];
        
        $alert = ReputationAlert::create(
            $reputation->criticId,
            'rapid_change',
            $severity,
            $message,
            $context
        );
        
        // Save alert to database
        $this->saveAlert($alert);
        
        // Log alert
        $this->logAlert($alert);
        
        return $alert;
    }
    
    /**
     * Generate alert for gaming detection
     * 
     * Requirements: 8.3, 8.4
     * 
     * @param string $criticId Critic identifier
     * @param array $gamingDetection Gaming detection result from GamingDetector
     * @return ReputationAlert Alert
     */
    public function generateGamingAlert(string $criticId, array $gamingDetection): ReputationAlert
    {
        $severity = $this->calculateGamingSeverity($gamingDetection['confidence'] ?? 0.5);
        
        $message = sprintf(
            "Potential reputation gaming detected for critic '%s'. " .
            "Type: %s, Confidence: %.2f",
            $criticId,
            $gamingDetection['gaming_type'] ?? 'unknown',
            $gamingDetection['confidence'] ?? 0.0
        );
        
        $context = [
            'gaming_type' => $gamingDetection['gaming_type'] ?? 'unknown',
            'confidence' => $gamingDetection['confidence'] ?? 0.0,
            'evidence' => $gamingDetection['evidence'] ?? [],
            'detection_timestamp' => date('Y-m-d H:i:s')
        ];
        
        $alert = ReputationAlert::create(
            $criticId,
            'gaming_detected',
            $severity,
            $message,
            $context
        );
        
        // Save alert to database
        $this->saveAlert($alert);
        
        // Log alert
        $this->logAlert($alert);
        
        return $alert;
    }
    
    /**
     * Generate alert for anomaly detection
     * 
     * Requirements: 8.4
     * 
     * @param string $criticId Critic identifier
     * @param array $anomaly Anomaly details
     * @return ReputationAlert Alert
     */
    public function generateAnomalyAlert(string $criticId, array $anomaly): ReputationAlert
    {
        $severity = $anomaly['severity'] ?? 'medium';
        
        $message = sprintf(
            "Reputation anomaly detected for critic '%s': %s",
            $criticId,
            $anomaly['description'] ?? 'Unknown anomaly'
        );
        
        $context = [
            'anomaly_type' => $anomaly['type'] ?? 'unknown',
            'description' => $anomaly['description'] ?? '',
            'detection_timestamp' => date('Y-m-d H:i:s'),
            'details' => $anomaly['details'] ?? []
        ];
        
        $alert = ReputationAlert::create(
            $criticId,
            'anomaly',
            $severity,
            $message,
            $context
        );
        
        // Save alert to database
        $this->saveAlert($alert);
        
        // Log alert
        $this->logAlert($alert);
        
        return $alert;
    }
    
    /**
     * Save alert to database
     * 
     * Requirements: 8.4
     * 
     * @param ReputationAlert $alert Alert to save
     * @return bool True if successful
     */
    private function saveAlert(ReputationAlert $alert): bool
    {
        if (!$alert->isValid()) {
            error_log("Invalid alert: " . json_encode($alert->toArray()));
            return false;
        }
        
        $sql = "
            INSERT INTO {$this->prefix}rag_agent_reputation_alerts (
                critic_id,
                alert_type,
                severity,
                message,
                context,
                acknowledged,
                created_at
            ) VALUES (
                :critic_id,
                :alert_type,
                :severity,
                :message,
                :context,
                :acknowledged,
                :created_at
            )
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'critic_id' => $alert->criticId,
                'alert_type' => $alert->alertType,
                'severity' => $alert->severity,
                'message' => $alert->message,
                'context' => $alert->context ? json_encode($alert->context) : null,
                'acknowledged' => $alert->acknowledged ? 1 : 0,
                'created_at' => $alert->createdAt->format('Y-m-d H:i:s')
            ]);
            
            // Get the inserted alert ID
            $alert->alertId = (int)$this->db->lastInsertId();
            
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            error_log("Failed to save alert: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log alert to file
     * 
     * Requirements: 8.4
     * 
     * @param ReputationAlert $alert Alert to log
     * @return void
     */
    private function logAlert(ReputationAlert $alert): void
    {
        $logMessage = sprintf(
            "[%s] [%s] [%s] %s - Critic: %s",
            $alert->createdAt->format('Y-m-d H:i:s'),
            strtoupper($alert->severity),
            strtoupper($alert->alertType),
            $alert->message,
            $alert->criticId
        );
        
        if ($alert->context) {
            $logMessage .= " | Context: " . json_encode($alert->context);
        }
        
        error_log($logMessage);
    }
    
    /**
     * Check if critic has recent alert of given type
     * 
     * @param string $criticId Critic identifier
     * @param string $alertType Alert type
     * @param int $hours Hours to look back
     * @return bool True if recent alert exists
     */
    private function hasRecentAlert(string $criticId, string $alertType, int $hours): bool
    {
        $sql = "
            SELECT COUNT(*) as count
            FROM {$this->prefix}rag_agent_reputation_alerts
            WHERE critic_id = :critic_id
              AND alert_type = :alert_type
              AND acknowledged = 0
              AND created_at > DATE_SUB(NOW(), INTERVAL :hours HOUR)
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'critic_id' => $criticId,
                'alert_type' => $alertType,
                'hours' => $hours
            ]);
            
            $row = $stmt->fetch();
            return $row && $row['count'] > 0;
        } catch (\Exception $e) {
            error_log("Failed to check recent alerts: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Calculate severity for low reputation alert
     * 
     * @param float $reputation Reputation score
     * @return string Severity level
     */
    private function calculateLowReputationSeverity(float $reputation): string
    {
        if ($reputation < 0.5) {
            return 'critical'; // Below minimum
        } elseif ($reputation < 0.55) {
            return 'high'; // Very close to minimum
        } elseif ($reputation < 0.6) {
            return 'medium'; // Below threshold
        } else {
            return 'low'; // Just below threshold
        }
    }
    
    /**
     * Calculate severity for rapid change alert
     * 
     * @param float $change Magnitude of change
     * @return string Severity level
     */
    private function calculateRapidChangeSeverity(float $change): string
    {
        if ($change >= 0.4) {
            return 'critical'; // Extreme change
        } elseif ($change >= 0.3) {
            return 'high'; // Very large change
        } elseif ($change >= 0.2) {
            return 'medium'; // Large change
        } else {
            return 'low'; // Moderate change
        }
    }
    
    /**
     * Calculate severity for gaming detection alert
     * 
     * @param float $confidence Confidence level (0.0-1.0)
     * @return string Severity level
     */
    private function calculateGamingSeverity(float $confidence): string
    {
        if ($confidence >= 0.9) {
            return 'critical'; // Very high confidence
        } elseif ($confidence >= 0.75) {
            return 'high'; // High confidence
        } elseif ($confidence >= 0.6) {
            return 'medium'; // Moderate confidence
        } else {
            return 'low'; // Low confidence
        }
    }
    
    /**
     * Get all unacknowledged alerts
     * 
     * @return array<ReputationAlert> Array of alerts
     */
    public function getUnacknowledgedAlerts(): array
    {
        $sql = "
            SELECT 
                alert_id,
                critic_id,
                alert_type,
                severity,
                message,
                context,
                acknowledged,
                acknowledged_by,
                acknowledged_at,
                created_at
            FROM {$this->prefix}rag_agent_reputation_alerts
            WHERE acknowledged = 0
            ORDER BY severity DESC, created_at DESC
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            $alerts = [];
            while ($row = $stmt->fetch()) {
                $alerts[] = $this->mapRowToAlert($row);
            }
            
            return $alerts;
        } catch (\Exception $e) {
            error_log("Failed to get unacknowledged alerts: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get alerts for a specific critic
     * 
     * @param string $criticId Critic identifier
     * @param int $days Number of days to look back
     * @return array<ReputationAlert> Array of alerts
     */
    public function getAlertsForCritic(string $criticId, int $days = 30): array
    {
        $sql = "
            SELECT 
                alert_id,
                critic_id,
                alert_type,
                severity,
                message,
                context,
                acknowledged,
                acknowledged_by,
                acknowledged_at,
                created_at
            FROM {$this->prefix}rag_agent_reputation_alerts
            WHERE critic_id = :critic_id
              AND created_at > DATE_SUB(NOW(), INTERVAL :days DAY)
            ORDER BY created_at DESC
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'critic_id' => $criticId,
                'days' => $days
            ]);
            
            $alerts = [];
            while ($row = $stmt->fetch()) {
                $alerts[] = $this->mapRowToAlert($row);
            }
            
            return $alerts;
        } catch (\Exception $e) {
            error_log("Failed to get alerts for critic: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Acknowledge an alert
     * 
     * @param int $alertId Alert identifier
     * @param string $acknowledgedBy Administrator who acknowledged
     * @return bool True if successful
     */
    public function acknowledgeAlert(int $alertId, string $acknowledgedBy): bool
    {
        $sql = "
            UPDATE {$this->prefix}rag_agent_reputation_alerts
            SET acknowledged = 1,
                acknowledged_by = :acknowledged_by,
                acknowledged_at = NOW()
            WHERE alert_id = :alert_id
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'alert_id' => $alertId,
                'acknowledged_by' => $acknowledgedBy
            ]);
            
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            error_log("Failed to acknowledge alert: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Map database row to ReputationAlert object
     * 
     * @param array $row Database row
     * @return ReputationAlert Alert object
     */
    private function mapRowToAlert(array $row): ReputationAlert
    {
        $alert = new ReputationAlert();
        $alert->alertId = (int)$row['alert_id'];
        $alert->criticId = $row['critic_id'];
        $alert->alertType = $row['alert_type'];
        $alert->severity = $row['severity'];
        $alert->message = $row['message'];
        $alert->context = $row['context'] ? json_decode($row['context'], true) : null;
        $alert->acknowledged = (bool)$row['acknowledged'];
        $alert->acknowledgedBy = $row['acknowledged_by'];
        $alert->acknowledgedAt = $row['acknowledged_at'] ? new \DateTimeImmutable($row['acknowledged_at']) : null;
        $alert->createdAt = new \DateTimeImmutable($row['created_at']);
        
        return $alert;
    }
    
    /**
     * Set low reputation threshold
     * 
     * @param float $threshold Threshold value (0.0-1.0)
     * @return void
     */
    public function setLowReputationThreshold(float $threshold): void
    {
        $this->lowReputationThreshold = max(0.0, min(1.0, $threshold));
    }
    
    /**
     * Set rapid change threshold
     * 
     * @param float $threshold Threshold value (0.0-1.0)
     * @return void
     */
    public function setRapidChangeThreshold(float $threshold): void
    {
        $this->rapidChangeThreshold = max(0.0, min(1.0, $threshold));
    }
    
    /**
     * Set rapid change window
     * 
     * @param int $hours Window in hours
     * @return void
     */
    public function setRapidChangeWindow(int $hours): void
    {
        $this->rapidChangeWindowHours = max(1, $hours);
    }
}
