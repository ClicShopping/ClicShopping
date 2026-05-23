<?php
/**
 * Agent Technical Configuration
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * Provides configuration management for the Agent Technical (AT) module.
 * Controls actor-critic coordination, consensus building, and LLM provider selection.
 *
 * @package ClicShopping\AI\Config
 * @version 1.0.0
 * @since 2026-02-08
 */

namespace ClicShopping\AI\Config;

/**
 * AgentTechnicalConfig Class
 *
 * Configuration management for Agent Technical module.
 * Provides feature flag control and configuration parameters for technical agent operations.
 *
 * Features:
 * - Enable/disable Agent Technical module
 * - Configure coordination timeout
 * - Set maximum critics per evaluation
 * - Configure consensus threshold
 * - Select LLM provider (OpenAI, Ollama, Anthropic, LmStudio)
 * - Configure cache TTL
 *
 * Usage:
 * ```php
 * // Check if Agent Technical is enabled
 * if (AgentTechnicalConfig::isEnabled()) {
 *     $timeout = AgentTechnicalConfig::getCoordinationTimeout();
 *     $provider = AgentTechnicalConfig::getLLMProvider();
 * }
 *
 * // Get consensus threshold
 * $threshold = AgentTechnicalConfig::getConsensusThreshold();
 *
 * // Get max critics
 * $maxCritics = AgentTechnicalConfig::getMaxCritics();
 * ```
 */
class AgentTechnicalConfig
{
    private static ?array $config = null;
    private static bool $debug = false;
    
    /**
     * Configuration constant names (from AT module)
     */
    private const CONST_STATUS = 'CLICSHOPPING_APP_CHATGPT_AT_STATUS';
    private const CONST_COORDINATION_TIMEOUT = 'CLICSHOPPING_APP_CHATGPT_AT_COORDINATION_TIMEOUT';
    private const CONST_MAX_CRITICS = 'CLICSHOPPING_APP_CHATGPT_AT_MAX_CRITICS';
    private const CONST_CONSENSUS_THRESHOLD = 'CLICSHOPPING_APP_CHATGPT_AT_CONSENSUS_THRESHOLD';
    private const CONST_LLM_PROVIDER = 'CLICSHOPPING_APP_CHATGPT_AT_LLM_PROVIDER';
    private const CONST_CACHE_TTL = 'CLICSHOPPING_APP_CHATGPT_AT_CACHE_TTL';
    private const CONST_SORT_ORDER = 'CLICSHOPPING_APP_CHATGPT_AT_SORT_ORDER';
    
    /**
     * Default configuration values (fallback if module not installed)
     */
    private const DEFAULTS = [
        'status' => false, // Disabled by default until module is installed
        'coordination_timeout' => 30, // seconds
        'max_critics' => 5,
        'consensus_threshold' => 0.8, // 0.0 to 1.0
        'llm_provider' => 'openai', // openai, ollama, anthropic, LmStudio
        'cache_ttl' => 3600, // seconds (1 hour)
        'sort_order' => 200
    ];
    
    /**
     * Valid LLM providers
     */
    private const VALID_PROVIDERS = ['openai', 'ollama', 'anthropic', 'LmStudio'];
    
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
        
        // Load configuration from constants or use defaults
        self::$config = self::loadConfigFromConstants();
        
        if (self::$debug) {
            error_log("AgentTechnicalConfig: Initialized with enabled=" . (self::$config['status'] ? 'true' : 'false') . ", provider=" . self::$config['llm_provider']);
        }
    }
    
    /**
     * Load configuration from defined constants
     *
     * @return array Configuration array
     */
    private static function loadConfigFromConstants(): array
    {
        $config = self::DEFAULTS;
        
        // Load from constants if defined
        if (\defined(self::CONST_STATUS)) {
            $config['status'] = (constant(self::CONST_STATUS) === 'True');
        }
        
        if (\defined(self::CONST_COORDINATION_TIMEOUT)) {
            $config['coordination_timeout'] = (int)constant(self::CONST_COORDINATION_TIMEOUT);
        }
        
        if (\defined(self::CONST_MAX_CRITICS)) {
            $config['max_critics'] = (int)constant(self::CONST_MAX_CRITICS);
        }
        
        if (\defined(self::CONST_CONSENSUS_THRESHOLD)) {
            $config['consensus_threshold'] = (float)constant(self::CONST_CONSENSUS_THRESHOLD);
        }
        
        if (\defined(self::CONST_LLM_PROVIDER)) {
            $provider = constant(self::CONST_LLM_PROVIDER);
            if (in_array($provider, self::VALID_PROVIDERS, true)) {
                $config['llm_provider'] = $provider;
            }
        }
        
        if (\defined(self::CONST_CACHE_TTL)) {
            $config['cache_ttl'] = (int)constant(self::CONST_CACHE_TTL);
        }
        
        if (\defined(self::CONST_SORT_ORDER)) {
            $config['sort_order'] = (int)constant(self::CONST_SORT_ORDER);
        }
        
        return $config;
    }
    
    /**
     * Check if Agent Technical module is enabled
     *
     * @return bool True if enabled, false otherwise
     */
    public static function isEnabled(): bool
    {
        self::initialize();
        return self::$config['status'] ?? false;
    }
    
    /**
     * Get coordination timeout in seconds
     *
     * Maximum time for actor-critic coordination.
     * If exceeded, returns best available response.
     *
     * @return int Timeout in seconds (default: 30)
     */
    public static function getCoordinationTimeout(): int
    {
        self::initialize();
        return self::$config['coordination_timeout'] ?? 30;
    }
    
    /**
     * Get maximum critics per evaluation
     *
     * Maximum number of critics that can evaluate a single action.
     * More critics = better evaluation but higher cost.
     *
     * @return int Maximum critics (default: 5)
     */
    public static function getMaxCritics(): int
    {
        self::initialize();
        return self::$config['max_critics'] ?? 5;
    }
    
    /**
     * Get consensus threshold
     *
     * Minimum weighted score (0.0 to 1.0) required for consensus.
     * Higher threshold = stricter quality requirements.
     *
     * @return float Consensus threshold (default: 0.8)
     */
    public static function getConsensusThreshold(): float
    {
        self::initialize();
        return self::$config['consensus_threshold'] ?? 0.8;
    }
    
    /**
     * Get LLM provider
     *
     * Returns the configured LLM provider for agent operations.
     * Valid values: openai, ollama, anthropic, LmStudio
     *
     * @return string LLM provider (default: 'openai')
     */
    public static function getLLMProvider(): string
    {
        self::initialize();
        return self::$config['llm_provider'] ?? 'openai';
    }
    
    /**
     * Get cache TTL in seconds
     *
     * Cache time-to-live for agent responses.
     * Longer TTL = lower API costs but potentially stale data.
     *
     * @return int Cache TTL in seconds (default: 3600 = 1 hour)
     */
    public static function getCacheTTL(): int
    {
        self::initialize();
        return self::$config['cache_ttl'] ?? 3600;
    }
    
    /**
     * Get sort order
     *
     * Display order for the module in admin interface.
     *
     * @return int Sort order (default: 200)
     */
    public static function getSortOrder(): int
    {
        self::initialize();
        return self::$config['sort_order'] ?? 200;
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
     * Check if a specific LLM provider is configured
     *
     * @param string $provider Provider name (openai, ollama, anthropic, LmStudio)
     * @return bool True if this provider is configured
     */
    public static function isProvider(string $provider): bool
    {
        self::initialize();
        return strcasecmp(self::$config['llm_provider'], $provider) === 0;
    }
    
    /**
     * Check if OpenAI is the configured provider
     *
     * @return bool True if OpenAI is configured
     */
    public static function isOpenAI(): bool
    {
        return self::isProvider('openai');
    }
    
    /**
     * Check if Ollama is the configured provider
     *
     * @return bool True if Ollama is configured
     */
    public static function isOllama(): bool
    {
        return self::isProvider('ollama');
    }
    
    /**
     * Check if Anthropic is the configured provider
     *
     * @return bool True if Anthropic is configured
     */
    public static function isAnthropic(): bool
    {
        return self::isProvider('anthropic');
    }
    
    /**
     * Check if LmStudio is the configured provider
     *
     * @return bool True if LmStudio is configured
     */
    public static function isLmStudio(): bool
    {
        return self::isProvider('LmStudio');
    }
    
    /**
     * Get configuration status for debugging
     *
     * @return array Status information
     */
    public static function getStatus(): array
    {
        self::initialize();
        
        return [
            'enabled' => self::$config['status'],
            'module_installed' => \defined(self::CONST_STATUS),
            'configuration' => self::$config,
            'constants_defined' => [
                'STATUS' => \defined(self::CONST_STATUS),
                'COORDINATION_TIMEOUT' => \defined(self::CONST_COORDINATION_TIMEOUT),
                'MAX_CRITICS' => \defined(self::CONST_MAX_CRITICS),
                'CONSENSUS_THRESHOLD' => \defined(self::CONST_CONSENSUS_THRESHOLD),
                'LLM_PROVIDER' => \defined(self::CONST_LLM_PROVIDER),
                'CACHE_TTL' => \defined(self::CONST_CACHE_TTL),
                'SORT_ORDER' => \defined(self::CONST_SORT_ORDER)
            ]
        ];
    }
    
    /**
     * Validate configuration
     *
     * @return array Validation result with keys: valid (bool), errors (array)
     */
    public static function validate(): array
    {
        self::initialize();
        
        $errors = [];
        
        // Validate coordination timeout
        $timeout = self::$config['coordination_timeout'];
        if ($timeout < 10 || $timeout > 120) {
            $errors[] = "Coordination timeout must be between 10 and 120 seconds, got {$timeout}";
        }
        
        // Validate max critics
        $maxCritics = self::$config['max_critics'];
        if ($maxCritics < 2 || $maxCritics > 10) {
            $errors[] = "Max critics must be between 2 and 10, got {$maxCritics}";
        }
        
        // Validate consensus threshold
        $threshold = self::$config['consensus_threshold'];
        if ($threshold < 0.0 || $threshold > 1.0) {
            $errors[] = "Consensus threshold must be between 0.0 and 1.0, got {$threshold}";
        }
        
        // Validate LLM provider
        $provider = self::$config['llm_provider'];
        if (!in_array($provider, self::VALID_PROVIDERS, true)) {
            $errors[] = "Invalid LLM provider: {$provider}. Valid: " . implode(', ', self::VALID_PROVIDERS);
        }
        
        // Validate cache TTL
        $cacheTTL = self::$config['cache_ttl'];
        if ($cacheTTL < 60 || $cacheTTL > 86400) {
            $errors[] = "Cache TTL must be between 60 and 86400 seconds, got {$cacheTTL}";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Reload configuration from constants
     *
     * Useful after module installation or configuration changes.
     *
     * @return void
     */
    public static function reload(): void
    {
        self::$config = null;
        self::initialize();
        
        if (self::$debug) {
            error_log("AgentTechnicalConfig: Configuration reloaded");
        }
    }
}
