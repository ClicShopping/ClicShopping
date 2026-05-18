<?php
declare(strict_types=1);

/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine;

use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Models\WeightResult;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\CriticDataCollector;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\LLMPromptBuilder;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\WeightNormalizer;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\WeightAuditLogger;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\AI\Infrastructure\Monitoring\AlertManager;
use ClicShopping\AI\Infrastructure\Cache\Cache;

/**
 * LLMWeightingEngine - Core component for Pure LLM-based adaptive weight calculation
 * 
 * This engine uses LLM intelligence to analyze critic profiles, evaluation context,
 * and historical data to determine optimal adaptive weights. It follows a Pure LLM
 * approach where all decisions are made by the LLM without fixed formulas.
 * 
 * Multi-Domain Support:
 * - Analyzes domain match quality between critic expertise and evaluation requirements
 * - Considers expertise depth (expert > competent > novice) in matched domains
 * - Evaluates domain breadth (coverage across multiple relevant domains)
 * - Balances domain specialists vs generalists based on context
 * 
 * Process Flow:
 * 1. Gather critic data (reputation, domain expertise, confidence, recency)
 * 2. Build structured LLM prompt with evaluation context and domain requirements
 * 3. Call LLM service to analyze and determine weights with domain analysis
 * 4. Parse LLM response to extract weights, explanations, and domain_analysis
 * 5. Normalize weights to ensure sum = 1.0
 * 6. Store complete audit trail with domain match analysis
 * 
 * Requirements: 1.1, 1.2, 1.3, 1.4, 10.1, 10.2, 10.3, 10.4
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine
 * @version 1.0.0
 * @since 2026-02-06
 */
class LLMWeightingEngine
{
    private CriticDataCollector $criticDataCollector;
    private LLMPromptBuilder $promptBuilder;
    private WeightNormalizer $normalizer;
    private WeightAuditLogger $auditLogger;
    private AlertManager $alertManager;
    private Cache $cache;
    private ?MigrationManager $migrationManager = null;
    private string $errorLogPath;

    // Configuration
    private int $maxRetries = 2;
    private int $timeoutSeconds = 30;
    private bool $fallbackEnabled = true;
    private float $fallbackAlertThreshold = 0.05; // 5%
    
    // Fallback tracking
    private const CACHE_KEY_FALLBACK_COUNT = 'adaptive_weighting_fallback_count';
    private const CACHE_KEY_TOTAL_COUNT = 'adaptive_weighting_total_count';
    private const CACHE_TTL = 86400; // 24 hours
    
    /**
     * Constructor
     * 
     * @param CriticDataCollector $criticDataCollector Collects critic data
     * @param LLMPromptBuilder $promptBuilder Builds LLM prompts
     * @param WeightNormalizer $normalizer Normalizes weights
     * @param WeightAuditLogger $auditLogger Logs weight calculations
     * @param array $config Optional configuration overrides
     */
    public function __construct(
        CriticDataCollector $criticDataCollector,
        LLMPromptBuilder $promptBuilder,
        WeightNormalizer $normalizer,
        WeightAuditLogger $auditLogger,
        array $config = []
    ) {
        $this->criticDataCollector = $criticDataCollector;
        $this->promptBuilder = $promptBuilder;
        $this->normalizer = $normalizer;
        $this->auditLogger = $auditLogger;
        $this->alertManager = new AlertManager();
        $this->cache = new Cache(true);

        // Set error log path
        $this->errorLogPath = defined('CLICSHOPPING_BASE_DIR') 
            ? CLICSHOPPING_BASE_DIR . 'Work/Log/adaptive_weighting_errors.log'
            : __DIR__ . '/../../../../../../Work/Log/adaptive_weighting_errors.log';
        
        // Apply configuration overrides
        if (isset($config['max_retries'])) {
            $this->maxRetries = $config['max_retries'];
        }
        if (isset($config['timeout_seconds'])) {
            $this->timeoutSeconds = $config['timeout_seconds'];
        }
        if (isset($config['fallback_enabled'])) {
            $this->fallbackEnabled = $config['fallback_enabled'];
        }
        if (isset($config['fallback_alert_threshold'])) {
            $this->fallbackAlertThreshold = $config['fallback_alert_threshold'];
        }
    }
    
    /**
     * Calculate adaptive weights using Pure LLM analysis with multi-domain support
     * 
     * Main method that orchestrates the complete weight calculation process:
     * - Step 1: Gather all critic data with multi-domain expertise
     * - Step 2: Build structured prompt with domain matching requirements
     * - Step 3: Call LLM service to analyze and determine weights
     * - Step 4: Parse LLM response to extract weights, explanations, and domain_analysis
     * - Step 5: Normalize weights to ensure sum = 1.0
     * - Step 6: Store audit trail with domain_match_analysis
     * 
     * The LLM analyzes:
     * - Domain Match Quality: How well critic's domains align with required domains
     * - Expertise Depth: Level of expertise in matched domains (expert > competent > novice)
     * - Domain Breadth: Coverage across multiple relevant domains
     * - Reputation, confidence, recency, trends (as before)
     * 
     * NO mathematical formulas - pure LLM analysis of multi-domain expertise.
     * 
     * Includes comprehensive error handling with retry logic and fallback mechanisms.
     * 
     * Requirements: 1.1, 1.2, 1.3, 1.4, 10.1, 10.2, 10.3, 10.4, 19.1, 19.2, 19.3, 19.4, 19.5
     * 
     * @param array $critics Array of critic agents
     * @param array $context Evaluation context with required_domains
     * @return WeightResult Weight calculation result with domain analysis
     */
    public function calculateAdaptiveWeights(array $critics, array $context): WeightResult
    {
        $evaluationId = $context['evaluation_id'] ?? uniqid('eval_', true);
        $startTime = microtime(true);
        
        // Track total count
        $this->incrementTotalCount();
        
        try {
            // Step 1: Gather all critic data with multi-domain expertise
            $criticData = $this->criticDataCollector->collectCriticData($critics);
            
            if (empty($criticData)) {
                throw new \RuntimeException('No critic data collected');
            }
            
            // Step 2: Build structured prompt with domain matching requirements
            $prompt = $this->promptBuilder->buildWeightAnalysisPrompt($criticData, $context);
            
            // Step 3: Call LLM service with retry logic
            $llmResponse = $this->callLLMWithRetry($prompt);
            
            // Step 4: Parse LLM response to extract weights, explanations, and domain_analysis
            $parsedResponse = $this->parseLLMResponse($llmResponse, array_keys($criticData));
            
            // Step 5: Normalize weights
            $normalizedWeights = $this->normalizer->normalize($parsedResponse['weights']);
            
            // Create WeightResult
            $result = new WeightResult(
                $evaluationId,
                $parsedResponse['weights'],
                $normalizedWeights,
                $parsedResponse['explanations'],
                $parsedResponse['overall_rationale'],
                $parsedResponse['factor_analysis'],
                $parsedResponse['bounds'] ?? null,
                false, // not fallback
                null
            );
            
            // Step 6: Store audit trail with domain_match_analysis
            $this->auditLogger->logWeightCalculation($evaluationId, $result);
            
            $duration = microtime(true) - $startTime;
            $this->logSuccess($evaluationId, $duration);
            
            return $result;
            
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            
            // Log error with full context
            $this->logError($evaluationId, $e, $context, $duration);
            
            // Fall back to static weighting if enabled
            if ($this->fallbackEnabled) {
                $this->incrementFallbackCount();
                $this->checkFallbackRate();
                return $this->fallbackToStaticWeighting($evaluationId, $critics, $e->getMessage());
            }
            
            throw $e;
        }
    }
    
    /**
     * Call LLM service with retry logic and exponential backoff
     * 
     * Attempts to call the LLM service with exponential backoff retry.
     * Retries up to maxRetries times on failure with delays: 1s, 2s.
     * 
     * Requirements: 10.3, 19.1, 19.2
     * 
     * @param string $prompt Structured prompt for LLM
     * @return string LLM response (JSON)
     * @throws \RuntimeException If all retries fail
     */
    private function callLLMWithRetry(string $prompt): string
    {
        $attempt = 0;
        $lastException = null;
        
        while ($attempt <= $this->maxRetries) {
            try {
                $response = $this->callLLM($prompt);

                if ($attempt > 0) {
                    $this->logRetrySuccess($attempt);
                }

                return $response;

            } catch (\Exception $e) {
                $lastException = $e;
                $attempt++;
                
                // Log retry attempt
                $this->logRetryAttempt($attempt, $this->maxRetries, $e->getMessage());
                
                if ($attempt <= $this->maxRetries) {
                    // Exponential backoff: 1s, 2s
                    $waitTime = pow(2, $attempt - 1);
                    sleep($waitTime);
                }
            }
        }
        
        // All retries failed
        throw new \RuntimeException(
            "LLM call failed after {$this->maxRetries} retries: " . $lastException->getMessage(),
            0,
            $lastException
        );
    }
    
    /**
     * Call LLM via Gpt facade
     *
     * @param string $prompt Prompt to send
     * @return string LLM response
     * @throws \RuntimeException If LLM call fails
     */
    private function callLLM(string $prompt): string
    {
        $fullPrompt = $prompt . "\n\nIMPORTANT: You MUST respond with valid JSON only. Do not include any text before or after the JSON object.";

        $chat = Gpt::getChatForModel();
        $response = $chat->generateText($fullPrompt);

        if (empty($response)) {
            throw new \RuntimeException('Empty response from LLM');
        }

        return $response;
    }
    
    /**
     * Parse LLM JSON response
     * 
     * Extracts weights, explanations, rationale, and domain_analysis from LLM response.
     * Validates that all expected critics have weights.
     * 
     * Expected JSON format:
     * {
     *   "weights": {"critic_id": weight, ...},
     *   "explanations": {"critic_id": "explanation", ...},
     *   "overall_rationale": "reasoning",
     *   "dominant_factors": ["factor1", "factor2"],
     *   "domain_analysis": {"critic_id": {"match_quality": "high", "expertise_depth": "expert", ...}, ...},
     *   "suggested_bounds": {"min": 0.1, "max": 0.5}
     * }
     * 
     * Requirements: 1.1, 10.4
     * 
     * @param string $response LLM response (JSON)
     * @param array $expectedCriticIds List of expected critic IDs
     * @return array Parsed response with weights, explanations, rationale, factor_analysis
     * @throws \RuntimeException If response is invalid or missing data
     */
    private function parseLLMResponse(string $response, array $expectedCriticIds): array
    {
        // Try to extract JSON from response (LLM might include extra text)
        $jsonStart = strpos($response, '{');
        $jsonEnd = strrpos($response, '}');
        
        if ($jsonStart === false || $jsonEnd === false) {
            throw new \RuntimeException('No JSON object found in LLM response');
        }
        
        $jsonString = substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);
        $data = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON in LLM response: ' . json_last_error_msg());
        }
        
        // Validate required fields
        if (!isset($data['weights']) || !is_array($data['weights'])) {
            throw new \RuntimeException('LLM response missing "weights" field');
        }
        
        if (!isset($data['explanations']) || !is_array($data['explanations'])) {
            throw new \RuntimeException('LLM response missing "explanations" field');
        }
        
        // Validate all expected critics have weights
        foreach ($expectedCriticIds as $criticId) {
            if (!isset($data['weights'][$criticId])) {
                throw new \RuntimeException("LLM response missing weight for critic: {$criticId}");
            }
        }
        
        // Extract and validate weights are numeric
        $weights = [];
        foreach ($data['weights'] as $criticId => $weight) {
            if (!is_numeric($weight)) {
                throw new \RuntimeException("Invalid weight for critic {$criticId}: not numeric");
            }
            $weights[$criticId] = (float)$weight;
        }
        
        // Extract explanations
        $explanations = [];
        foreach ($data['explanations'] as $criticId => $explanation) {
            $explanations[$criticId] = (string)$explanation;
        }
        
        // Extract overall rationale
        $overallRationale = $data['overall_rationale'] ?? 'No rationale provided';
        
        // Extract factor analysis
        $factorAnalysis = [
            'dominant_factors' => $data['dominant_factors'] ?? [],
            'domain_analysis' => $data['domain_analysis'] ?? []
        ];
        
        // Extract bounds if provided
        $bounds = null;
        if (isset($data['suggested_bounds']) && is_array($data['suggested_bounds'])) {
            $bounds = $data['suggested_bounds'];
        }
        
        return [
            'weights' => $weights,
            'explanations' => $explanations,
            'overall_rationale' => $overallRationale,
            'factor_analysis' => $factorAnalysis,
            'bounds' => $bounds
        ];
    }
    
    /**
     * Fall back to static reputation-based weighting
     * 
     * When LLM analysis fails, falls back to simple reputation-based weighting.
     * Weights are proportional to reputation scores: weight = reputation / sum(reputations)
     * 
     * Requirements: 10.4, 19.3
     * 
     * @param string $evaluationId Evaluation identifier
     * @param array $critics Array of critic agents
     * @param string $reason Reason for fallback
     * @return WeightResult Fallback weight result
     */
    private function fallbackToStaticWeighting(string $evaluationId, array $critics, string $reason): WeightResult
    {
        $this->logFallback($evaluationId, $reason);
        
        // Collect reputation scores
        $criticData = $this->criticDataCollector->collectCriticData($critics);
        
        $weights = [];
        $explanations = [];
        
        foreach ($criticData as $criticId => $data) {
            $reputation = $data['reputation']['score'] ?? 0.75;
            $weights[$criticId] = $reputation;
            $explanations[$criticId] = "Fallback weight based on reputation score: {$reputation}";
        }
        
        // Normalize weights
        $normalizedWeights = $this->normalizer->normalize($weights);
        
        // Create fallback result
        $result = new WeightResult(
            $evaluationId,
            $weights,
            $normalizedWeights,
            $explanations,
            "Fallback to static reputation-based weighting due to: {$reason}",
            ['dominant_factors' => ['reputation'], 'domain_analysis' => []],
            null,
            true, // is fallback
            $reason
        );
        
        // Log fallback
        $this->auditLogger->logWeightCalculation($evaluationId, $result);
        
        return $result;
    }
    
    /**
     * Get configuration
     * 
     * Returns current configuration for debugging/monitoring.
     * 
     * @return array Configuration array
     */
    public function getConfig(): array
    {
        return [
            'max_retries' => $this->maxRetries,
            'timeout_seconds' => $this->timeoutSeconds,
            'fallback_enabled' => $this->fallbackEnabled,
            'fallback_alert_threshold' => $this->fallbackAlertThreshold
        ];
    }
    
    /**
     * Enable/disable fallback
     * 
     * @param bool $enabled Whether fallback is enabled
     * @return void
     */
    public function setFallbackEnabled(bool $enabled): void
    {
        $this->fallbackEnabled = $enabled;
    }
    
    /**
     * Log error with full context to dedicated error log file
     * 
     * Logs comprehensive error information including:
     * - Evaluation ID and timestamp
     * - Exception message and stack trace
     * - Evaluation context
     * - Duration before failure
     * 
     * Requirements: 19.4
     * 
     * @param string $evaluationId Evaluation identifier
     * @param \Exception $exception Exception that occurred
     * @param array $context Evaluation context
     * @param float $duration Duration in seconds
     * @return void
     */
    private function logError(string $evaluationId, \Exception $exception, array $context, float $duration): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $errorMessage = sprintf(
            "[%s] ERROR - Evaluation: %s | Duration: %.3fs\n" .
            "Exception: %s\n" .
            "Message: %s\n" .
            "File: %s:%d\n" .
            "Context: %s\n" .
            "Stack Trace:\n%s\n" .
            str_repeat('-', 80) . "\n",
            $timestamp,
            $evaluationId,
            $duration,
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            json_encode($context, JSON_PRETTY_PRINT),
            $exception->getTraceAsString()
        );
        
        // Write to dedicated error log file
        error_log($errorMessage, 3, $this->errorLogPath);
        
        // Also log to standard error log for visibility
        error_log("LLMWeightingEngine error for evaluation {$evaluationId}: " . $exception->getMessage());
    }
    
    /**
     * Log successful weight calculation
     * 
     * Requirements: 19.4
     * 
     * @param string $evaluationId Evaluation identifier
     * @param float $duration Duration in seconds
     * @return void
     */
    private function logSuccess(string $evaluationId, float $duration): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $message = sprintf(
            "[%s] SUCCESS - Evaluation: %s | Duration: %.3fs\n",
            $timestamp,
            $evaluationId,
            $duration
        );
        
        error_log($message, 3, $this->errorLogPath);
    }
    
    /**
     * Log fallback to static weighting
     * 
     * Requirements: 19.4
     * 
     * @param string $evaluationId Evaluation identifier
     * @param string $reason Reason for fallback
     * @return void
     */
    private function logFallback(string $evaluationId, string $reason): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $message = sprintf(
            "[%s] FALLBACK - Evaluation: %s | Reason: %s\n",
            $timestamp,
            $evaluationId,
            $reason
        );
        
        error_log($message, 3, $this->errorLogPath);
        error_log("Falling back to static weighting for evaluation {$evaluationId}: {$reason}");
    }
    
    /**
     * Log retry attempt
     * 
     * Requirements: 19.4
     * 
     * @param int $attempt Current attempt number
     * @param int $maxRetries Maximum retries
     * @param string $error Error message
     * @return void
     */
    private function logRetryAttempt(int $attempt, int $maxRetries, string $error): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $message = sprintf(
            "[%s] RETRY - Attempt %d/%d | Error: %s\n",
            $timestamp,
            $attempt,
            $maxRetries,
            $error
        );
        
        error_log($message, 3, $this->errorLogPath);
    }
    
    /**
     * Log successful retry
     * 
     * Requirements: 19.4
     * 
     * @param int $attempt Attempt number that succeeded
     * @return void
     */
    private function logRetrySuccess(int $attempt): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $message = sprintf(
            "[%s] RETRY SUCCESS - Succeeded on attempt %d\n",
            $timestamp,
            $attempt
        );
        
        error_log($message, 3, $this->errorLogPath);
    }
    
    /**
     * Increment total count in cache
     * 
     * Tracks total number of weight calculation attempts.
     * 
     * Requirements: 19.5
     * 
     * @return void
     */
    private function incrementTotalCount(): void
    {
        try {
            $cached = $this->cache->getCachedResponse(self::CACHE_KEY_TOTAL_COUNT);
            $count = $cached !== null ? (int)$cached : 0;
            $count++;
            $this->cache->cacheResponse(self::CACHE_KEY_TOTAL_COUNT, (string)$count, self::CACHE_TTL);
        } catch (\Exception $e) {
            error_log("Failed to increment total count: " . $e->getMessage());
        }
    }
    
    /**
     * Increment fallback count in cache
     * 
     * Tracks number of times fallback weighting was used.
     * 
     * Requirements: 19.5
     * 
     * @return void
     */
    private function incrementFallbackCount(): void
    {
        try {
            $cached = $this->cache->getCachedResponse(self::CACHE_KEY_FALLBACK_COUNT);
            $count = $cached !== null ? (int)$cached : 0;
            $count++;
            $this->cache->cacheResponse(self::CACHE_KEY_FALLBACK_COUNT, (string)$count, self::CACHE_TTL);
        } catch (\Exception $e) {
            error_log("Failed to increment fallback count: " . $e->getMessage());
        }
    }
    
    /**
     * Get fallback rate
     * 
     * Calculates the percentage of weight calculations that used fallback.
     * 
     * Requirements: 19.5
     * 
     * @return float Fallback rate (0.0 to 1.0)
     */
    public function getFallbackRate(): float
    {
        try {
            $totalCached = $this->cache->getCachedResponse(self::CACHE_KEY_TOTAL_COUNT);
            $fallbackCached = $this->cache->getCachedResponse(self::CACHE_KEY_FALLBACK_COUNT);
            
            $total = $totalCached !== null ? (int)$totalCached : 0;
            $fallback = $fallbackCached !== null ? (int)$fallbackCached : 0;
            
            if ($total === 0) {
                return 0.0;
            }
            
            return $fallback / $total;
        } catch (\Exception $e) {
            error_log("Failed to calculate fallback rate: " . $e->getMessage());
            return 0.0;
        }
    }
    
    /**
     * Check fallback rate and generate alert if threshold exceeded
     * 
     * Monitors fallback rate and triggers alert if it exceeds the configured threshold.
     * Default threshold is 5% (0.05).
     * 
     * Requirements: 19.5
     * 
     * @return void
     */
    private function checkFallbackRate(): void
    {
        try {
            $rate = $this->getFallbackRate();
            
            if ($rate > $this->fallbackAlertThreshold) {
                $this->generateFallbackAlert($rate);
            }
        } catch (\Exception $e) {
            error_log("Failed to check fallback rate: " . $e->getMessage());
        }
    }
    
    /**
     * Generate alert for high fallback rate
     * 
     * Creates an alert when fallback rate exceeds threshold.
     * Integrates with existing AlertManager system.
     * 
     * Requirements: 19.5
     * 
     * @param float $rate Current fallback rate
     * @return void
     */
    private function generateFallbackAlert(float $rate): void
    {
        try {
            $percentage = round($rate * 100, 2);
            $threshold = round($this->fallbackAlertThreshold * 100, 2);
            
            $alertData = [
                'type' => 'adaptive_weighting_high_fallback_rate',
                'severity' => 'warning',
                'message' => "Adaptive weighting fallback rate ({$percentage}%) exceeds threshold ({$threshold}%)",
                'details' => [
                    'fallback_rate' => $rate,
                    'threshold' => $this->fallbackAlertThreshold,
                    'fallback_count' => $this->cache->getCachedResponse(self::CACHE_KEY_FALLBACK_COUNT),
                    'total_count' => $this->cache->getCachedResponse(self::CACHE_KEY_TOTAL_COUNT),
                    'timestamp' => date('Y-m-d H:i:s')
                ],
                'current_value' => $rate,
                'threshold' => $this->fallbackAlertThreshold
            ];
            
            $this->alertManager->triggerAlert('adaptive_weighting_fallback_rate', $alertData);
            
            // Log alert generation
            $timestamp = date('Y-m-d H:i:s');
            $message = sprintf(
                "[%s] ALERT - High fallback rate: %.2f%% (threshold: %.2f%%)\n",
                $timestamp,
                $percentage,
                $threshold
            );
            error_log($message, 3, $this->errorLogPath);
            
        } catch (\Exception $e) {
            error_log("Failed to generate fallback alert: " . $e->getMessage());
        }
    }
    
    /**
     * Reset fallback tracking counters
     * 
     * Clears fallback and total count from cache.
     * Useful for testing or periodic resets.
     * 
     * @return void
     */
    public function resetFallbackTracking(): void
    {
        try {
            $this->cache->cacheResponse(self::CACHE_KEY_FALLBACK_COUNT, '0', self::CACHE_TTL);
            $this->cache->cacheResponse(self::CACHE_KEY_TOTAL_COUNT, '0', self::CACHE_TTL);
            
            $timestamp = date('Y-m-d H:i:s');
            $message = sprintf("[%s] Fallback tracking reset\n", $timestamp);
            error_log($message, 3, $this->errorLogPath);
        } catch (\Exception $e) {
            error_log("Failed to reset fallback tracking: " . $e->getMessage());
        }
    }
    
    /**
     * Get fallback statistics
     * 
     * Returns current fallback tracking statistics.
     * 
     * @return array Statistics array
     */
    public function getFallbackStats(): array
    {
        try {
            $totalCached = $this->cache->getCachedResponse(self::CACHE_KEY_TOTAL_COUNT);
            $fallbackCached = $this->cache->getCachedResponse(self::CACHE_KEY_FALLBACK_COUNT);
            
            $total = $totalCached !== null ? (int)$totalCached : 0;
            $fallback = $fallbackCached !== null ? (int)$fallbackCached : 0;
            $rate = $this->getFallbackRate();
            
            return [
                'total_calculations' => $total,
                'fallback_count' => $fallback,
                'success_count' => $total - $fallback,
                'fallback_rate' => $rate,
                'fallback_percentage' => round($rate * 100, 2),
                'threshold' => $this->fallbackAlertThreshold,
                'threshold_percentage' => round($this->fallbackAlertThreshold * 100, 2),
                'alert_triggered' => $rate > $this->fallbackAlertThreshold
            ];
        } catch (\Exception $e) {
            error_log("Failed to get fallback stats: " . $e->getMessage());
            return [
                'total_calculations' => 0,
                'fallback_count' => 0,
                'success_count' => 0,
                'fallback_rate' => 0.0,
                'fallback_percentage' => 0.0,
                'threshold' => $this->fallbackAlertThreshold,
                'threshold_percentage' => round($this->fallbackAlertThreshold * 100, 2),
                'alert_triggered' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Select optimal critics for evaluation using LLM analysis with multi-domain support
     * 
     * Uses LLM to analyze all available critics and the evaluation context to select
     * the most appropriate critics. The LLM determines:
     * - Which critics to include based on domain expertise match
     * - Optimal number of critics (not fixed at 3)
     * - Balance between domain specialists and generalists
     * - Diversity of perspectives
     * 
     * Multi-Domain Selection Strategy:
     * - Prioritize critics with expertise in required domains
     * - Balance domain specialists (deep expertise in one domain) vs generalists (broad expertise)
     * - Ensure coverage of all required domains if possible
     * - Consider domain expertise level (expert > competent > novice)
     * 
     * Requirements: 16.1, 16.2, 16.3, 16.4, 15.1, 15.2, 15.3, 15.4
     * 
     * @param array $availableCritics All available critic agents
     * @param array $context Evaluation context with required_domains
     * @return array Selection result with selected critics, explanations, and rationale
     * @throws \RuntimeException If LLM selection fails and no fallback possible
     */
    public function selectCritics(array $availableCritics, array $context): array
    {
        $evaluationId = $context['evaluation_id'] ?? uniqid('eval_', true);
        $startTime = microtime(true);
        
        try {
            // Validate minimum critics available
            if (count($availableCritics) < 2) {
                throw new \RuntimeException('Insufficient critics available. Minimum 2 required, got ' . count($availableCritics));
            }
            
            // Step 1: Gather data for all available critics
            $criticData = $this->criticDataCollector->collectCriticData($availableCritics);
            
            if (empty($criticData)) {
                throw new \RuntimeException('No critic data collected for selection');
            }
            
            // Step 2: Build LLM prompt for critic selection
            $prompt = $this->promptBuilder->buildCriticSelectionPrompt($criticData, $context);
            
            // Step 3: Call LLM service with retry logic
            $llmResponse = $this->callLLMWithRetry($prompt);
            
            // Step 4: Parse LLM response
            $parsedResponse = $this->parseCriticSelectionResponse($llmResponse, array_keys($criticData));
            
            // Step 5: Validate selection
            $this->validateCriticSelection($parsedResponse, $availableCritics);
            
            // Step 6: Build result
            $result = [
                'selected_critic_ids' => $parsedResponse['selected_critics'],
                'selected_critics' => $this->getCriticsByIds($availableCritics, $parsedResponse['selected_critics']),
                'selection_rationale' => $parsedResponse['selection_rationale'],
                'critic_explanations' => $parsedResponse['critic_explanations'],
                'rejected_critics' => $parsedResponse['rejected_critics'],
                'optimal_count' => $parsedResponse['optimal_count'],
                'diversity_score' => $parsedResponse['diversity_score'],
                'domain_coverage' => $parsedResponse['domain_coverage'] ?? [],
                'evaluation_id' => $evaluationId,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            $duration = microtime(true) - $startTime;
            $this->logCriticSelection($evaluationId, $result, $duration);
            
            return $result;
            
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            $this->logError($evaluationId, $e, $context, $duration);
            
            // Fall back to default selection if enabled
            if ($this->fallbackEnabled) {
                return $this->fallbackCriticSelection($evaluationId, $availableCritics, $context, $e->getMessage());
            }
            
            throw $e;
        }
    }
    
    /**
     * Parse LLM critic selection response
     * 
     * Expected JSON format:
     * {
     *   "selected_critics": ["critic_id1", "critic_id2", ...],
     *   "selection_rationale": "overall reasoning",
     *   "critic_explanations": {
     *     "critic_id": "why this critic was selected",
     *     ...
     *   },
     *   "rejected_critics": {
     *     "critic_id": "why this critic was not selected",
     *     ...
     *   },
     *   "optimal_count": number,
     *   "diversity_score": 0.0-1.0,
     *   "domain_coverage": {
     *     "domain": ["critic_id1", "critic_id2"],
     *     ...
     *   }
     * }
     * 
     * Requirements: 16.1, 16.3, 16.4
     * 
     * @param string $response LLM response (JSON)
     * @param array $availableCriticIds List of available critic IDs
     * @return array Parsed selection response
     * @throws \RuntimeException If response is invalid
     */
    private function parseCriticSelectionResponse(string $response, array $availableCriticIds): array
    {
        // Try to extract JSON from response
        $jsonStart = strpos($response, '{');
        $jsonEnd = strrpos($response, '}');
        
        if ($jsonStart === false || $jsonEnd === false) {
            throw new \RuntimeException('No JSON object found in LLM critic selection response');
        }
        
        $jsonString = substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);
        $data = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON in LLM critic selection response: ' . json_last_error_msg());
        }
        
        // Validate required fields
        if (!isset($data['selected_critics']) || !is_array($data['selected_critics'])) {
            throw new \RuntimeException('LLM response missing "selected_critics" field');
        }
        
        if (empty($data['selected_critics'])) {
            throw new \RuntimeException('LLM selected no critics');
        }
        
        // Validate selected critics are from available pool
        foreach ($data['selected_critics'] as $criticId) {
            if (!in_array($criticId, $availableCriticIds, true)) {
                throw new \RuntimeException("LLM selected invalid critic: {$criticId}");
            }
        }
        
        return [
            'selected_critics' => $data['selected_critics'],
            'selection_rationale' => $data['selection_rationale'] ?? 'No rationale provided',
            'critic_explanations' => $data['critic_explanations'] ?? [],
            'rejected_critics' => $data['rejected_critics'] ?? [],
            'optimal_count' => $data['optimal_count'] ?? count($data['selected_critics']),
            'diversity_score' => $data['diversity_score'] ?? 0.5,
            'domain_coverage' => $data['domain_coverage'] ?? []
        ];
    }
    
    /**
     * Validate critic selection meets minimum requirements
     * 
     * Ensures:
     * - At least 2 critics selected (minimum for valid consensus)
     * - All selected critics are valid
     * 
     * Requirements: 16.4, 15.3
     * 
     * @param array $selection Parsed selection response
     * @param array $availableCritics Available critics
     * @return void
     * @throws \RuntimeException If validation fails
     */
    private function validateCriticSelection(array $selection, array $availableCritics): void
    {
        $selectedCount = count($selection['selected_critics']);
        
        // Ensure minimum 2 critics
        if ($selectedCount < 2) {
            throw new \RuntimeException(
                "LLM selected insufficient critics. Minimum 2 required, got {$selectedCount}"
            );
        }
        
        // Ensure not more than available
        if ($selectedCount > count($availableCritics)) {
            throw new \RuntimeException(
                "LLM selected more critics than available: {$selectedCount} > " . count($availableCritics)
            );
        }
    }
    
    /**
     * Get critic objects by IDs
     * 
     * @param array $availableCritics All available critics
     * @param array $criticIds Selected critic IDs
     * @return array Selected critic objects
     */
    private function getCriticsByIds(array $availableCritics, array $criticIds): array
    {
        $selected = [];
        
        foreach ($availableCritics as $critic) {
            // Handle both objects with getCriticId() method and arrays with 'critic_id' key
            $criticId = null;
            if (is_object($critic) && method_exists($critic, 'getCriticId')) {
                $criticId = $critic->getCriticId();
            } elseif (is_array($critic) && isset($critic['critic_id'])) {
                $criticId = $critic['critic_id'];
            }
            
            if ($criticId && in_array($criticId, $criticIds, true)) {
                $selected[] = $critic;
            }
        }
        
        return $selected;
    }
    
    /**
     * Fall back to default critic selection
     * 
     * When LLM selection fails, uses a simple heuristic-based selection:
     * - Select critics with highest reputation scores
     * - Ensure minimum 2 critics
     * - Prefer critics with domain expertise matching context if available
     * 
     * Requirements: 16.4, 19.3
     * 
     * @param string $evaluationId Evaluation identifier
     * @param array $availableCritics Available critics
     * @param array $context Evaluation context
     * @param string $reason Reason for fallback
     * @return array Fallback selection result
     */
    private function fallbackCriticSelection(
        string $evaluationId,
        array $availableCritics,
        array $context,
        string $reason
    ): array {
        $this->logFallback($evaluationId, "Critic selection fallback: {$reason}");
        
        // Collect critic data for scoring
        $criticData = $this->criticDataCollector->collectCriticData($availableCritics);
        
        // Score critics by reputation and domain match
        $scoredCritics = [];
        $requiredDomains = $context['required_domains'] ?? [];
        
        foreach ($criticData as $criticId => $data) {
            $reputation = $data['reputation']['score'] ?? 0.75;
            $domains = $data['expertise']['domains'] ?? [];
            
            // Calculate domain match score
            $domainMatchScore = 0.0;
            if (!empty($requiredDomains) && !empty($domains)) {
                $matchCount = 0;
                foreach ($requiredDomains as $requiredDomain) {
                    if (isset($domains[$requiredDomain])) {
                        $matchCount++;
                    }
                }
                $domainMatchScore = $matchCount / count($requiredDomains);
            }
            
            // Combined score: reputation (60%) + domain match (40%)
            $score = ($reputation * 0.6) + ($domainMatchScore * 0.4);
            $scoredCritics[] = [
                'critic_id' => $criticId,
                'score' => $score,
                'reputation' => $reputation,
                'domain_match' => $domainMatchScore
            ];
        }
        
        // Sort by score descending
        usort($scoredCritics, fn($a, $b) => $b['score'] <=> $a['score']);
        
        // Select top 3 critics (or all if less than 3 available)
        $defaultCount = min(3, count($scoredCritics));
        $selectedScored = array_slice($scoredCritics, 0, max(2, $defaultCount));
        
        $selectedCriticIds = array_map(fn($s) => $s['critic_id'], $selectedScored);
        
        // Build fallback result
        $result = [
            'selected_critic_ids' => $selectedCriticIds,
            'selected_critics' => $this->getCriticsByIds($availableCritics, $selectedCriticIds),
            'selection_rationale' => "Fallback selection based on reputation and domain match due to: {$reason}",
            'critic_explanations' => array_combine(
                $selectedCriticIds,
                array_map(
                    fn($s) => "Selected by fallback (reputation: {$s['reputation']}, domain match: {$s['domain_match']})",
                    $selectedScored
                )
            ),
            'rejected_critics' => [],
            'optimal_count' => count($selectedCriticIds),
            'diversity_score' => 0.5,
            'domain_coverage' => [],
            'evaluation_id' => $evaluationId,
            'timestamp' => date('Y-m-d H:i:s'),
            'is_fallback' => true,
            'fallback_reason' => $reason
        ];
        
        $this->logCriticSelection($evaluationId, $result, 0.0);
        
        return $result;
    }
    
    /**
     * Log critic selection
     * 
     * @param string $evaluationId Evaluation identifier
     * @param array $result Selection result
     * @param float $duration Duration in seconds
     * @return void
     */
    private function logCriticSelection(string $evaluationId, array $result, float $duration): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $selectedCount = count($result['selected_critic_ids']);
        $isFallback = $result['is_fallback'] ?? false;
        $status = $isFallback ? 'FALLBACK' : 'SUCCESS';
        
        $message = sprintf(
            "[%s] CRITIC_SELECTION_%s - Evaluation: %s | Selected: %d critics | Duration: %.3fs\n" .
            "Selected Critics: %s\n" .
            "Rationale: %s\n",
            $timestamp,
            $status,
            $evaluationId,
            $selectedCount,
            $duration,
            implode(', ', $result['selected_critic_ids']),
            $result['selection_rationale']
        );
        
        error_log($message, 3, $this->errorLogPath);
    }
    
    /**
     * Determine appropriate weight bounds using LLM analysis
     * 
     * Uses LLM to analyze the evaluation context and determine appropriate
     * minimum and maximum weight bounds. The LLM considers:
     * - Number of critics (more critics → tighter bounds to prevent dominance)
     * - Evaluation criticality (critical → allow expert dominance with wider bounds)
     * - Diversity needs (high diversity → tighter bounds to ensure all voices heard)
     * - Context requirements (security-critical, performance-critical, etc.)
     * 
     * Typical bounds are 0.1 - 0.5. The LLM may suggest different bounds based on context.
     * When bounds exceed typical ranges, the rationale is logged for audit.
     * 
     * Requirements: 14.1, 14.2, 14.3, 14.4
     * 
     * @param array $critics Array of critic agents
     * @param array $context Evaluation context
     * @return array Bounds result with min, max, rationale, and explanation
     * @throws \RuntimeException If LLM bounds determination fails
     */
    public function determineBounds(array $critics, array $context): array
    {
        $evaluationId = $context['evaluation_id'] ?? uniqid('eval_', true);
        $startTime = microtime(true);
        
        try {
            // Step 1: Gather critic data
            $criticData = $this->criticDataCollector->collectCriticData($critics);
            
            if (empty($criticData)) {
                throw new \RuntimeException('No critic data collected for bounds determination');
            }
            
            // Step 2: Build LLM prompt for bounds determination
            $prompt = $this->promptBuilder->buildBoundsDeterminationPrompt($criticData, $context);
            
            // Step 3: Call LLM service with retry logic
            $llmResponse = $this->callLLMWithRetry($prompt);
            
            // Step 4: Parse LLM response
            $parsedResponse = $this->parseBoundsResponse($llmResponse);
            
            // Step 5: Validate bounds
            $this->validateBounds($parsedResponse);
            
            // Step 6: Check if bounds exceed typical range and log rationale
            $exceedsTypical = $this->checkBoundsExceedTypical($parsedResponse);
            
            // Step 7: Build result
            $result = [
                'min_bound' => $parsedResponse['min_bound'],
                'max_bound' => $parsedResponse['max_bound'],
                'rationale' => $parsedResponse['rationale'],
                'explanation' => $parsedResponse['explanation'],
                'factors_considered' => $parsedResponse['factors_considered'],
                'exceeds_typical' => $exceedsTypical,
                'typical_bounds' => ['min' => 0.1, 'max' => 0.5],
                'evaluation_id' => $evaluationId,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            $duration = microtime(true) - $startTime;
            $this->logBoundsDetermination($evaluationId, $result, $duration);
            
            return $result;
            
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            $this->logError($evaluationId, $e, $context, $duration);
            
            // Fall back to typical bounds if enabled
            if ($this->fallbackEnabled) {
                return $this->fallbackBoundsDetermination($evaluationId, $critics, $context, $e->getMessage());
            }
            
            throw $e;
        }
    }
    
    /**
     * Parse LLM bounds determination response
     * 
     * Expected JSON format:
     * {
     *   "min_bound": 0.1,
     *   "max_bound": 0.5,
     *   "rationale": "overall reasoning for these bounds",
     *   "explanation": "detailed explanation of why these bounds are appropriate",
     *   "factors_considered": {
     *     "num_critics": "analysis of how number of critics influenced bounds",
     *     "criticality": "analysis of evaluation criticality",
     *     "diversity_needs": "analysis of diversity requirements",
     *     "context_requirements": "analysis of special context requirements"
     *   }
     * }
     * 
     * Requirements: 14.1, 14.2
     * 
     * @param string $response LLM response (JSON)
     * @return array Parsed bounds response
     * @throws \RuntimeException If response is invalid
     */
    private function parseBoundsResponse(string $response): array
    {
        // Try to extract JSON from response
        $jsonStart = strpos($response, '{');
        $jsonEnd = strrpos($response, '}');
        
        if ($jsonStart === false || $jsonEnd === false) {
            throw new \RuntimeException('No JSON object found in LLM bounds determination response');
        }
        
        $jsonString = substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);
        $data = json_decode($jsonString, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON in LLM bounds determination response: ' . json_last_error_msg());
        }
        
        // Validate required fields
        if (!isset($data['min_bound']) || !is_numeric($data['min_bound'])) {
            throw new \RuntimeException('LLM response missing or invalid "min_bound" field');
        }
        
        if (!isset($data['max_bound']) || !is_numeric($data['max_bound'])) {
            throw new \RuntimeException('LLM response missing or invalid "max_bound" field');
        }
        
        return [
            'min_bound' => (float)$data['min_bound'],
            'max_bound' => (float)$data['max_bound'],
            'rationale' => $data['rationale'] ?? 'No rationale provided',
            'explanation' => $data['explanation'] ?? 'No explanation provided',
            'factors_considered' => $data['factors_considered'] ?? []
        ];
    }
    
    /**
     * Validate bounds are within acceptable range
     * 
     * Ensures:
     * - min_bound >= 0.0
     * - max_bound <= 1.0
     * - min_bound < max_bound
     * - Bounds are reasonable (min not too high, max not too low)
     * 
     * Requirements: 14.1, 14.3
     * 
     * @param array $bounds Parsed bounds response
     * @return void
     * @throws \RuntimeException If bounds are invalid
     */
    private function validateBounds(array $bounds): void
    {
        $minBound = $bounds['min_bound'];
        $maxBound = $bounds['max_bound'];
        
        // Check absolute limits
        if ($minBound < 0.0) {
            throw new \RuntimeException("Invalid min_bound: {$minBound}. Must be >= 0.0");
        }
        
        if ($maxBound > 1.0) {
            throw new \RuntimeException("Invalid max_bound: {$maxBound}. Must be <= 1.0");
        }
        
        // Check min < max
        if ($minBound >= $maxBound) {
            throw new \RuntimeException(
                "Invalid bounds: min_bound ({$minBound}) must be less than max_bound ({$maxBound})"
            );
        }
        
        // Check reasonable range (at least 0.05 difference)
        $range = $maxBound - $minBound;
        if ($range < 0.05) {
            throw new \RuntimeException(
                "Bounds range too narrow: {$range}. Minimum range is 0.05 to allow meaningful weight variation"
            );
        }
    }
    
    /**
     * Check if bounds exceed typical range and should be logged
     * 
     * Typical bounds are 0.1 - 0.5. If LLM suggests bounds outside this range,
     * it indicates a special context that warrants logging and review.
     * 
     * Requirements: 14.4
     * 
     * @param array $bounds Parsed bounds response
     * @return bool True if bounds exceed typical range
     */
    private function checkBoundsExceedTypical(array $bounds): bool
    {
        $typicalMin = 0.1;
        $typicalMax = 0.5;
        
        $minBound = $bounds['min_bound'];
        $maxBound = $bounds['max_bound'];
        
        // Check if either bound is outside typical range
        $exceedsTypical = ($minBound < $typicalMin) || ($maxBound > $typicalMax);
        
        if ($exceedsTypical) {
            $this->logBoundsExceedTypical($bounds, $typicalMin, $typicalMax);
        }
        
        return $exceedsTypical;
    }
    
    /**
     * Log when bounds exceed typical range
     * 
     * Logs detailed information when LLM suggests bounds outside the typical
     * 0.1 - 0.5 range, including the rationale for the decision.
     * 
     * Requirements: 14.4
     * 
     * @param array $bounds Parsed bounds response
     * @param float $typicalMin Typical minimum bound
     * @param float $typicalMax Typical maximum bound
     * @return void
     */
    private function logBoundsExceedTypical(array $bounds, float $typicalMin, float $typicalMax): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $message = sprintf(
            "[%s] BOUNDS_EXCEED_TYPICAL\n" .
            "Suggested Bounds: [%.3f, %.3f]\n" .
            "Typical Bounds: [%.3f, %.3f]\n" .
            "Rationale: %s\n" .
            "Explanation: %s\n" .
            str_repeat('-', 80) . "\n",
            $timestamp,
            $bounds['min_bound'],
            $bounds['max_bound'],
            $typicalMin,
            $typicalMax,
            $bounds['rationale'],
            $bounds['explanation']
        );
        
        error_log($message, 3, $this->errorLogPath);
        error_log("LLM suggested bounds outside typical range: [{$bounds['min_bound']}, {$bounds['max_bound']}]");
    }
    
    /**
     * Fall back to typical bounds determination
     * 
     * When LLM bounds determination fails, uses heuristic-based approach:
     * - Default to typical bounds (0.1 - 0.5)
     * - Adjust based on number of critics:
     *   - 2 critics: wider bounds (0.05 - 0.7) to allow differentiation
     *   - 3-5 critics: typical bounds (0.1 - 0.5)
     *   - 6+ critics: tighter bounds (0.15 - 0.4) to prevent dominance
     * - Adjust based on criticality:
     *   - Critical: wider max (0.6) to allow expert dominance
     *   - Low: tighter bounds (0.15 - 0.4) to ensure diversity
     * 
     * Requirements: 14.1, 14.3, 19.3
     * 
     * @param string $evaluationId Evaluation identifier
     * @param array $critics Array of critics
     * @param array $context Evaluation context
     * @param string $reason Reason for fallback
     * @return array Fallback bounds result
     */
    private function fallbackBoundsDetermination(
        string $evaluationId,
        array $critics,
        array $context,
        string $reason
    ): array {
        $this->logFallback($evaluationId, "Bounds determination fallback: {$reason}");
        
        $numCritics = count($critics);
        $priorityLevel = $context['priority_level'] ?? 'medium';
        
        // Default typical bounds
        $minBound = 0.1;
        $maxBound = 0.5;
        
        // Adjust based on number of critics
        if ($numCritics === 2) {
            $minBound = 0.05;
            $maxBound = 0.7;
            $numCriticsReason = "With only 2 critics, wider bounds allow meaningful differentiation";
        } elseif ($numCritics >= 6) {
            $minBound = 0.15;
            $maxBound = 0.4;
            $numCriticsReason = "With {$numCritics} critics, tighter bounds prevent single critic dominance";
        } else {
            $numCriticsReason = "With {$numCritics} critics, typical bounds provide good balance";
        }
        
        // Adjust based on criticality
        $criticalityReason = '';
        if ($priorityLevel === 'critical') {
            $maxBound = min(0.6, $maxBound + 0.1);
            $criticalityReason = "Critical evaluation allows higher max bound for expert dominance";
        } elseif ($priorityLevel === 'low') {
            $minBound = max(0.15, $minBound + 0.05);
            $maxBound = min(0.4, $maxBound - 0.1);
            $criticalityReason = "Low priority evaluation uses tighter bounds to ensure diversity";
        } else {
            $criticalityReason = "Medium priority uses standard bounds";
        }
        
        $result = [
            'min_bound' => $minBound,
            'max_bound' => $maxBound,
            'rationale' => "Fallback bounds determination due to: {$reason}",
            'explanation' => "Using heuristic-based bounds. {$numCriticsReason}. {$criticalityReason}.",
            'factors_considered' => [
                'num_critics' => $numCriticsReason,
                'criticality' => $criticalityReason,
                'diversity_needs' => 'Standard diversity requirements applied',
                'context_requirements' => 'No special context requirements detected'
            ],
            'exceeds_typical' => ($minBound < 0.1) || ($maxBound > 0.5),
            'typical_bounds' => ['min' => 0.1, 'max' => 0.5],
            'evaluation_id' => $evaluationId,
            'timestamp' => date('Y-m-d H:i:s'),
            'is_fallback' => true,
            'fallback_reason' => $reason
        ];
        
        $this->logBoundsDetermination($evaluationId, $result, 0.0);
        
        return $result;
    }
    
    /**
     * Log bounds determination
     * 
     * @param string $evaluationId Evaluation identifier
     * @param array $result Bounds determination result
     * @param float $duration Duration in seconds
     * @return void
     */
    private function logBoundsDetermination(string $evaluationId, array $result, float $duration): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $isFallback = $result['is_fallback'] ?? false;
        $status = $isFallback ? 'FALLBACK' : 'SUCCESS';
        $exceedsTypical = $result['exceeds_typical'] ? 'YES' : 'NO';
        
        $message = sprintf(
            "[%s] BOUNDS_DETERMINATION_%s - Evaluation: %s | Duration: %.3fs\n" .
            "Bounds: [%.3f, %.3f]\n" .
            "Exceeds Typical: %s\n" .
            "Rationale: %s\n",
            $timestamp,
            $status,
            $evaluationId,
            $duration,
            $result['min_bound'],
            $result['max_bound'],
            $exceedsTypical,
            $result['rationale']
        );
        
        error_log($message, 3, $this->errorLogPath);
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
            $llmResponse = $this->callLLMWithRetry($prompt);
            
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
                error_log("Failed to store anomaly: " . $e->getMessage());
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
                    error_log("Failed to generate alert for anomaly {$anomaly['id']}: " . $e->getMessage());
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
            "[%s] ANOMALY_ALERT - ID: %d | Type: %s | Critic: %s | Severity: %s\n" .
            "Description: %s\n",
            $timestamp,
            $anomaly['id'],
            $anomaly['type'],
            $anomaly['critic_id'] ?? 'N/A',
            $anomaly['severity'],
            $anomaly['description']
        );
        
        error_log($message, 3, $this->errorLogPath);
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
            "[%s] ANOMALY_DETECTION - Analysis: %s | Duration: %.3fs\n" .
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
        
        error_log($message, 3, $this->errorLogPath);
    }
    
    /**
     * Set migration manager
     * 
     * Enables migration mode support for parallel weight calculation.
     * 
     * Requirements: 30.1, 30.2
     * 
     * @param MigrationManager $migrationManager Migration manager instance
     * @return void
     */
    public function setMigrationManager(MigrationManager $migrationManager): void
    {
        $this->migrationManager = $migrationManager;
    }
    
    /**
     * Calculate weights with migration support
     * 
     * In migration mode, calculates both static and adaptive weights in parallel
     * and logs the comparison for analysis. Uses rollout percentage to determine
     * which weight type to return.
     * 
     * Requirements: 30.1, 30.2, 30.3, 30.4
     * 
     * @param array $critics Array of critic agents
     * @param array $context Evaluation context
     * @param array $evaluations Critic evaluations for consensus calculation
     * @return array ['weights' => WeightResult, 'consensus' => float, 'used_adaptive' => bool]
     */
    public function calculateWeightsWithMigration(
        array $critics,
        array $context,
        array $evaluations
    ): array {
        $evaluationId = $context['evaluation_id'] ?? uniqid('eval_', true);
        
        // If no migration manager, use standard adaptive weighting
        if ($this->migrationManager === null) {
            $weights = $this->calculateAdaptiveWeights($critics, $context);
            $consensus = $this->calculateConsensus($evaluations, $weights->normalizedWeights);
            return [
                'weights' => $weights,
                'consensus' => $consensus,
                'used_adaptive' => true,
            ];
        }
        
        // Check if in migration mode
        if (!$this->migrationManager->isMigrationMode()) {
            // Not in migration mode - use standard behavior
            $weights = $this->calculateAdaptiveWeights($critics, $context);
            $consensus = $this->calculateConsensus($evaluations, $weights->normalizedWeights);
            return [
                'weights' => $weights,
                'consensus' => $consensus,
                'used_adaptive' => true,
            ];
        }
        
        // MIGRATION MODE: Calculate both static and adaptive weights
        
        // Calculate static weights (reputation-based)
        $staticWeights = $this->calculateStaticWeights($critics);
        $staticConsensus = $this->calculateConsensus($evaluations, $staticWeights);
        
        // Calculate adaptive weights (LLM-based)
        $adaptiveWeightResult = $this->calculateAdaptiveWeights($critics, $context);
        $dynamicConsensus = $this->calculateConsensus($evaluations, $adaptiveWeightResult->normalizedWeights);
        
        // Log comparison for analysis
        $this->migrationManager->logMigrationComparison(
            $evaluationId,
            $staticWeights,
            $adaptiveWeightResult->normalizedWeights,
            $staticConsensus,
            $dynamicConsensus,
            $context
        );
        
        // Determine which weights to use based on rollout percentage
        $useAdaptive = $this->migrationManager->shouldUseAdaptiveWeighting($evaluationId);
        
        if ($useAdaptive) {
            return [
                'weights' => $adaptiveWeightResult,
                'consensus' => $dynamicConsensus,
                'used_adaptive' => true,
                'static_consensus' => $staticConsensus, // For comparison
            ];
        } else {
            // Create WeightResult for static weights
            $staticWeightResult = new WeightResult(
                $evaluationId,
                $staticWeights,
                $staticWeights, // Already normalized
                [], // No explanations for static
                'Static reputation-based weighting',
                [],
                null,
                false,
                null
            );
            
            return [
                'weights' => $staticWeightResult,
                'consensus' => $staticConsensus,
                'used_adaptive' => false,
                'dynamic_consensus' => $dynamicConsensus, // For comparison
            ];
        }
    }
    
    /**
     * Calculate static reputation-based weights
     * 
     * Fallback weighting method using only reputation scores.
     * Weights are proportional to reputation: weight = reputation / sum(reputations)
     * 
     * Requirements: 30.1, 30.4
     * 
     * @param array $critics Array of critic agents
     * @return array Normalized weights [criticId => weight]
     */
    private function calculateStaticWeights(array $critics): array
    {
        $weights = [];
        $totalReputation = 0.0;
        
        // Collect reputation scores
        foreach ($critics as $critic) {
            $criticId = $critic['id'] ?? $critic['criticId'] ?? uniqid('critic_');
            $reputation = $critic['reputation'] ?? 0.75; // Default reputation
            
            $weights[$criticId] = $reputation;
            $totalReputation += $reputation;
        }
        
        // Normalize
        if ($totalReputation > 0) {
            foreach ($weights as $criticId => $weight) {
                $weights[$criticId] = $weight / $totalReputation;
            }
        } else {
            // Equal weighting if no reputation data
            $equalWeight = 1.0 / count($critics);
            foreach ($weights as $criticId => $weight) {
                $weights[$criticId] = $equalWeight;
            }
        }
        
        return $weights;
    }
    
    /**
     * Calculate consensus from evaluations and weights
     * 
     * Consensus = Σ(critic_score × weight)
     * 
     * Requirements: 30.3
     * 
     * @param array $evaluations Critic evaluations [criticId => score]
     * @param array $weights Critic weights [criticId => weight]
     * @return float Consensus value
     */
    private function calculateConsensus(array $evaluations, array $weights): float
    {
        $consensus = 0.0;
        
        foreach ($evaluations as $criticId => $evaluation) {
            $score = is_array($evaluation) ? ($evaluation['score'] ?? 0.0) : $evaluation;
            $weight = $weights[$criticId] ?? 0.0;
            $consensus += $score * $weight;
        }
        
        return $consensus;
    }
}
