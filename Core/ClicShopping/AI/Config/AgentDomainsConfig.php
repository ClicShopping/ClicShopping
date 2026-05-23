<?php
/**
 * Agent Domains Configuration
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * Provides configuration management for the Agent Domains (AD) module.
 * Controls domain-specific agent activation and multi-domain support.
 *
 * @package ClicShopping\AI\Config
 * @version 1.0.0
 * @since 2026-02-08
 */

namespace ClicShopping\AI\Config;

/**
 * AgentDomainsConfig Class
 *
 * Configuration management for Agent Domains module.
 * Provides feature flag control for domain-specific agent operations.
 *
 * Features:
 * - Enable/disable Agent Domains module
 * - Control domain-specific agent activation
 * - Support multiple business domains (Ecommerce, Hr, Finance, Trading)
 * - Domain isolation and permission management
 *
 * Usage:
 * ```php
 * // Check if Agent Domains is enabled
 * if (AgentDomainsConfig::isEnabled()) {
 *     // Check if specific domain is enabled
 *     if (AgentDomainsConfig::isDomainsEnabled()) {
 *         // Domain-specific logic
 *     }
 * }
 *
 * // Get sort order
 * $sortOrder = AgentDomainsConfig::getSortOrder();
 * ```
 */
class AgentDomainsConfig
{
    private static ?array $config = null;
    private static bool $debug = false;
    
    /**
     * Configuration constant names (from AD module)
     */
    private const CONST_STATUS = 'CLICSHOPPING_APP_CHATGPT_AD_STATUS';
    private const CONST_DOMAINS_STATUS = 'CLICSHOPPING_APP_CHATGPT_AD_DOMAINS_STATUS';
    private const CONST_SORT_ORDER = 'CLICSHOPPING_APP_CHATGPT_AD_SORT_ORDER';
    
    /**
     * Default configuration values (fallback if module not installed)
     */
    private const DEFAULTS = [
        'status' => false, // Disabled by default until module is installed
        'domains_status' => false, // Domain-specific agents disabled by default
        'sort_order' => 600
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
        
        self::$debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && 
                       CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
        
        // Load configuration from constants or use defaults
        self::$config = self::loadConfigFromConstants();
        
        if (self::$debug) {
            error_log("AgentDomainsConfig: Initialized with enabled=" . 
                     (self::$config['status'] ? 'true' : 'false') . 
                     ", domains_enabled=" . (self::$config['domains_status'] ? 'true' : 'false'));
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
        
        if (\defined(self::CONST_DOMAINS_STATUS)) {
            $config['domains_status'] = self::parseBool(constant(self::CONST_DOMAINS_STATUS));
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
     * Check if Agent Domains module is enabled
     *
     * @return bool True if enabled, false otherwise
     */
    public static function isEnabled(): bool
    {
        self::initialize();
        return self::$config['status'] ?? false;
    }
    
    /**
     * Check if domain-specific agents are enabled
     *
     * Controls whether agents are activated for specific business domains
     * (e.g., Ecommerce, Hr, Finance, Trading).
     *
     * @return bool True if domain agents are enabled, false otherwise
     */
    public static function isDomainsEnabled(): bool
    {
        self::initialize();
        return self::$config['domains_status'] ?? false;
    }
    
    /**
     * Get sort order
     *
     * Display order for the module in admin interface.
     *
     * @return int Sort order (default: 600)
     */
    public static function getSortOrder(): int
    {
        self::initialize();
        return self::$config['sort_order'] ?? 600;
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
            'domains_enabled' => self::$config['domains_status'],
            'module_installed' => \defined(self::CONST_STATUS),
            'configuration' => self::$config,
            'constants_defined' => [
                'STATUS' => \defined(self::CONST_STATUS),
                'DOMAINS_STATUS' => \defined(self::CONST_DOMAINS_STATUS),
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
        
        // Validate sort order
        $sortOrder = self::$config['sort_order'];
        if ($sortOrder < 0 || $sortOrder > 9999) {
            $errors[] = "Sort order must be between 0 and 9999, got {$sortOrder}";
        }
        
        // Validate logical consistency
        if (self::$config['domains_status'] && !self::$config['status']) {
            $errors[] = "Domains cannot be enabled when Agent Domains module is disabled";
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
            error_log("AgentDomainsConfig: Configuration reloaded");
        }
    }
}
