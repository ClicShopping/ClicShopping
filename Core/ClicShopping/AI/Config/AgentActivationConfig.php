<?php
/**
 * Agent Activation Configuration
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * Provides flexible configuration for enabling/disabling individual agents.
 * Supports both actor and critic agents with domain-specific activation.
 *
 * Requirements: 19.5, 23.5, 24.5
 *
 * @package ClicShopping\AI\Config
 * @version 1.0.0
 * @since 2026-01-31
 */

namespace ClicShopping\AI\Config;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;

/**
 * AgentActivationConfig Class
 *
 * Manages activation state for individual agents in the actor-critic system.
 * Provides flexible enable/disable functionality for gradual rollout and testing.
 *
 * Features:
 * - Enable/disable individual actors and critics
 * - Domain-specific agent activation
 * - Persistent configuration in database
 * - Runtime configuration updates
 * - Default activation states
 *
 * Usage:
 * ```php
 * // Check if an agent is enabled
 * if (AgentActivationConfig::isAgentEnabled('analytics_actor')) {
 *   ===> Use analytics actor
 * }
 *
 * // Enable/disable an agent
 * AgentActivationConfig::setAgentEnabled('hybrid_agent', false);
 * AgentActivationConfig::setAgentEnabled('analytics_actor', true);
 *
 * // Get all enabled agents
 * $enabledActors = AgentActivationConfig::getEnabledAgents('actor');
 * ```
 */
class AgentActivationConfig
{
    /**
     * Agent types
     */
    private const AGENT_TYPE_ACTOR = 'actor';
    private const AGENT_TYPE_CRITIC = 'critic';
    private const AGENT_TYPE_HYBRID = 'hybrid';
    private const AGENT_DOMAIN = 'Ecommerce';
    /**
     * Default agent activation states
     *
     * Format: 'agent_id' => ['enabled' => bool, 'type' => string, 'domain' => string|null]
     */
    private const DEFAULT_AGENTS = [
        // Hybrid agents (legacy)
        'hybrid_agent' => [
            'enabled' => true,
            'type' => self::AGENT_TYPE_HYBRID,
            'domain' => null,
            'description' => 'Legacy hybrid agent (actor + critic)'
        ],

        // Actor agents
        'analytics_actor' => [
            'enabled' => true,
            'type' => self::AGENT_TYPE_ACTOR,
            'domain' => self::AGENT_DOMAIN,
            'description' => 'Analytics SQL generation and execution'
        ],
        'reasoning_actor' => [
            'enabled' => true,
            'type' => self::AGENT_TYPE_ACTOR,
            'domain' => null,
            'description' => 'Logical reasoning and inference'
        ],
        'validation_actor' => [
            'enabled' => true,
            'type' => self::AGENT_TYPE_ACTOR,
            'domain' => null,
            'description' => 'Data validation and verification'
        ],
        'semantic_actor' => [
            'enabled' => true,
            'type' => self::AGENT_TYPE_ACTOR,
            'domain' => self::AGENT_DOMAIN,
            'description' => 'Semantic search and retrieval'
        ],
        'websearch_actor' => [
            'enabled' => true,
            'type' => self::AGENT_TYPE_ACTOR,
            'domain' => null,
            'description' => 'Web search and external data retrieval'
        ],

        // Critic agents
        'analytics_critic' => [
            'enabled' => true,
            'type' => self::AGENT_TYPE_CRITIC,
            'domain' => self::AGENT_DOMAIN,
            'description' => 'SQL quality and performance evaluation'
        ],
        'reasoning_critic' => [
            'enabled' => true,
            'type' => self::AGENT_TYPE_CRITIC,
            'domain' => null,
            'description' => 'Logic soundness evaluation'
        ],
        'validation_critic' => [
            'enabled' => true,
            'type' => self::AGENT_TYPE_CRITIC,
            'domain' => null,
            'description' => 'Validation correctness evaluation'
        ],
        'semantic_critic' => [
            'enabled' => true,
            'type' => self::AGENT_TYPE_CRITIC,
            'domain' => self::AGENT_DOMAIN,
            'description' => 'Semantic relevance evaluation'
        ],
    ];
    private static ?array $config = null;
    private static bool $debug = false;
    
    /**
     * Check if an agent is enabled
     *
     * Requirements: 19.5
     *
     * @param string $agentId Agent identifier
     * @return bool True if enabled, false otherwise
     */
    public static function isAgentEnabled(string $agentId): bool
    {
        self::initialize();
        return self::$config[$agentId]['enabled'] ?? false;
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
            $enabledCount = count(array_filter(self::$config, fn($a) => $a['enabled']));
            error_log("AgentActivationConfig: Initialized with {$enabledCount} enabled agents");
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

            $sql = "SELECT agent_id, 
                           enabled, 
                           agent_type, 
                           domain, 
                          description 
                    FROM :table_rag_agent_activation_config";

            $result = $db->query($sql);

            $config = [];

            while ($row = $result->fetch()) {
                $config[$row['agent_id']] = [
                    'enabled' => self::parseBool($row['enabled']),
                    'type' => $row['agent_type'],
                    'domain' => $row['domain'],
                    'description' => $row['description']
                ];
            }

            // Merge with defaults for any missing agents
            foreach (self::DEFAULT_AGENTS as $agentId => $defaultConfig) {
                if (!isset($config[$agentId])) {
                    $config[$agentId] = $defaultConfig;
                }
            }

            return $config;

        } catch (\Exception $e) {
            if (self::$debug) {
                error_log("AgentActivationConfig: Failed to load from database, using defaults - " . $e->getMessage());
            }
            return self::DEFAULT_AGENTS;
        }
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
     * Enable an agent
     *
     * Requirements: 19.5
     *
     * @param string $agentId Agent identifier
     * @return bool True if successful
     */
    public static function enableAgent(string $agentId): bool
    {
        return self::setAgentEnabled($agentId, true);
    }
    
    /**
     * Set agent enabled state
     *
     * Requirements: 19.5
     *
     * @param string $agentId Agent identifier
     * @param bool $enabled Enabled state
     * @return bool True if successful
     */
    public static function setAgentEnabled(string $agentId, bool $enabled): bool
    {
        self::initialize();

        // Validate agent exists
        if (!isset(self::$config[$agentId]) && !isset(self::DEFAULT_AGENTS[$agentId])) {
            if (self::$debug) {
                error_log("AgentActivationConfig: Unknown agent ID: {$agentId}");
            }
            return false;
        }

        try {
            $db = Registry::get('Db');

            // Get agent info
            $agentInfo = self::$config[$agentId] ?? self::DEFAULT_AGENTS[$agentId];

            // Update in database
            $sql = "INSERT INTO :table_rag_agent_activation_config 
                    (agent_id, enabled, agent_type, domain, description, updated_at) 
                    VALUES (:agent_id, :enabled, :agent_type, :domain, :description, NOW()) 
                    ON DUPLICATE KEY UPDATE 
                        enabled = VALUES(enabled), 
                        updated_at = NOW()";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':agent_id', $agentId);
            $stmt->bindValue(':enabled', (int)$enabled);
            $stmt->bindValue(':agent_type', $agentInfo['type']);
            $stmt->bindValue(':domain', $agentInfo['domain']);
            $stmt->bindValue(':description', $agentInfo['description'] ?? '');
            $stmt->execute();

            // Update in memory
            if (!isset(self::$config[$agentId])) {
                self::$config[$agentId] = $agentInfo;
            }

            self::$config[$agentId]['enabled'] = $enabled;

            if (self::$debug) {
                $state = $enabled ? 'enabled' : 'disabled';
                error_log("AgentActivationConfig: Agent {$agentId} {$state}");
            }

            return true;

        } catch (\Exception $e) {
            if (self::$debug) {
                error_log("AgentActivationConfig: Failed to set agent state - " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Disable an agent
     *
     * Requirements: 19.5
     *
     * @param string $agentId Agent identifier
     * @return bool True if successful
     */
    public static function disableAgent(string $agentId): bool
    {
        return self::setAgentEnabled($agentId, false);
    }
    
    /**
     * Get all enabled agents
     *
     * @param string|null $type Filter by agent type ('actor', 'critic', 'hybrid', or null for all)
     * @param string|null $domain Filter by domain (null for all)
     * @return array Array of enabled agent IDs
     */
    public static function getEnabledAgents(?string $type = null, ?string $domain = null): array
    {
        self::initialize();
        
        $normalizedDomain = self::normalizeDomain($domain);
        $enabled = [];
        foreach (self::$config as $agentId => $config) {
            if (!$config['enabled']) {
                continue;
            }
            
            if ($type !== null && $config['type'] !== $type) {
                continue;
            }
            
            if ($normalizedDomain !== null) {
                $configDomain = self::normalizeDomain($config['domain'] ?? null);
                if ($configDomain !== $normalizedDomain) {
                    continue;
                }
            }
            
            $enabled[] = $agentId;
        }
        
        return $enabled;
    }
    
    /**
     * Get all agents with their configuration
     *
     * @return array All agents configuration
     */
    public static function getAllAgents(): array
    {
        self::initialize();
        return self::$config;
    }
    
    /**
     * Get agent configuration
     *
     * @param string $agentId Agent identifier
     * @return array|null Agent configuration or null if not found
     */
    public static function getAgentConfig(string $agentId): ?array
    {
        self::initialize();
        return self::$config[$agentId] ?? null;
    }
    
    /**
     * Register a new agent
     *
     * @param string $agentId Agent identifier
     * @param string $type Agent type ('actor', 'critic', 'hybrid')
     * @param string|null $domain Domain specialization (null for general)
     * @param string $description Agent description
     * @param bool $enabled Initial enabled state
     * @return bool True if successful
     */
    public static function registerAgent (
        string $agentId,
        string $type,
        ?string $domain = null,
        string $description = '',
        bool $enabled = true
    ): bool {
        self::initialize();
        
        // Validate type
        if (!in_array($type, [self::AGENT_TYPE_ACTOR, self::AGENT_TYPE_CRITIC, self::AGENT_TYPE_HYBRID], true)) {
            if (self::$debug) {
                error_log("AgentActivationConfig: Invalid agent type: {$type}");
            }
            return false;
        }
        
        try {
            $db = Registry::get('Db');

            $sql = "INSERT INTO :table_rag_agent_activation_config 
                    (agent_id, enabled, agent_type, domain, description, created_at, updated_at) 
                    VALUES (:agent_id, :enabled, :agent_type, :domain, :description, NOW(), NOW()) 
                    ON DUPLICATE KEY UPDATE 
                        agent_type = VALUES(agent_type),
                        domain = VALUES(domain),
                        description = VALUES(description),
                        updated_at = NOW()";
            
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':agent_id', $agentId);
            $stmt->bindValue(':enabled', (int)$enabled);
            $stmt->bindValue(':agent_type', $type);
            $stmt->bindValue(':domain', $domain);
            $stmt->bindValue(':description', $description);
            $stmt->execute();
            
            // Update in memory
            self::$config[$agentId] = [
                'enabled' => $enabled,
                'type' => $type,
                'domain' => $domain,
                'description' => $description
            ];
            
            if (self::$debug) {
                error_log("AgentActivationConfig: Agent registered: {$agentId} ({$type})");
            }
            
            return true;
            
        } catch (\Exception $e) {
            if (self::$debug) {
                error_log("AgentActivationConfig: Failed to register agent - " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Reset all agents to default configuration
     *
     * @return bool True if successful
     */
    public static function resetToDefaults(): bool
    {
        try {
            $db = Registry::get('Db');

            // Delete all configuration
            $sql = "DELETE FROM :table_rag_agent_activation_config";
            $db->query($sql);
            
            // Reset in memory
            self::$config = self::DEFAULT_AGENTS;
            
            if (self::$debug) {
                error_log("AgentActivationConfig: Reset to defaults");
            }
            
            return true;
            
        } catch (\Exception $e) {
            if (self::$debug) {
                error_log("AgentActivationConfig: Failed to reset configuration - " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Get statistics about agent activation
     *
     * @return array Statistics
     */
    public static function getStatistics(): array
    {
        self::initialize();
        
        $stats = [
            'total_agents' => count(self::$config),
            'enabled_agents' => 0,
            'disabled_agents' => 0,
            'by_type' => [
                'actor' => ['total' => 0, 'enabled' => 0],
                'critic' => ['total' => 0, 'enabled' => 0],
                'hybrid' => ['total' => 0, 'enabled' => 0]
            ],
            'by_domain' => []
        ];
        
        foreach (self::$config as $agentId => $config) {
            if ($config['enabled']) {
                $stats['enabled_agents']++;
            } else {
                $stats['disabled_agents']++;
            }
            
            $type = $config['type'];
            $stats['by_type'][$type]['total']++;
            if ($config['enabled']) {
                $stats['by_type'][$type]['enabled']++;
            }
            
            $domain = $config['domain'] ?? 'general';
            if ($domain !== 'general') {
                $domain = DomainFields::getModuleName($domain) ?? $domain;
            }
            if (!isset($stats['by_domain'][$domain])) {
                $stats['by_domain'][$domain] = ['total' => 0, 'enabled' => 0];
            }
            $stats['by_domain'][$domain]['total']++;
            if ($config['enabled']) {
                $stats['by_domain'][$domain]['enabled']++;
            }
        }
        
        return $stats;
    }

    /**
     * Normalize domain string for comparisons.
     *
     * @param string|null $domain
     * @return string|null
     */
    private static function normalizeDomain(?string $domain): ?string
    {
        if ($domain === null) {
            return null;
        }
        $domain = strtolower(trim((string)$domain));
        return $domain === '' ? null : $domain;
    }
}
