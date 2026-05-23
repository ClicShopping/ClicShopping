<?php
/**
 * Agent Actors Configuration
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * Provides configuration management for the Agent Actors (AA) module.
 * Controls actor agent activation for different query types and reasoning capabilities.
 *
 * @package ClicShopping\AI\Config
 * @version 1.0.0
 * @since 2026-02-08
 */

namespace ClicShopping\AI\Config;

/**
 * AgentActorsConfig Class
 *
 * Configuration management for Agent Actors module.
 * Provides feature flag control for actor agent operations.
 *
 * Features:
 * - Enable/disable Agent Actors module
 * - Control analytics actor activation
 * - Control semantic actor activation
 * - Control validation actor activation
 * - Control web search actor activation
 * - Control reasoning actor activation
 *
 * Usage:
 * ```php
 * // Check if Agent Actors is enabled
 * if (AgentActorsConfig::isEnabled()) {
 *     // Check specific actors
 *     if (AgentActorsConfig::isAnalyticsEnabled()) {
 *         // Use analytics actor
 *     }
 *     if (AgentActorsConfig::isSemanticEnabled()) {
 *         // Use semantic actor
 *     }
 * }
 * ```
 */
class AgentActorsConfig
{
    private static ?array $config = null;
    private static bool $debug = false;
    
    /**
     * Configuration constant names (from AA module)
     */
    private const CONST_STATUS = 'CLICSHOPPING_APP_CHATGPT_AA_STATUS';
    private const CONST_ANALYTICS_STATUS = 'CLICSHOPPING_APP_CHATGPT_AA_ANALYTICS_STATUS';
    private const CONST_SEMANTIC_STATUS = 'CLICSHOPPING_APP_CHATGPT_AA_SEMANTIC_STATUS';
    private const CONST_VALIDATION_STATUS = 'CLICSHOPPING_APP_CHATGPT_AA_VALIDATION_STATUS';
    private const CONST_WEBSEARCH_STATUS = 'CLICSHOPPING_APP_CHATGPT_AA_WEBSEARCH_STATUS';
    private const CONST_REASONING_STATUS = 'CLICSHOPPING_APP_CHATGPT_AA_REASONING_STATUS';
    private const CONST_SORT_ORDER = 'CLICSHOPPING_APP_CHATGPT_AA_SORT_ORDER';
    
    /**
     * Default configuration values (fallback if module not installed)
     */
    private const DEFAULTS = [
        'status' => false,
        'analytics_status' => false,
        'semantic_status' => false,
        'validation_status' => false,
        'websearch_status' => false,
        'reasoning_status' => false,
        'sort_order' => 300
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
        
        self::$debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
        
        self::$config = self::loadConfigFromConstants();
        
        if (self::$debug) {
            error_log("AgentActorsConfig: Initialized with enabled=" . (self::$config['status'] ? 'true' : 'false'));
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

      if (\defined(self::CONST_STATUS)) {
          $config['status'] = (constant(self::CONST_STATUS) === 'True');
      }

      if (\defined(self::CONST_ANALYTICS_STATUS)) {
        $config['analytics_status'] = self::parseBool(constant(self::CONST_ANALYTICS_STATUS));
      }

      if (\defined(self::CONST_SEMANTIC_STATUS)) {
        $config['semantic_status'] = self::parseBool(constant(self::CONST_SEMANTIC_STATUS));
      }

      if (\defined(self::CONST_VALIDATION_STATUS)) {
        $config['validation_status'] = self::parseBool(constant(self::CONST_VALIDATION_STATUS));
      }

      if (\defined(self::CONST_WEBSEARCH_STATUS)) {
        $config['websearch_status'] = self::parseBool(constant(self::CONST_WEBSEARCH_STATUS));
      }

      if (\defined(self::CONST_REASONING_STATUS)) {
        $config['reasoning_status'] = self::parseBool(constant(self::CONST_REASONING_STATUS));
      }

      if (\defined(self::CONST_SORT_ORDER)) {
        $config['sort_order'] = (int) constant(self::CONST_SORT_ORDER);
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
     * Check if Agent Actors module is enabled
     *
     * @return bool True if enabled, false otherwise
     */
    public static function isEnabled(): bool
    {
        self::initialize();
        return self::$config['status'] ?? false;
    }
    
    /**
     * Check if analytics actor is enabled
     *
     * @return bool True if analytics actor is enabled
     */
    public static function isAnalyticsEnabled(): bool
    {
        self::initialize();
        return self::$config['analytics_status'] ?? false;
    }
    
    /**
     * Check if semantic actor is enabled
     *
     * @return bool True if semantic actor is enabled
     */
    public static function isSemanticEnabled(): bool
    {
        self::initialize();
        return self::$config['semantic_status'] ?? false;
    }
    
    /**
     * Check if validation actor is enabled
     *
     * @return bool True if validation actor is enabled
     */
    public static function isValidationEnabled(): bool
    {
        self::initialize();
        return self::$config['validation_status'] ?? false;
    }
    
    /**
     * Check if web search actor is enabled
     *
     * @return bool True if web search actor is enabled
     */
    public static function isWebSearchEnabled(): bool
    {
        self::initialize();
        return self::$config['websearch_status'] ?? false;
    }
    
    /**
     * Check if reasoning actor is enabled
     *
     * @return bool True if reasoning actor is enabled
     */
    public static function isReasoningEnabled(): bool
    {
        self::initialize();
        return self::$config['reasoning_status'] ?? false;
    }
    
    /**
     * Get sort order
     *
     * @return int Sort order (default: 300)
     */
    public static function getSortOrder(): int
    {
        self::initialize();
        return self::$config['sort_order'] ?? 300;
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
            'actors' => [
                'analytics' => self::$config['analytics_status'],
                'semantic' => self::$config['semantic_status'],
                'validation' => self::$config['validation_status'],
                'websearch' => self::$config['websearch_status'],
                'reasoning' => self::$config['reasoning_status']
            ],
            'module_installed' => \defined(self::CONST_STATUS),
            'configuration' => self::$config
        ];
    }
    
    /**
     * Reload configuration from constants
     *
     * @return void
     */
    public static function reload(): void
    {
        self::$config = null;
        self::initialize();
        
        if (self::$debug) {
            error_log("AgentActorsConfig: Configuration reloaded");
        }
    }
}
