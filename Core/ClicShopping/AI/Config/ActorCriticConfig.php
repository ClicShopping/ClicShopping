<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Config;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;

/**
 * ActorCriticConfig Class
 *
 * Configuration management for Actor-Critic separation feature.
 * Provides feature flag control and configuration parameters for gradual rollout.
 *
 * Requirements: 25.5, 30.1, 30.2, 30.3, 30.4, 30.5
 *
 * @package ClicShopping\AI\Config
 * @version 1.0.0
 * @since 2026-01-31
 */
class ActorCriticConfig
{
    /**
     * Configuration keys
     */
    private const CONFIG_KEY_ENABLED = 'actor_critic_enabled';
    private const CONFIG_KEY_CRITICS_PER_EVALUATION = 'critics_per_evaluation';
    private const CONFIG_KEY_MIN_CRITICS_REQUIRED = 'min_critics_required';
    private const CONFIG_KEY_ACTOR_RETRY_ATTEMPTS = 'actor_retry_attempts';
    private const CONFIG_KEY_CRITIC_EVALUATION_TIMEOUT = 'critic_evaluation_timeout';
    private const CONFIG_KEY_CONSENSUS_THRESHOLD = 'consensus_threshold';
    private const CONFIG_KEY_MAX_CONCURRENT_ACTIONS_PER_ACTOR = 'max_concurrent_actions_per_actor';
    private const CONFIG_KEY_MAX_CONCURRENT_EVALUATIONS_PER_CRITIC = 'max_concurrent_evaluations_per_critic';
    private const CONFIG_KEY_FALLBACK_TO_HYBRID = 'fallback_to_hybrid_on_error';
    private const CONFIG_KEY_QUALITY_GATE_REGENERATION = 'quality_gate_regeneration_enabled';
    private const CONFIG_KEY_REPUTATION_DECAY_ENABLED = 'reputation_decay_enabled';
    private const CONFIG_KEY_REPUTATION_DECAY_FACTOR = 'reputation_decay_factor';
    
    // Reputation decay configuration keys
    private const CONFIG_KEY_REPUTATION_DECAY_PERIOD_SECONDS = 'reputation_decay_period_seconds';
    private const CONFIG_KEY_REPUTATION_DECAY_RECENT_EVAL_COUNT = 'reputation_decay_recent_evaluation_count';
    private const CONFIG_KEY_REPUTATION_DECAY_RATE = 'reputation_decay_rate';
    private const CONFIG_KEY_REPUTATION_WEIGHT_CONSENSUS = 'reputation_weight_consensus_alignment';
    
    // Reputation configuration keys (Requirements 13.1-13.5)
    private const CONFIG_KEY_REPUTATION_WEIGHT_FEEDBACK = 'reputation_weight_feedback_quality';
    private const CONFIG_KEY_REPUTATION_WEIGHT_CONSISTENCY = 'reputation_weight_consistency';
    private const CONFIG_KEY_REPUTATION_WEIGHT_EXPERTISE = 'reputation_weight_expertise_accuracy';
    private const CONFIG_KEY_REPUTATION_MIN_THRESHOLD = 'reputation_minimum_threshold';
    private const CONFIG_KEY_REPUTATION_BOOTSTRAP_THRESHOLD_LOW = 'reputation_bootstrapping_threshold_low';
    private const CONFIG_KEY_REPUTATION_BOOTSTRAP_THRESHOLD_HIGH = 'reputation_bootstrapping_threshold_high';
    /**
     * Default configuration values
     */
    private const DEFAULTS = [
        self::CONFIG_KEY_ENABLED => False, // Feature flag - // Disabled by default, must be explicitly enabled in DB for rollout
        self::CONFIG_KEY_CRITICS_PER_EVALUATION => 3,
        self::CONFIG_KEY_MIN_CRITICS_REQUIRED => 2,
        self::CONFIG_KEY_ACTOR_RETRY_ATTEMPTS => 3,
        self::CONFIG_KEY_CRITIC_EVALUATION_TIMEOUT => 30, // seconds
        self::CONFIG_KEY_CONSENSUS_THRESHOLD => 0.15, // max std deviation
        self::CONFIG_KEY_MAX_CONCURRENT_ACTIONS_PER_ACTOR => 5,
        self::CONFIG_KEY_MAX_CONCURRENT_EVALUATIONS_PER_CRITIC => 10,
        self::CONFIG_KEY_FALLBACK_TO_HYBRID => true, // Fallback to hybrid mode on error
        self::CONFIG_KEY_QUALITY_GATE_REGENERATION => false, // Feature flag — regenerate once on quality-gate miss. OFF by default, enable in DB after ai_eval validation.

        // Reputation decay configuration (Requirement 4.2)
        self::CONFIG_KEY_REPUTATION_DECAY_ENABLED => true,
        self::CONFIG_KEY_REPUTATION_DECAY_FACTOR => 0.95,
        self::CONFIG_KEY_REPUTATION_DECAY_PERIOD_SECONDS => 86400, // Daily (24 hours)
        self::CONFIG_KEY_REPUTATION_DECAY_RECENT_EVAL_COUNT => 10,

        // Reputation configuration (Requirements 13.1-13.5)
        self::CONFIG_KEY_REPUTATION_DECAY_RATE => 0.95, // Requirement 13.1
        self::CONFIG_KEY_REPUTATION_WEIGHT_CONSENSUS => 0.4, // Requirement 13.2
        self::CONFIG_KEY_REPUTATION_WEIGHT_FEEDBACK => 0.3, // Requirement 13.2
        self::CONFIG_KEY_REPUTATION_WEIGHT_CONSISTENCY => 0.2, // Requirement 13.2
        self::CONFIG_KEY_REPUTATION_WEIGHT_EXPERTISE => 0.1, // Requirement 13.2
        self::CONFIG_KEY_REPUTATION_MIN_THRESHOLD => 0.5, // Requirement 13.3
        self::CONFIG_KEY_REPUTATION_BOOTSTRAP_THRESHOLD_LOW => 10, // Requirement 13.4
        self::CONFIG_KEY_REPUTATION_BOOTSTRAP_THRESHOLD_HIGH => 50, // Requirement 13.4
    ];
    private static ?array $config = null;
    private static bool $debug = false;
  private static ActorCriticConfig $instance;

  /**
   * Get singleton instance
   *
   * @return ActorCriticConfig Instance
   */
  public static function getInstance(): ActorCriticConfig
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }

    return self::$instance;
  }

    /**
     * Check if Actor-Critic separation is enabled

     * @return bool True if enabled, false otherwise
     */
    public static function isEnabled(): bool
    {
        self::initialize();
        return self::$config[self::CONFIG_KEY_ENABLED] ?? false;
    }

    /**
     * Whether the quality-gate response-regeneration loop is enabled.
     *
     * Feature flag (default false). When on, a result whose consensus score misses
     * AT_CONSENSUS_THRESHOLD is regenerated once with critic feedback; the higher-scoring
     * of the two is kept (never worse than the original).
     */
    public static function isQualityGateRegenerationEnabled(): bool
    {
        self::initialize();
        return self::$config[self::CONFIG_KEY_QUALITY_GATE_REGENERATION] ?? false;
    }

  /**
     * Initialize configuration
     *
     * @return void
     */
    private static function initialize(): void
    {
        if (self::$config !== null) {
            return;
        }

        self::$debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

        // Load configuration from database or use defaults
        self::$config = self::loadConfigFromDatabase();

        if (self::$debug) {
            error_log("ActorCriticConfig: Initialized with enabled=" . (self::$config[self::CONFIG_KEY_ENABLED] ? 'true' : 'false'));
        }
    }

    /**
     * Load configuration from database
     *
     * @return array Configuration array
     */
    private static function loadConfigFromDatabase(): array
    {
        try {
            $db = Registry::get('Db');

            $sql = "SELECT config_key, 
                           config_value 
                    FROM :table_rag_agent_actor_critic_config
                    WHERE config_key LIKE 'actor_critic_%'
                       OR config_key LIKE 'reputation_%'
                       OR config_key = 'quality_gate_regeneration_enabled'";

            $result = $db->query($sql);

            $config = self::DEFAULTS;

            while ($row = $result->fetch()) {
                $key = $row['config_key'];
                $value = $row['config_value'];

              // Type conversion
                if ($key === self::CONFIG_KEY_ENABLED ||
                    $key === self::CONFIG_KEY_FALLBACK_TO_HYBRID ||
                    $key === self::CONFIG_KEY_QUALITY_GATE_REGENERATION ||
                    $key === self::CONFIG_KEY_REPUTATION_DECAY_ENABLED) {
                    $config[$key] = self::parseBool($value);
                } elseif (in_array($key, [
                    self::CONFIG_KEY_CRITICS_PER_EVALUATION,
                    self::CONFIG_KEY_MIN_CRITICS_REQUIRED,
                    self::CONFIG_KEY_ACTOR_RETRY_ATTEMPTS,
                    self::CONFIG_KEY_CRITIC_EVALUATION_TIMEOUT,
                    self::CONFIG_KEY_MAX_CONCURRENT_ACTIONS_PER_ACTOR,
                    self::CONFIG_KEY_MAX_CONCURRENT_EVALUATIONS_PER_CRITIC,
                    self::CONFIG_KEY_REPUTATION_DECAY_PERIOD_SECONDS,
                    self::CONFIG_KEY_REPUTATION_DECAY_RECENT_EVAL_COUNT,
                    self::CONFIG_KEY_REPUTATION_BOOTSTRAP_THRESHOLD_LOW,
                    self::CONFIG_KEY_REPUTATION_BOOTSTRAP_THRESHOLD_HIGH
                ], true)) {
                    $config[$key] = (int)$value;
                } elseif (in_array($key, [
                    self::CONFIG_KEY_CONSENSUS_THRESHOLD,
                    self::CONFIG_KEY_REPUTATION_DECAY_FACTOR,
                    self::CONFIG_KEY_REPUTATION_DECAY_RATE,
                    self::CONFIG_KEY_REPUTATION_WEIGHT_CONSENSUS,
                    self::CONFIG_KEY_REPUTATION_WEIGHT_FEEDBACK,
                    self::CONFIG_KEY_REPUTATION_WEIGHT_CONSISTENCY,
                    self::CONFIG_KEY_REPUTATION_WEIGHT_EXPERTISE,
                    self::CONFIG_KEY_REPUTATION_MIN_THRESHOLD
                ], true)) {
                    $config[$key] = (float)$value;
                }
            }

            return $config;

        } catch (\Exception $e) {
            if (self::$debug) {
                error_log("ActorCriticConfig: Failed to load from database, using defaults - " . $e->getMessage());
            }
            return self::DEFAULTS;
        }
    }
    
    /**
     * Get configuration value

     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value
     */
    public static function get(string $key, $default = null)
    {
        self::initialize();
        return self::$config[$key] ?? $default;
    }
    
    /**
     * Parse bool values safely from DB values ('1'/'0', true/false, 'True'/'False').
     *
     * @param mixed $value Raw DB value
     * @return bool
     */
    private static function parseBool($value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_numeric($value)) {
            return (int)$value === 1;
        }

        $normalized = \strtolower(\trim((string)$value));
        return \in_array($normalized, ['1', 'true', 'yes', 'on'], true,);
    }
    
    /**
     * Enable Actor-Critic separation
     *
     * @return bool True if successful
     */
    public static function enable(): bool
    {
        return self::setConfig(self::CONFIG_KEY_ENABLED, true);
    }
    
    /**
     * Set configuration value
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @return bool True if successful
     */
    public static function setConfig(string $key, $value): bool
    {
        self::initialize();

        if (!array_key_exists($key, self::DEFAULTS)) {
            if (self::$debug) {
                error_log("ActorCriticConfig: Invalid configuration key: {$key}");
            }
            return false;
        }

        // Validate value (Requirement 30.4)
        if (!self::validateValue($key, $value)) {
            if (self::$debug) {
                error_log("ActorCriticConfig: Invalid value for key {$key}: " . var_export($value, true));
            }
            return false;
        }

        try {
            $db = Registry::get('Db');

            // Update in database
            $sql = "INSERT INTO :table_rag_agent_actor_critic_config (config_key, config_value, updated_at) 
                    VALUES (:key, :value, NOW()) 
                    ON DUPLICATE KEY UPDATE config_value = :value, updated_at = NOW()";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':key', $key);
            $stmt->bindValue(':value', (string)$value);
            $stmt->execute();

            // Update in memory
            self::$config[$key] = $value;

            if (self::$debug) {
                error_log("ActorCriticConfig: Updated {$key} = " . var_export($value, true));
            }

            return true;

        } catch (\Exception $e) {
            if (self::$debug) {
                error_log("ActorCriticConfig: Failed to set configuration - " . $e->getMessage());
                error_log("ActorCriticConfig: Stack trace - " . $e->getTraceAsString());
            }
            // Log to stdout for debugging
            echo "Error setting config: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Validate configuration value

     * @param string $key Configuration key
     * @param mixed $value Value to validate
     * @return bool True if valid
     */
    private static function validateValue(string $key, $value): bool
    {
        switch ($key) {
            case self::CONFIG_KEY_ENABLED:
            case self::CONFIG_KEY_FALLBACK_TO_HYBRID:
            case self::CONFIG_KEY_REPUTATION_DECAY_ENABLED:
                return is_bool($value);

            case self::CONFIG_KEY_CRITICS_PER_EVALUATION:
                return is_int($value) && $value >= 2 && $value <= 10;

            case self::CONFIG_KEY_MIN_CRITICS_REQUIRED:
                return is_int($value) && $value >= 2 && $value <= 5;

            case self::CONFIG_KEY_ACTOR_RETRY_ATTEMPTS:
                return is_int($value) && $value >= 1 && $value <= 5;

            case self::CONFIG_KEY_CRITIC_EVALUATION_TIMEOUT:
                return is_int($value) && $value >= 10 && $value <= 120;

            case self::CONFIG_KEY_CONSENSUS_THRESHOLD:
            case self::CONFIG_KEY_REPUTATION_DECAY_FACTOR:
            case self::CONFIG_KEY_REPUTATION_DECAY_RATE:
                return is_float($value) && $value >= 0.0 && $value <= 1.0;

            case self::CONFIG_KEY_REPUTATION_WEIGHT_CONSENSUS:
            case self::CONFIG_KEY_REPUTATION_WEIGHT_FEEDBACK:
            case self::CONFIG_KEY_REPUTATION_WEIGHT_CONSISTENCY:
            case self::CONFIG_KEY_REPUTATION_WEIGHT_EXPERTISE:
            case self::CONFIG_KEY_REPUTATION_MIN_THRESHOLD:
                return is_float($value) && $value >= 0.0 && $value <= 1.0;

            case self::CONFIG_KEY_MAX_CONCURRENT_ACTIONS_PER_ACTOR:
            case self::CONFIG_KEY_MAX_CONCURRENT_EVALUATIONS_PER_CRITIC:
                return is_int($value) && $value >= 1 && $value <= 100;

            case self::CONFIG_KEY_REPUTATION_DECAY_PERIOD_SECONDS:
                return is_int($value) && $value >= 3600 && $value <= 604800; // 1 hour to 1 week

            case self::CONFIG_KEY_REPUTATION_DECAY_RECENT_EVAL_COUNT:
            case self::CONFIG_KEY_REPUTATION_BOOTSTRAP_THRESHOLD_LOW:
            case self::CONFIG_KEY_REPUTATION_BOOTSTRAP_THRESHOLD_HIGH:
                return is_int($value) && $value >= 5 && $value <= 100;

            default:
                return false;
        }
    }
    
    /**
     * Disable Actor-Critic separation
     *
     * @return bool True if successful
     */
    public static function disable(): bool
    {
        return self::setConfig(self::CONFIG_KEY_ENABLED, false);
    }
    
    /**
     * Get all configuration values
     *
     * @return array All configuration values
     */
    public static function getAll(): array
    {
        self::initialize();
        return self::$config;
    }
    
    /**
     * Reset configuration to defaults
     *
     * @return bool True if successful
     */
    public static function resetToDefaults(): bool
    {
        try {
            $db = Registry::get('Db');
            
            // Delete all configuration
            $sql = "DELETE FROM :table_rag_agent_actor_critic_config WHERE config_key LIKE 'actor_critic_%'";
            $db->query($sql);
            
            // Reset in memory
            self::$config = self::DEFAULTS;
            
            if (self::$debug) {
                error_log("ActorCriticConfig: Reset to defaults");
            }
            
            return true;
            
        } catch (\Exception $e) {
            if (self::$debug) {
                error_log("ActorCriticConfig: Failed to reset configuration - " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Check if fallback to hybrid mode is enabled
     *
     * @return bool True if fallback enabled
     */
    public static function shouldFallbackToHybrid(): bool
    {
        self::initialize();
        return self::$config[self::CONFIG_KEY_FALLBACK_TO_HYBRID] ?? true;
    }
    
    /**
     * Get reputation decay configuration
     * @return array Decay configuration with keys: enabled, decay_factor, period_seconds, recent_evaluation_count
     */
    public static function getReputationDecayConfig(): array
    {
        self::initialize();
        return [
            'enabled' => self::$config[self::CONFIG_KEY_REPUTATION_DECAY_ENABLED] ?? true,
            'decay_factor' => self::$config[self::CONFIG_KEY_REPUTATION_DECAY_FACTOR] ?? 0.95,
            'period_seconds' => self::$config[self::CONFIG_KEY_REPUTATION_DECAY_PERIOD_SECONDS] ?? 86400,
            'recent_evaluation_count' => self::$config[self::CONFIG_KEY_REPUTATION_DECAY_RECENT_EVAL_COUNT] ?? 10,
        ];
    }
    
    /**
     * Check if reputation decay is enabled
     * @return bool True if enabled
     */
    public static function isReputationDecayEnabled(): bool
    {
        self::initialize();
        return self::$config[self::CONFIG_KEY_REPUTATION_DECAY_ENABLED] ?? true;
    }
    
    /**
     * Enable reputation decay
     * 
     * @return bool True if successful
     */
    public static function enableReputationDecay(): bool
    {
        return self::setConfig(self::CONFIG_KEY_REPUTATION_DECAY_ENABLED, true);
    }
    
    /**
     * Disable reputation decay
     * 
     * @return bool True if successful
     */
    public static function disableReputationDecay(): bool
    {
        return self::setConfig(self::CONFIG_KEY_REPUTATION_DECAY_ENABLED, false);
    }
    
    /**
     * Set reputation decay rate
     * @param float $rate Decay rate (0.0-1.0)
     * @return bool True if successful
     */
    public static function setReputationDecayRate(float $rate): bool
    {
        return self::setConfig(self::CONFIG_KEY_REPUTATION_DECAY_RATE, $rate);
    }
    
    /**
     * Set reputation weights

     * @param float $consensus Consensus alignment weight
     * @param float $feedback Feedback quality weight
     * @param float $consistency Consistency weight
     * @param float $expertise Expertise accuracy weight
     * @return bool True if successful
     */
    public static function setReputationWeights(float $consensus, float $feedback, float $consistency, float $expertise): bool
    {
        // Validate that weights sum to 1.0 (with small tolerance for floating point)
        $sum = $consensus + $feedback + $consistency + $expertise;
        if (abs($sum - 1.0) > 0.01) {
            if (self::$debug) {
                error_log("ActorCriticConfig: Reputation weights must sum to 1.0, got {$sum}");
            }
            // Still update the values so validation can detect the issue
            self::$config[self::CONFIG_KEY_REPUTATION_WEIGHT_CONSENSUS] = $consensus;
            self::$config[self::CONFIG_KEY_REPUTATION_WEIGHT_FEEDBACK] = $feedback;
            self::$config[self::CONFIG_KEY_REPUTATION_WEIGHT_CONSISTENCY] = $consistency;
            self::$config[self::CONFIG_KEY_REPUTATION_WEIGHT_EXPERTISE] = $expertise;
            return false;
        }

        $success = true;
        $success = $success && self::setConfig(self::CONFIG_KEY_REPUTATION_WEIGHT_CONSENSUS, $consensus);
        $success = $success && self::setConfig(self::CONFIG_KEY_REPUTATION_WEIGHT_FEEDBACK, $feedback);
        $success = $success && self::setConfig(self::CONFIG_KEY_REPUTATION_WEIGHT_CONSISTENCY, $consistency);
        $success = $success && self::setConfig(self::CONFIG_KEY_REPUTATION_WEIGHT_EXPERTISE, $expertise);

        return $success;
    }
    
    /**
     * Set minimum reputation threshold

     * @param float $threshold Minimum threshold (0.0-1.0)
     * @return bool True if successful
     */
    public static function setMinimumReputationThreshold(float $threshold): bool
    {
        return self::setConfig(self::CONFIG_KEY_REPUTATION_MIN_THRESHOLD, $threshold);
    }
    
    /**
     * Set bootstrapping thresholds

     * @param int $low Low threshold for bootstrapping status
     * @param int $high High threshold for established status
     * @return bool True if successful
     */
    public static function setBootstrappingThresholds(int $low, int $high): bool
    {
        // Validate that low < high
        if ($low >= $high) {
            if (self::$debug) {
                error_log("ActorCriticConfig: Low threshold must be less than high threshold");
            }
            // Still update the values so validation can detect the issue
            self::$config[self::CONFIG_KEY_REPUTATION_BOOTSTRAP_THRESHOLD_LOW] = $low;
            self::$config[self::CONFIG_KEY_REPUTATION_BOOTSTRAP_THRESHOLD_HIGH] = $high;
            return false;
        }

        $success = true;
        $success = $success && self::setConfig(self::CONFIG_KEY_REPUTATION_BOOTSTRAP_THRESHOLD_LOW, $low);
        $success = $success && self::setConfig(self::CONFIG_KEY_REPUTATION_BOOTSTRAP_THRESHOLD_HIGH, $high);

        return $success;
    }
    
    /**
     * Validate reputation configuration

     * @return array Validation result with keys: valid (bool), errors (array)
     */
    public static function validateReputationConfig(): array
    {
        self::initialize();

        $errors = [];

        // Validate decay rate
        $decayRate = self::getReputationDecayRate();
        if ($decayRate < 0.0 || $decayRate > 1.0) {
            $errors[] = "Decay rate must be between 0.0 and 1.0, got {$decayRate}";
        }

        // Validate weights sum to 1.0
        $weights = self::getReputationWeights();
        $sum = array_sum($weights);
        if (abs($sum - 1.0) > 0.01) {
            $errors[] = "Reputation weights must sum to 1.0, got {$sum}";
        }

        // Validate minimum threshold
        $minThreshold = self::getMinimumReputationThreshold();
        if ($minThreshold < 0.0 || $minThreshold > 1.0) {
            $errors[] = "Minimum threshold must be between 0.0 and 1.0, got {$minThreshold}";
        }

        // Validate bootstrapping thresholds
        $thresholds = self::getBootstrappingThresholds();
        if ($thresholds['low'] >= $thresholds['high']) {
            $errors[] = "Low threshold ({$thresholds['low']}) must be less than high threshold ({$thresholds['high']})";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
    
    /**
     * Get reputation decay rate

     * @return float Decay rate (default: 0.95)
     */
    public static function getReputationDecayRate(): float
    {
        self::initialize();
        return self::$config[self::CONFIG_KEY_REPUTATION_DECAY_RATE] ?? 0.95;
    }
    
    /**
     * Get reputation weights

     * @return array Weights with keys: consensus_alignment, feedback_quality, consistency, expertise_accuracy
     */
    public static function getReputationWeights(): array
    {
        self::initialize();
        return [
            'consensus_alignment' => self::$config[self::CONFIG_KEY_REPUTATION_WEIGHT_CONSENSUS] ?? 0.4,
            'feedback_quality' => self::$config[self::CONFIG_KEY_REPUTATION_WEIGHT_FEEDBACK] ?? 0.3,
            'consistency' => self::$config[self::CONFIG_KEY_REPUTATION_WEIGHT_CONSISTENCY] ?? 0.2,
            'expertise_accuracy' => self::$config[self::CONFIG_KEY_REPUTATION_WEIGHT_EXPERTISE] ?? 0.1,
        ];
    }
    
    /**
     * Get minimum reputation threshold
 
     * @return float Minimum threshold (default: 0.5)
     */
    public static function getMinimumReputationThreshold(): float
    {
        self::initialize();
        return self::$config[self::CONFIG_KEY_REPUTATION_MIN_THRESHOLD] ?? 0.5;
    }
    
    /**
     * Get bootstrapping thresholds

     * @return array Thresholds with keys: low (default: 10), high (default: 50)
     */
    public static function getBootstrappingThresholds(): array
    {
        self::initialize();
        return [
            'low' => self::$config[self::CONFIG_KEY_REPUTATION_BOOTSTRAP_THRESHOLD_LOW] ?? 10,
            'high' => self::$config[self::CONFIG_KEY_REPUTATION_BOOTSTRAP_THRESHOLD_HIGH] ?? 50,
        ];
    }
}
