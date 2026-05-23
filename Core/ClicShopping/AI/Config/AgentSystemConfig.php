<?php
/**
 * Agent System Configuration
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * Provides configuration management for the Agent System (ASY) module.
 * Controls Actor-Critic system, Adaptive Weighting, Reputation System, and WebSearch global settings.
 *
 * @package ClicShopping\AI\Config
 * @version 1.0.0
 * @since 2026-02-08
 */

namespace ClicShopping\AI\Config;

/**
 * AgentSystemConfig Class
 *
 * Configuration management for Agent System module.
 * Provides feature flag control and configuration parameters for agent system operations.
 *
 * Features:
 * - Enable/disable Agent System module
 * - Control Actor-Critic system activation
 * - Control Adaptive Weighting system
 * - Control Reputation System
 * - Control WebSearch global availability
 *
 * Usage:
 * ```php
 * // Check if Agent System is enabled
 * if (AgentSystemConfig::isEnabled()) {
 *     $actorCriticEnabled = AgentSystemConfig::isActorCriticEnabled();
 *     $adaptiveWeightingEnabled = AgentSystemConfig::isAdaptiveWeightingEnabled();
 * }
 *
 * // Check if WebSearch is globally enabled
 * if (AgentSystemConfig::isWebSearchGloballyEnabled()) {
 *     // Allow web search operations
 * }
 * ```
 */
class AgentSystemConfig
{
    private static ?array $config = null;
    private static bool $debug = false;
    
    /**
     * Configuration constant names (from ASY module)
     */
    private const CONST_STATUS = 'CLICSHOPPING_APP_CHATGPT_ASY_ACTOR_SYSTEM_STATUS';
    private const CONST_WEBSEARCH_GLOBAL_STATUS = 'CLICSHOPPING_APP_CHATGPT_ASY_WEBSEARCH_GLOBAL_STATUS';
    private const CONST_ADAPTIVE_WEIGHTING_STATUS = 'CLICSHOPPING_APP_CHATGPT_ASY_ADAPTIVE_WEIGHTING_STATUS';
    private const CONST_REPUTATION_SYSTEM_STATUS = 'CLICSHOPPING_APP_CHATGPT_ASY_REPUTATION_SYSTEM_STATUS';
    private const CONST_SORT_ORDER = 'CLICSHOPPING_APP_CHATGPT_ASY_SORT_ORDER';
    
    /**
     * Default configuration values (fallback if module not installed)
     */
    private const DEFAULTS = [
        'status' => false, // Disabled by default until module is installed
        'websearch_global_status' => false, // WebSearch disabled by default
        'adaptive_weighting_status' => true, // Adaptive weighting enabled by default
        'reputation_system_status' => true, // Reputation system enabled by default
        'sort_order' => 200
    ];
    
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
        
        self::$debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') &&  CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
        
        // Load configuration from constants or use defaults
        self::$config = self::loadConfigFromConstants();
        
        if (self::$debug) {
            error_log("AgentSystemConfig: Initialized with enabled=" .   (self::$config['status'] ? 'true' : 'false') .  ", websearch_global=" . (self::$config['websearch_global_status'] ? 'true' : 'false'));
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
        
        if (\defined(self::CONST_WEBSEARCH_GLOBAL_STATUS)) {
            $config['websearch_global_status'] = self::parseBool(constant(self::CONST_WEBSEARCH_GLOBAL_STATUS));
        }
        
        if (\defined(self::CONST_ADAPTIVE_WEIGHTING_STATUS)) {
            $config['adaptive_weighting_status'] = self::parseBool(constant(self::CONST_ADAPTIVE_WEIGHTING_STATUS));
        }
        
        if (\defined(self::CONST_REPUTATION_SYSTEM_STATUS)) {
            $config['reputation_system_status'] = self::parseBool(constant(self::CONST_REPUTATION_SYSTEM_STATUS));
        }
        
        if (\defined(self::CONST_SORT_ORDER)) {
            $config['sort_order'] = (int)constant(self::CONST_SORT_ORDER);
        }
        
        return $config;
    }

    /**
     * Parse bool values from constants safely (handles 'True'/'False', 1/0, yes/no).
     *
     * @param mixed $value Raw constant value
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
        return \in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
    
    /**
     * Check if Agent System module is enabled
     *
     * @return bool True if enabled, false otherwise
     */
    public static function isEnabled(): bool
    {
        self::initialize();
        return self::$config['status'] ?? false;
    }
    
    /**
     * Check if WebSearch is globally enabled
     *
     * When disabled, no agents can perform web searches regardless of individual settings.
     * Useful for internal-only deployments.
     *
     * @return bool True if WebSearch is globally enabled
     */
    public static function isWebSearchGloballyEnabled(): bool
    {
        self::initialize();
        return self::$config['websearch_global_status'] ?? false;
    }
    
    /**
     * Check if Adaptive Weighting is enabled
     *
     * When enabled, critic weights are dynamically adjusted based on performance.
     *
     * @return bool True if Adaptive Weighting is enabled
     */
    public static function isAdaptiveWeightingEnabled(): bool
    {
        self::initialize();
        return self::$config['adaptive_weighting_status'] ?? true;
    }
    
    /**
     * Check if Reputation System is enabled
     *
     * When enabled, actors and critics build reputation scores based on performance.
     *
     * @return bool True if Reputation System is enabled
     */
    public static function isReputationSystemEnabled(): bool
    {
        self::initialize();
        return self::$config['reputation_system_status'] ?? true;
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
                'WEBSEARCH_GLOBAL_STATUS' => \defined(self::CONST_WEBSEARCH_GLOBAL_STATUS),
                'ADAPTIVE_WEIGHTING_STATUS' => \defined(self::CONST_ADAPTIVE_WEIGHTING_STATUS),
                'REPUTATION_SYSTEM_STATUS' => \defined(self::CONST_REPUTATION_SYSTEM_STATUS),
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
        
        // All boolean flags - no validation needed beyond type checking
        // Sort order validation
        $sortOrder = self::$config['sort_order'];
        if ($sortOrder < 0 || $sortOrder > 9999) {
            $errors[] = "Sort order must be between 0 and 9999, got {$sortOrder}";
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
            error_log("AgentSystemConfig: Configuration reloaded");
        }
    }
}
