<?php
/**
 * Agent Critics Configuration
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * Provides configuration management for the Agent Critics (AC) module.
 * Controls critic agent activation for quality evaluation and validation.
 *
 * @package ClicShopping\AI\Config
 * @version 1.0.0
 * @since 2026-02-08
 */

namespace ClicShopping\AI\Config;

/**
 * AgentCriticsConfig Class
 *
 * Configuration management for Agent Critics module.
 * Provides feature flag control for critic agent operations.
 *
 * Features:
 * - Enable/disable Agent Critics module
 * - Control analytics expert critic activation
 * - Control Ecommerce specialist critic activation
 * - Control security expert critic activation
 * - Control generalist critic activation
 *
 * Usage:
 * ```php
 * // Check if Agent Critics is enabled
 * if (AgentCriticsConfig::isEnabled()) {
 *     // Check specific critics
 *     if (AgentCriticsConfig::isAnalyticsExpertEnabled()) {
 *         // Use analytics expert critic
 *     }
 * }
 * ```
 */
class AgentCriticsConfig
{
    /**
     * Configuration constant names (from AC module)
     */
    private const CONST_STATUS = 'CLICSHOPPING_APP_CHATGPT_AC_STATUS';
    private const CONST_ANALYTICS_EXPERT_STATUS = 'CLICSHOPPING_APP_CHATGPT_AC_ANALYTICS_EXPERT_STATUS';
    private const CONST_SPECIALIST_STATUS = 'CLICSHOPPING_APP_CHATGPT_AC_SPECIALIST_STATUS';
    private const CONST_SECURITY_EXPERT_STATUS = 'CLICSHOPPING_APP_CHATGPT_AC_SECURITY_EXPERT_STATUS';
    private const CONST_GENERALIST_STATUS = 'CLICSHOPPING_APP_CHATGPT_AC_GENERALIST_STATUS';
    private const CONST_SORT_ORDER = 'CLICSHOPPING_APP_CHATGPT_AC_SORT_ORDER';
    /**
     * Default configuration values (fallback if module not installed)
     */
    private const DEFAULTS = [
        'status' => false,
        'analytics_expert_status' => false,
        'specialist_status' => false,
        'security_expert_status' => false,
        'generalist_status' => false,
        'sort_order' => 400
    ];
    private static ?array $config = null;
    private static bool $debug = false;
    
    /**
     * Check if Agent Critics module is enabled
     *
     * @return bool True if enabled, false otherwise
     */
    public static function isEnabled(): bool
    {
        self::initialize();
        return self::$config['status'] ?? false;
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

        self::$config = self::loadConfigFromConstants();

        if (self::$debug) {
            error_log("AgentCriticsConfig: Initialized with enabled=" .
                     (self::$config['status'] ? 'true' : 'false'));
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

        if (\defined(self::CONST_ANALYTICS_EXPERT_STATUS)) {
            $config['analytics_expert_status'] = self::parseBool(constant(self::CONST_ANALYTICS_EXPERT_STATUS));
        }

        if (\defined(self::CONST_SPECIALIST_STATUS)) {
            $config['specialist_status'] = self::parseBool(constant(self::CONST_SPECIALIST_STATUS));
        }

        if (\defined(self::CONST_SECURITY_EXPERT_STATUS)) {
            $config['security_expert_status'] = self::parseBool(constant(self::CONST_SECURITY_EXPERT_STATUS));
        }

        if (\defined(self::CONST_GENERALIST_STATUS)) {
            $config['generalist_status'] = self::parseBool(constant(self::CONST_GENERALIST_STATUS));
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
     * Check if analytics expert critic is enabled
     *
     * @return bool True if analytics expert critic is enabled
     */
    public static function isAnalyticsExpertEnabled(): bool
    {
        self::initialize();
        return self::$config['analytics_expert_status'] ?? false;
    }
    
    /**
     * Check if Ecommerce specialist critic is enabled
     *
     * @return bool True if specialist critic is enabled
     */
    public static function isSpecialistEnabled(): bool
    {
        self::initialize();
        return self::$config['specialist_status'] ?? false;
    }
    
    /**
     * Check if security expert critic is enabled
     *
     * @return bool True if security expert critic is enabled
     */
    public static function isSecurityExpertEnabled(): bool
    {
        self::initialize();
        return self::$config['security_expert_status'] ?? false;
    }
    
    /**
     * Check if generalist critic is enabled
     *
     * @return bool True if generalist critic is enabled
     */
    public static function isGeneralistEnabled(): bool
    {
        self::initialize();
        return self::$config['generalist_status'] ?? false;
    }
    
    /**
     * Get sort order
     *
     * @return int Sort order (default: 400)
     */
    public static function getSortOrder(): int
    {
        self::initialize();
        return self::$config['sort_order'] ?? 400;
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
            'critics' => [
                'analytics_expert' => self::$config['analytics_expert_status'],
                'specialist' => self::$config['specialist_status'],
                'security_expert' => self::$config['security_expert_status'],
                'generalist' => self::$config['generalist_status']
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
            error_log("AgentCriticsConfig: Configuration reloaded");
        }
    }
}
