<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Config\AgentSystemConfig;
use ClicShopping\AI\Config\AgentTechnicalConfig;

/**
 * CoordinatorConfigLoader
 *
 * Adaptive-weighting configuration-loading concern extracted verbatim from
 * {@see ActorCriticCoordinator}. Reads the file config, merges the module
 * configuration (AgentSystemConfig / AgentTechnicalConfig take precedence — adaptive
 * weighting only enables when BOTH the module and the file allow it), and applies the
 * defaults. Behaviour unchanged.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic
 */
class CoordinatorConfigLoader
{
    private bool $debug;

    /**
     * @param bool $debug Debug logging toggle (inherited from the coordinator)
     */
    public function __construct(bool $debug)
    {
        $this->debug = $debug;
    }

    /**
     * Load adaptive weighting configuration
     *
     * Loads configuration from config file and merges with module configuration.
     * Module configuration (AgentSystemConfig/AgentTechnicalConfig) takes precedence.
     *
     * @return array Configuration array
     */
    public function load(): array
    {
        $configPath = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Apps/Configuration/ChatGpt/config/adaptive_weighting.php';

        $config = [];
        if (file_exists($configPath)) {
            $fileConfig = require $configPath;
            if (is_array($fileConfig)) {
                $config = $fileConfig;
            }
        }

        // Override with AgentSystemConfig (module configuration takes precedence)
        // Only enable adaptive weighting if BOTH module and file config allow it
        $moduleEnabled = AgentSystemConfig::isAdaptiveWeightingEnabled();
        $fileEnabled = $config['ADAPTIVE_WEIGHTING_ENABLED'] ?? false;
        $config['ADAPTIVE_WEIGHTING_ENABLED'] = $moduleEnabled && $fileEnabled;

        // Get LLM provider and timeout from AgentTechnicalConfig if available
        if (AgentTechnicalConfig::isEnabled()) {
            $config['LLM_PROVIDER'] = AgentTechnicalConfig::getLLMProvider();
            $config['TIMEOUT_SECONDS'] = AgentTechnicalConfig::getCoordinationTimeout();
            $config['MAX_RETRIES'] = AgentTechnicalConfig::getMaxRetries();
            // Wire the admin AT knobs so they actually apply (were dead before):
            // max_critics bounds the critic count (selectCritics caps at available critics),
            // cache_ttl feeds the adaptive-weighting cache.
            $config['critics_per_evaluation'] = AgentTechnicalConfig::getMaxCritics();
            $config['weight_cache_ttl'] = AgentTechnicalConfig::getCacheTtl();
        }

        // Set other defaults if not in file
        $config = array_merge([
            'LLM_PROVIDER' => 'openai',
            'FALLBACK_ENABLED' => true,
            'FALLBACK_ALERT_THRESHOLD' => 0.05,
            'WEIGHT_AUDIT_RETENTION_DAYS' => 90,
            'ANOMALY_DETECTION_ENABLED' => true,
            'CRITIC_SELECTION_ENABLED' => false,
            'MAX_RETRIES' => 2,
            'TIMEOUT_SECONDS' => 30,
            'MIGRATION_MODE' => false,
            'ADAPTIVE_WEIGHT_ROLLOUT_PERCENTAGE' => 0
        ], $config);

        if ($this->debug) {
            error_log(sprintf(
                "ActorCriticCoordinator: Configuration loaded - Adaptive Weighting: %s (Module: %s, File: %s), LLM Provider: %s, Timeout: %ds",
                $config['ADAPTIVE_WEIGHTING_ENABLED'] ? 'enabled' : 'disabled',
                $moduleEnabled ? 'enabled' : 'disabled',
                $fileEnabled ? 'enabled' : 'disabled',
                $config['LLM_PROVIDER'],
                $config['TIMEOUT_SECONDS']
            ));
        }

        return $config;
    }
}
