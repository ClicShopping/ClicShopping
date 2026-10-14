<?php
declare(strict_types=1);

/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine;

use ClicShopping\AI\Infrastructure\Monitoring\AlertManager;

/**
 * WeightAnomalyDetector - LLM-driven detection of suspicious weight-history patterns
 *
 * Analyses recent weight history with the LLM to surface gaming/collusion signals
 * (critics consistently maxed out, sudden unexplained shifts, single-critic dominance),
 * persists the findings through {@see WeightAuditLogger}, and raises an alert for every
 * high-severity anomaly via {@see AlertManager}. Extracted verbatim from
 * {@see LLMWeightingEngine} to keep the engine focused on the adaptive-weighting core path.
 *
 * LLM access goes through {@see WeightingLlmClient} (Gpt facade / LLPhant abstraction);
 * all persistence is delegated to {@see WeightAuditLogger}.
 *
 * Requirements: 20.1, 20.3, 29.1, 29.2, 29.3, 29.4
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine
 * @version 1.0.0
 * @since 2026-06-09
 */
class WeightAnomalyDetector
{
    private WeightAuditLogger $auditLogger;
    private LLMPromptBuilder $promptBuilder;
    private WeightingLlmClient $llmClient;
    private AlertManager $alertManager;
    private bool $debug;
    private string $errorLogPath;

    /**
     * Constructor
     *
     * @param WeightAuditLogger $auditLogger Provides weight history and persists anomalies
     * @param LLMPromptBuilder $promptBuilder Builds the anomaly-detection prompt
     * @param WeightingLlmClient $llmClient Shared LLM transport (retry + JSON repair)
     * @param AlertManager $alertManager Raises alerts for high-severity anomalies
     * @param bool $debug Gates verbose error_log
     * @param string $errorLogPath Destination file for debug log lines
     */
    public function __construct(
        WeightAuditLogger $auditLogger,
        LLMPromptBuilder $promptBuilder,
        WeightingLlmClient $llmClient,
        AlertManager $alertManager,
        bool $debug,
        string $errorLogPath
    ) {
        $this->auditLogger = $auditLogger;
        $this->promptBuilder = $promptBuilder;
        $this->llmClient = $llmClient;
        $this->alertManager = $alertManager;
        $this->debug = $debug;
        $this->errorLogPath = $errorLogPath;
    }

    /**
     * Detect anomalies in weight history using LLM analysis
     *
     * Uses LLM to analyze weight history and identify suspicious patterns:
     * - Critics with unusually high weights across multiple evaluations
     * - Critics consistently receiving maximum weights
     * - Unusual weight distributions (e.g., one critic dominates)
     * - Sudden weight changes without corresponding reputation changes
     * - Patterns suggesting collusion or gaming
     *
     * Detected anomalies are stored in rag_agent_weight_anomalies table.
     * High-severity anomalies trigger alerts via the existing alert system.
     *
     * Requirements: 20.1, 20.3, 29.1, 29.2, 29.3, 29.4
     *
     * @param int $days Number of days of history to analyze (default: 30)
     * @param array|null $criticIds Optional specific critics to analyze (null = all)
     * @return array Anomaly detection result with detected anomalies and analysis
     * @throws \RuntimeException If anomaly detection fails
     */
    public function detectAnomalies(int $days = 30, ?array $criticIds = null): array
    {
        $startTime = microtime(true);
        $analysisId = uniqid('anomaly_', true);

        try {
            // Step 1: Gather weight history from audit logger
            $weightHistory = $this->auditLogger->getWeightHistoryForAnomalyDetection($days, $criticIds);

            if (empty($weightHistory)) {
                return [
                    'analysis_id' => $analysisId,
                    'anomalies' => [],
                    'overall_assessment' => 'No weight history available for analysis',
                    'period_days' => $days,
                    'critics_analyzed' => 0,
                    'evaluations_analyzed' => 0,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }

            // Step 2: Build LLM prompt for anomaly detection
            $prompt = $this->promptBuilder->buildAnomalyDetectionPrompt($weightHistory, $days);

            // Step 3: Call LLM service with retry logic
            $llmResponse = $this->llmClient->callLLMWithRetry($prompt);

            // Step 4: Parse LLM response
            $parsedResponse = $this->parseAnomalyDetectionResponse($llmResponse);

            // Step 5: Store detected anomalies in database
            $storedAnomalies = $this->storeAnomalies($parsedResponse['anomalies']);

            // Step 6: Generate alerts for high-severity anomalies
            $this->generateAnomalyAlerts($storedAnomalies);

            // Step 7: Build result
            $result = [
                'analysis_id' => $analysisId,
                'anomalies' => $storedAnomalies,
                'overall_assessment' => $parsedResponse['overall_assessment'],
                'period_days' => $days,
                'critics_analyzed' => $this->countUniqueCritics($weightHistory),
                'evaluations_analyzed' => $this->countUniqueEvaluations($weightHistory),
                'high_severity_count' => $this->countBySeverity($storedAnomalies, 'high'),
                'medium_severity_count' => $this->countBySeverity($storedAnomalies, 'medium'),
                'low_severity_count' => $this->countBySeverity($storedAnomalies, 'low'),
                'timestamp' => date('Y-m-d H:i:s')
            ];

            $duration = microtime(true) - $startTime;
            $this->logAnomalyDetection($analysisId, $result, $duration);

            return $result;

        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            $this->logError($analysisId, $e, ['days' => $days, 'critic_ids' => $criticIds], $duration);
            throw $e;
        }
    }

    /**
     * Parse LLM anomaly detection response
     *
     * Expected JSON format:
     * {
     *   "anomalies": [
     *     {
     *       "type": "anomaly_type",
     *       "critic_id": "critic_id",
     *       "severity": "low|medium|high",
     *       "description": "what was detected",
     *       "evidence": ["supporting evidence"],
     *       "recommendation": "suggested action"
     *     }
     *   ],
     *   "overall_assessment": "summary of findings"
     * }
     *
     * Requirements: 20.1, 20.3
     *
     * @param string $response LLM response (JSON)
     * @return array Parsed anomaly detection response
     * @throws \RuntimeException If response is invalid
     */
    private function parseAnomalyDetectionResponse(string $response): array
    {
        // Try to extract JSON from response
        $jsonStart = strpos($response, '{');
        $jsonEnd = strrpos($response, '}');

        if ($jsonStart === false || $jsonEnd === false) {
            throw new \RuntimeException('No JSON object found in LLM anomaly detection response');
        }

        $jsonString = substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);
        $data = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON in LLM anomaly detection response: ' . json_last_error_msg());
        }

        // Validate required fields
        if (!isset($data['anomalies']) || !is_array($data['anomalies'])) {
            throw new \RuntimeException('LLM response missing "anomalies" field');
        }

        // Validate each anomaly has required fields
        foreach ($data['anomalies'] as $idx => $anomaly) {
            if (!isset($anomaly['type'])) {
                throw new \RuntimeException("Anomaly {$idx} missing 'type' field");
            }
            if (!isset($anomaly['severity'])) {
                throw new \RuntimeException("Anomaly {$idx} missing 'severity' field");
            }
            if (!isset($anomaly['description'])) {
                throw new \RuntimeException("Anomaly {$idx} missing 'description' field");
            }

            // Validate severity is valid
            $validSeverities = ['low', 'medium', 'high'];
            if (!in_array($anomaly['severity'], $validSeverities, true)) {
                throw new \RuntimeException(
                    "Anomaly {$idx} has invalid severity: {$anomaly['severity']}. Must be one of: " .
                    implode(', ', $validSeverities)
                );
            }
        }

        return [
            'anomalies' => $data['anomalies'],
            'overall_assessment' => $data['overall_assessment'] ?? 'No overall assessment provided'
        ];
    }

    /**
     * Store detected anomalies in database
     *
     * Inserts anomalies into rag_agent_weight_anomalies table.
     * Returns array of stored anomalies with database IDs.
     *
     * Requirements: 29.1, 29.2, 29.3, 29.4
     *
     * @param array $anomalies Array of anomalies from LLM
     * @return array Stored anomalies with database IDs
     */
    private function storeAnomalies(array $anomalies): array
    {
        $stored = [];

        foreach ($anomalies as $anomaly) {
            try {
                // Build LLM analysis text
                $llmAnalysis = $this->buildAnomalyAnalysisText($anomaly);

                // Insert into database
                $anomalyId = $this->auditLogger->logAnomaly(
                    $anomaly['type'],
                    $anomaly['critic_id'] ?? null,
                    $anomaly['severity'],
                    $llmAnalysis
                );

                // Add to stored array with ID
                $stored[] = array_merge($anomaly, ['id' => $anomalyId]);

            } catch (\Exception $e) {
                error_log("[ERROR] Failed to store anomaly: " . $e->getMessage());
                // Continue with other anomalies
            }
        }

        return $stored;
    }

    /**
     * Build LLM analysis text for anomaly
     *
     * Combines description, evidence, and recommendation into formatted text.
     *
     * @param array $anomaly Anomaly data
     * @return string Formatted LLM analysis text
     */
    private function buildAnomalyAnalysisText(array $anomaly): string
    {
        $parts = [];

        $parts[] = "Description: " . $anomaly['description'];

        if (isset($anomaly['evidence']) && is_array($anomaly['evidence']) && !empty($anomaly['evidence'])) {
            $parts[] = "Evidence:";
            foreach ($anomaly['evidence'] as $evidence) {
                $parts[] = "  - " . $evidence;
            }
        }

        if (isset($anomaly['recommendation'])) {
            $parts[] = "Recommendation: " . $anomaly['recommendation'];
        }

        return implode("\n", $parts);
    }

    /**
     * Generate alerts for high-severity anomalies
     *
     * Integrates with existing AlertManager to trigger alerts for anomalies
     * with 'high' severity. Alerts include full anomaly details for investigation.
     *
     * Requirements: 29.1, 29.2
     *
     * @param array $anomalies Stored anomalies with IDs
     * @return void
     */
    private function generateAnomalyAlerts(array $anomalies): void
    {
        foreach ($anomalies as $anomaly) {
            if ($anomaly['severity'] === 'high') {
                try {
                    $alertData = [
                        'type' => 'adaptive_weighting_anomaly',
                        'severity' => 'high',
                        'message' => "High-severity weight anomaly detected: {$anomaly['type']}",
                        'details' => [
                            'anomaly_id' => $anomaly['id'],
                            'anomaly_type' => $anomaly['type'],
                            'critic_id' => $anomaly['critic_id'] ?? 'N/A',
                            'description' => $anomaly['description'],
                            'evidence' => $anomaly['evidence'] ?? [],
                            'recommendation' => $anomaly['recommendation'] ?? 'No recommendation provided',
                            'detected_at' => date('Y-m-d H:i:s')
                        ],
                        'current_value' => $anomaly['type'],
                        'threshold' => 'high_severity'
                    ];

                    $this->alertManager->triggerAlert('adaptive_weighting_anomaly', $alertData);

                    // Log alert generation
                    $this->logAnomalyAlert($anomaly);

                } catch (\Exception $e) {
                    error_log("[ERROR] Failed to generate alert for anomaly {$anomaly['id']}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Log anomaly alert generation
     *
     * @param array $anomaly Anomaly data
     * @return void
     */
    private function logAnomalyAlert(array $anomaly): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $message = sprintf(
            "[%s] [WARNING] ANOMALY_ALERT - ID: %d | Type: %s | Critic: %s | Severity: %s\n" .
            "Description: %s\n",
            $timestamp,
            $anomaly['id'],
            $anomaly['type'],
            $anomaly['critic_id'] ?? 'N/A',
            $anomaly['severity'],
            $anomaly['description']
        );

        $this->debugLog($message);
    }

    /**
     * Count unique critics in weight history
     *
     * @param array $weightHistory Weight history data
     * @return int Number of unique critics
     */
    private function countUniqueCritics(array $weightHistory): int
    {
        $criticIds = [];

        foreach ($weightHistory as $entry) {
            if (isset($entry['critic_id'])) {
                $criticIds[$entry['critic_id']] = true;
            }
        }

        return count($criticIds);
    }

    /**
     * Count unique evaluations in weight history
     *
     * @param array $weightHistory Weight history data
     * @return int Number of unique evaluations
     */
    private function countUniqueEvaluations(array $weightHistory): int
    {
        $evaluationIds = [];

        foreach ($weightHistory as $entry) {
            if (isset($entry['evaluation_id'])) {
                $evaluationIds[$entry['evaluation_id']] = true;
            }
        }

        return count($evaluationIds);
    }

    /**
     * Count anomalies by severity
     *
     * @param array $anomalies Array of anomalies
     * @param string $severity Severity to count
     * @return int Count of anomalies with specified severity
     */
    private function countBySeverity(array $anomalies, string $severity): int
    {
        $count = 0;

        foreach ($anomalies as $anomaly) {
            if (isset($anomaly['severity']) && $anomaly['severity'] === $severity) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Log anomaly detection analysis
     *
     * @param string $analysisId Analysis identifier
     * @param array $result Anomaly detection result
     * @param float $duration Duration in seconds
     * @return void
     */
    private function logAnomalyDetection(string $analysisId, array $result, float $duration): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $anomalyCount = count($result['anomalies']);

        $message = sprintf(
            "[%s] [INFO] ANOMALY_DETECTION - Analysis: %s | Duration: %.3fs\n" .
            "Period: %d days | Critics: %d | Evaluations: %d\n" .
            "Anomalies Found: %d (High: %d, Medium: %d, Low: %d)\n" .
            "Assessment: %s\n" .
            str_repeat('-', 80) . "\n",
            $timestamp,
            $analysisId,
            $duration,
            $result['period_days'],
            $result['critics_analyzed'],
            $result['evaluations_analyzed'],
            $anomalyCount,
            $result['high_severity_count'],
            $result['medium_severity_count'],
            $result['low_severity_count'],
            $result['overall_assessment']
        );

        $this->debugLog($message);
    }

    /**
     * Log an anomaly-detection error to the weighting error log.
     *
     * @param string $analysisId Analysis identifier
     * @param \Exception $exception Exception that occurred
     * @param array $context Analysis context (days, critic_ids)
     * @param float $duration Duration in seconds
     * @return void
     */
    private function logError(string $analysisId, \Exception $exception, array $context, float $duration): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $errorMessage = sprintf(
            "[%s] [ERROR] Analysis: %s | Duration: %.3fs\n" .
            "Exception: %s\n" .
            "Message: %s\n" .
            "File: %s:%d\n" .
            "Context: %s\n" .
            "Stack Trace:\n%s\n" .
            str_repeat('-', 80) . "\n",
            $timestamp,
            $analysisId,
            $duration,
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            json_encode($context, JSON_PRETTY_PRINT),
            $exception->getTraceAsString()
        );

        // Write to dedicated error log file
        $this->debugLog($errorMessage);

        // Also log to standard error log for visibility (debug-gated)
        if ($this->debug) {
            error_log("[ERROR] WeightAnomalyDetector error for analysis {$analysisId}: " . $exception->getMessage());
        }
    }

    /**
     * Write a debug line to the error log when debug mode is enabled.
     *
     * @param string $message Pre-formatted log line
     * @return void
     */
    private function debugLog(string $message): void
    {
        if (!$this->debug) {
            return;
        }

        error_log($message, 3, $this->errorLogPath);
    }
}
