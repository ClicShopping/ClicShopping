<?php
declare(strict_types=1);

/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine;

use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Models\WeightResult;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\CriticDataCollector;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\LLMPromptBuilder;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\WeightNormalizer;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\WeightAuditLogger;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\WeightingLlmClient;
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
    private WeightingLlmClient $llmClient;
    private AlertManager $alertManager;
    private Cache $cache;
    private string $errorLogPath;

    // Configuration
    private int $maxRetries = 2;
    private int $timeoutSeconds = 30;
    private bool $fallbackEnabled = true;
    private float $fallbackAlertThreshold = 0.05; // 5%
    private int $maxParseRetries = 1; // extra LLM regenerations when a successful call returns unparseable JSON
    private int $minSampleForAlert = 20; // min evaluations before the fallback-rate alert may fire (avoids 1/1=100%)
    private bool $weightCacheEnabled = true; // reuse a recent weighting to skip the ~5s LLM call
    private int $weightCacheTtl = 1800; // 30 min — caches the weighting "shape"; bounds reputation drift
    private bool $debug = false; // gates verbose error_log (e.g. cache-hit traces)

    // Fallback tracking
    private const CACHE_KEY_FALLBACK_COUNT = 'adaptive_weighting_fallback_count';
    private const CACHE_KEY_TOTAL_COUNT = 'adaptive_weighting_total_count';
    private const CACHE_KEY_WEIGHTS_PREFIX = 'adaptive_weights_'; // + md5(critic set + action/output shape)
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
        array $config = [],
        ?WeightingLlmClient $llmClient = null
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
        if (isset($config['max_parse_retries'])) {
            $this->maxParseRetries = (int)$config['max_parse_retries'];
        }
        if (isset($config['min_sample_for_alert'])) {
            $this->minSampleForAlert = (int)$config['min_sample_for_alert'];
        }
        if (isset($config['weight_cache_enabled'])) {
            $this->weightCacheEnabled = (bool)$config['weight_cache_enabled'];
        }
        if (isset($config['weight_cache_ttl'])) {
            $this->weightCacheTtl = (int)$config['weight_cache_ttl'];
        }

        // Debug flag: follows the RAG debug constant (same as Infrastructure\Cache\Cache), overridable
        // via config. Gates verbose error_log such as cache-hit traces.
        $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER')
            && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
        if (isset($config['debug'])) {
            $this->debug = (bool)$config['debug'];
        }

        // Shared LLM transport (retry loop, Gpt facade, JSON repair) used by every
        // LLM-backed step: adaptive weighting, bounds determination, anomaly detection.
        // Injectable for deterministic tests (golden harness); defaults to the real client.
        $this->llmClient = $llmClient ?? new WeightingLlmClient($this->maxRetries, $this->debug, $this->errorLogPath);
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

            // Cache: the weighting decision depends on the critic SET + the action/output shape
            // (output_type, action_type, required_domains, priority, special_requirements), NOT on the
            // query text. A recent result is reusable, so reuse it to skip the ~5s LLM round-trip on
            // every analytics query. The TTL bounds reputation drift (weights are a soft signal).
            $cacheKey = $this->weightCacheKey($criticData, $context);
            if ($this->weightCacheEnabled) {
                $cached = $this->cache->getCachedResponse($cacheKey);
                if ($cached !== null) {
                    $parsed = json_decode($cached, true);
                    if (is_array($parsed) && isset($parsed['weights']) && is_array($parsed['weights'])) {
                        $this->logWeightCacheHit($evaluationId, microtime(true) - $startTime);
                        return $this->buildWeightResult($evaluationId, $parsed);
                    }
                }
            }

            // Step 2: Build structured prompt with domain matching requirements
            $prompt = $this->promptBuilder->buildWeightAnalysisPrompt($criticData, $context);

            // Call the LLM and parse its JSON, regenerating once if the response is
            // unparseable. callLLMWithRetry only covers transport failures; a successful call that
            // returns malformed JSON would otherwise drop straight to static weighting. A fresh
            // generation usually yields valid JSON — cheaper and more reliable than the fallback.
            $parsedResponse = $this->callAndParseWithRetry($prompt, array_keys($criticData));

            // Cache the parsed weighting for reuse by subsequent equivalent evaluations.
            if ($this->weightCacheEnabled) {
                $this->cache->cacheResponse($cacheKey, json_encode($parsedResponse), $this->weightCacheTtl);
            }

            // Step 5 + create WeightResult
            $result = $this->buildWeightResult($evaluationId, $parsedResponse);

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
     * Build the cache key for an adaptive-weighting result.
     *
     * Keyed on the stable factors the weighting actually depends on — the critic set and the
     * action/output shape — NOT the query text or volatile fields (evaluation_id, execution_metrics,
     * critic reputations). This maximizes reuse across equivalent evaluations; the cache TTL bounds
     * how stale the (reputation-influenced) weights may get.
     *
     * @param array $criticData Collected critic data (keyed by critic id)
     * @param array $context Evaluation context
     * @return string Cache key
     */
    private function weightCacheKey(array $criticData, array $context): string
    {
        $criticIds = array_keys($criticData);
        sort($criticIds);

        $key = [
            'critics' => $criticIds,
            'output_type' => $context['output_type'] ?? '',
            'action_type' => $context['action_type'] ?? '',
            'required_domains' => $context['required_domains'] ?? [],
            'priority' => $context['priority'] ?? '',
            'special_requirements' => $context['special_requirements'] ?? [],
        ];

        return self::CACHE_KEY_WEIGHTS_PREFIX . md5(json_encode($key));
    }

    /**
     * Build a WeightResult from a parsed weighting response (live or cached).
     *
     * Shared by the live LLM path and the cache-hit path so both produce an identical, non-fallback
     * result. Defensive defaults guard against a partial/corrupted cache entry.
     *
     * @param string $evaluationId Evaluation identifier
     * @param array $parsed Parsed weighting response
     * @return WeightResult
     */
    private function buildWeightResult(string $evaluationId, array $parsed): WeightResult
    {
        return new WeightResult(
            $evaluationId,
            $parsed['weights'],
            $this->normalizer->normalize($parsed['weights']),
            $parsed['explanations'] ?? [],
            $parsed['overall_rationale'] ?? '',
            $parsed['factor_analysis'] ?? [],
            $parsed['bounds'] ?? null,
            false, // not fallback
            null
        );
    }

    /**
     * Log an adaptive-weighting cache hit to the weighting log (debug-gated via debugLog()).
     *
     * @param string $evaluationId Evaluation identifier
     * @param float $duration Time spent before the cache short-circuit (seconds)
     * @return void
     */
    private function logWeightCacheHit(string $evaluationId, float $duration): void
    {
        $message = sprintf(
            "[%s] [INFO] CACHE HIT - Evaluation: %s | Duration: %.3fs (LLM weighting skipped)\n",
            date('Y-m-d H:i:s'),
            $evaluationId,
            $duration
        );

        $this->debugLog($message);
    }

    /**
     * Write a verbose trace line to the adaptive-weighting log, only when debug is enabled.
     *
     * Centralizes the debug gate for every diagnostic written to {@see $errorLogPath} (SUCCESS,
     * FALLBACK, retries, bounds/anomaly/critic traces, cache hits). Production-critical signals are
     * NOT routed here: alerts still reach the structured log via AlertManager::triggerAlert(), and
     * counters/fallback tracking are unaffected.
     *
     * @param string $message Pre-formatted log line (typically ending in "\n")
     * @return void
     */
    private function debugLog(string $message): void
    {
        if (!$this->debug) {
            return;
        }

        error_log($message, 3, $this->errorLogPath);
    }

    /**
     * Call the LLM and parse its JSON response, regenerating on a parse failure.
     *
     * {@see WeightingLlmClient::callLLMWithRetry} already covers transport failures; this adds resilience to a
     * successful call that returns malformed JSON by regenerating up to {@see $maxParseRetries}
     * times before letting the error propagate to the static-weighting fallback.
     *
     * @param string $prompt Structured prompt for the LLM
     * @param array $expectedCriticIds Expected critic IDs (for validation)
     * @return array Parsed response
     * @throws \RuntimeException If parsing still fails after the allowed regenerations
     */
    private function callAndParseWithRetry(string $prompt, array $expectedCriticIds): array
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->maxParseRetries) {
            $llmResponse = $this->llmClient->callLLMWithRetry($prompt);

            try {
                return $this->parseLLMResponse($llmResponse, $expectedCriticIds);
            } catch (\RuntimeException $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt <= $this->maxParseRetries) {
                    $this->llmClient->logRetryAttempt($attempt, $this->maxParseRetries, 'parse failure: ' . $e->getMessage());
                }
            }
        }

        throw $lastException;
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
            // Lenient repair of common, model-agnostic JSON defects (trailing commas, stray control
            // characters) before giving up — avoids a regeneration/fallback for a trivial blemish.
            $data = json_decode($this->llmClient->repairJson($jsonString), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON in LLM response: ' . json_last_error_msg());
            }
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
            "[%s] [ERROR] Evaluation: %s | Duration: %.3fs\n" .
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
        $this->debugLog($errorMessage);
        
        // Also log to standard error log for visibility (debug-gated)
        if ($this->debug) {
            error_log("[ERROR] LLMWeightingEngine error for evaluation {$evaluationId}: " . $exception->getMessage());
        }
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
            "[%s] [INFO] SUCCESS - Evaluation: %s | Duration: %.3fs\n",
            $timestamp,
            $evaluationId,
            $duration
        );
        
        $this->debugLog($message);
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
            "[%s] [WARNING] FALLBACK - Evaluation: %s | Reason: %s\n",
            $timestamp,
            $evaluationId,
            $reason
        );
        
        $this->debugLog($message);
        if ($this->debug) {
            error_log("[WARNING] Falling back to static weighting for evaluation {$evaluationId}: {$reason}");
        }
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
            error_log("[ERROR] Failed to increment total count: " . $e->getMessage());
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
            error_log("[ERROR] Failed to increment fallback count: " . $e->getMessage());
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
            error_log("[ERROR] Failed to calculate fallback rate: " . $e->getMessage());
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
            // Require a minimum number of evaluations before alerting: on a tiny window a single
            // early failure reads as a 100% fallback rate and trips the threshold spuriously.
            $totalCached = $this->cache->getCachedResponse(self::CACHE_KEY_TOTAL_COUNT);
            $total = $totalCached !== null ? (int)$totalCached : 0;

            if ($total < $this->minSampleForAlert) {
                return;
            }

            $rate = $this->getFallbackRate();

            if ($rate > $this->fallbackAlertThreshold) {
                $this->generateFallbackAlert($rate);
            }
        } catch (\Exception $e) {
            error_log("[ERROR] Failed to check fallback rate: " . $e->getMessage());
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
                "[%s] [WARNING] ALERT - High fallback rate: %.2f%% (threshold: %.2f%%)\n",
                $timestamp,
                $percentage,
                $threshold
            );
            $this->debugLog($message);
            
        } catch (\Exception $e) {
            error_log("[ERROR] Failed to generate fallback alert: " . $e->getMessage());
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
            $message = sprintf("[%s] [INFO] Fallback tracking reset\n", $timestamp);
            $this->debugLog($message);
        } catch (\Exception $e) {
            error_log("[ERROR] Failed to reset fallback tracking: " . $e->getMessage());
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
            error_log("[ERROR] Failed to get fallback stats: " . $e->getMessage());
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
}
