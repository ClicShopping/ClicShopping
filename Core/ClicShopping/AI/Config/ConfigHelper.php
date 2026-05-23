<?php
/**
 * Configuration Helper Utility
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * This utility class provides convenient helper methods for working with
 * actor-critic configuration. It simplifies common configuration tasks
 * and provides higher-level abstractions.
 *
 * Key Features:
 * - Convenient access to configuration values
 * - Configuration presets for common scenarios
 * - Configuration validation helpers
 * - Configuration export/import utilities
 * - Configuration comparison and diff
 *
 * Usage Example:
 * ```php
 * use ClicShopping\AI\Config\ConfigHelper;
 *
 * // Apply a preset configuration
 * ConfigHelper::applyPreset('high_accuracy');
 *
 * // Check if actor-critic separation is enabled
 * if (ConfigHelper::isActorCriticEnabled()) {
 *   // Use actor-critic workflow
 * }
 *
 * // Get coordination parameters
 * $params = ConfigHelper::getCoordinationParams();
 * ```
 *
 * @created 2026-01-31
 * @see ActorCriticConfig
 */

namespace ClicShopping\AI\Config;

use Exception;

class ConfigHelper
{
  /**
   * Configuration presets for common scenarios
   */
  private const PRESETS = [
    'high_accuracy' => [
      'critics_per_evaluation' => 5,
      'consensus_threshold' => 0.8,
      'minimum_critics_required' => 3,
      'outlier_detection_enabled' => true,
      'discussion_phase_enabled' => true,
    ],
    'fast_execution' => [
      'critics_per_evaluation' => 2,
      'critic_evaluation_timeout' => 15,
      'consensus_threshold' => 0.6,
      'minimum_critics_required' => 2,
      'parallel_evaluation_enabled' => true,
    ],
    'balanced' => [
      'critics_per_evaluation' => 3,
      'critic_evaluation_timeout' => 30,
      'consensus_threshold' => 0.7,
      'minimum_critics_required' => 2,
      'parallel_evaluation_enabled' => true,
    ],
    'development' => [
      'actor_critic_separation_enabled' => true,
      'hybrid_agent_support_enabled' => true,
      'migration_logging_enabled' => true,
      'error_logging_enabled' => true,
      'metrics_tracking_enabled' => true,
    ],
    'production' => [
      'actor_critic_separation_enabled' => true,
      'hybrid_agent_support_enabled' => false,
      'migration_logging_enabled' => false,
      'error_logging_enabled' => true,
      'metrics_tracking_enabled' => true,
      'alert_on_performance_degradation' => true,
    ],
  ];

  /**
   * Apply a configuration preset
   *
   * @param string $presetName Name of the preset to apply
   * @return bool True if preset applied successfully
   * @throws Exception If preset not found
   */
  public static function applyPreset(string $presetName): bool
  {
    if (!isset(self::PRESETS[$presetName])) {
      throw new Exception("Configuration preset not found: {$presetName}");
    }

    $config = ActorCriticConfig::getInstance();
    $config->setMultiple(self::PRESETS[$presetName]);

    return true;
  }

  /**
   * Get available preset names
   *
   * @return array List of available preset names
   */
  public static function getAvailablePresets(): array
  {
    return array_keys(self::PRESETS);
  }

  /**
   * Get preset configuration
   *
   * @param string $presetName Name of the preset
   * @return array Preset configuration
   * @throws Exception If preset not found
   */
  public static function getPreset(string $presetName): array
  {
    if (!isset(self::PRESETS[$presetName])) {
      throw new Exception("Configuration preset not found: {$presetName}");
    }

    return self::PRESETS[$presetName];
  }

  /**
   * Check if actor-critic separation is enabled
   *
   * @return bool True if enabled
   */
  public static function isActorCriticEnabled(): bool
  {
    $config = ActorCriticConfig::getInstance();
    return $config->get('actor_critic_separation_enabled', true);
  }

  /**
   * Check if hybrid agent support is enabled
   *
   * @return bool True if enabled
   */
  public static function isHybridAgentSupportEnabled(): bool
  {
    $config = ActorCriticConfig::getInstance();
    return $config->get('hybrid_agent_support_enabled', true);
  }

  /**
   * Get coordination parameters as an array
   *
   * @return array Coordination parameters
   */
  public static function getCoordinationParams(): array
  {
    $config = ActorCriticConfig::getInstance();

    return [
      'critics_per_evaluation' => $config->get('critics_per_evaluation'),
      'critic_evaluation_timeout' => $config->get('critic_evaluation_timeout'),
      'actor_retry_attempts' => $config->get('actor_retry_attempts'),
      'actor_execution_timeout' => $config->get('actor_execution_timeout'),
      'parallel_evaluation_enabled' => $config->get('parallel_evaluation_enabled'),
    ];
  }

  /**
   * Get consensus parameters as an array
   *
   * @return array Consensus parameters
   */
  public static function getConsensusParams(): array
  {
    $config = ActorCriticConfig::getInstance();

    return [
      'consensus_threshold' => $config->get('consensus_threshold'),
      'minimum_critics_required' => $config->get('minimum_critics_required'),
      'outlier_detection_enabled' => $config->get('outlier_detection_enabled'),
      'outlier_threshold' => $config->get('outlier_threshold'),
      'discussion_phase_enabled' => $config->get('discussion_phase_enabled'),
    ];
  }

  /**
   * Get load balancing parameters as an array
   *
   * @return array Load balancing parameters
   */
  public static function getLoadBalancingParams(): array
  {
    $config = ActorCriticConfig::getInstance();

    return [
      'max_concurrent_actions_per_actor' => $config->get('max_concurrent_actions_per_actor'),
      'max_concurrent_evaluations_per_critic' => $config->get('max_concurrent_evaluations_per_critic'),
      'load_balancing_enabled' => $config->get('load_balancing_enabled'),
      'load_balancing_algorithm' => $config->get('load_balancing_algorithm'),
    ];
  }

  /**
   * Get selection parameters as an array
   *
   * @return array Selection parameters
   */
  public static function getSelectionParams(): array
  {
    $config = ActorCriticConfig::getInstance();

    return [
      'actor_selection_algorithm' => $config->get('actor_selection_algorithm'),
      'critic_selection_algorithm' => $config->get('critic_selection_algorithm'),
      'prefer_domain_specialists' => $config->get('prefer_domain_specialists'),
      'require_domain_specialists' => $config->get('require_domain_specialists'),
    ];
  }

  /**
   * Export configuration to array
   *
   * @return array Complete configuration
   */
  public static function exportConfig(): array
  {
    $config = ActorCriticConfig::getInstance();
    return $config->getAll();
  }

  /**
   * Import configuration from array
   *
   * @param array $configArray Configuration array
   * @return bool True if imported successfully
   * @throws Exception If validation fails
   */
  public static function importConfig(array $configArray): bool
  {
    $config = ActorCriticConfig::getInstance();
    $config->setMultiple($configArray);
    return true;
  }

  /**
   * Compare current configuration with defaults
   *
   * @return array Array of differences [key => ['current' => value, 'default' => value]]
   */
  public static function compareWithDefaults(): array
  {
    $config = ActorCriticConfig::getInstance();
    $current = $config->getAll();
    $defaults = $config->getDefaults();

    $differences = [];
    foreach ($current as $key => $value) {
      if (isset($defaults[$key]) && $defaults[$key] !== $value) {
        $differences[$key] = [
          'current' => $value,
          'default' => $defaults[$key],
        ];
      }
    }

    return $differences;
  }

  /**
   * Get configuration summary for display
   *
   * @return array Configuration summary grouped by category
   */
  public static function getConfigSummary(): array
  {
    $config = ActorCriticConfig::getInstance();

    return [
      'coordination' => self::getCoordinationParams(),
      'consensus' => self::getConsensusParams(),
      'load_balancing' => self::getLoadBalancingParams(),
      'selection' => self::getSelectionParams(),
      'features' => [
        'actor_critic_enabled' => $config->get('actor_critic_separation_enabled'),
        'hybrid_support' => $config->get('hybrid_agent_support_enabled'),
        'metrics_tracking' => $config->get('metrics_tracking_enabled'),
        'dashboard' => $config->get('dashboard_enabled'),
      ],
    ];
  }

  /**
   * Validate configuration and return validation report
   *
   * @return array Validation report ['valid' => bool, 'errors' => array]
   */
  public static function validateConfig(): array
  {
    $config = ActorCriticConfig::getInstance();
    $errors = [];

    // Validate each parameter
    $allConfig = $config->getAll();
    foreach ($allConfig as $key => $value) {
      $rules = $config->getValidationRules($key);
      if ($rules === null) {
        continue;
      }

      // Type validation
      if (isset($rules['type'])) {
        switch ($rules['type']) {
          case 'integer':
            if (!is_int($value)) {
              $errors[$key] = "Must be an integer";
            } elseif (isset($rules['min']) && $value < $rules['min']) {
              $errors[$key] = "Must be >= {$rules['min']}";
            } elseif (isset($rules['max']) && $value > $rules['max']) {
              $errors[$key] = "Must be <= {$rules['max']}";
            }
            break;

          case 'float':
            if (!is_float($value) && !is_int($value)) {
              $errors[$key] = "Must be a float";
            } elseif (isset($rules['min']) && $value < $rules['min']) {
              $errors[$key] = "Must be >= {$rules['min']}";
            } elseif (isset($rules['max']) && $value > $rules['max']) {
              $errors[$key] = "Must be <= {$rules['max']}";
            }
            break;

          case 'boolean':
            if (!is_bool($value)) {
              $errors[$key] = "Must be a boolean";
            }
            break;

          case 'string':
            if (!is_string($value)) {
              $errors[$key] = "Must be a string";
            } elseif (isset($rules['pattern']) && !preg_match($rules['pattern'], $value)) {
              $errors[$key] = "Must match pattern {$rules['pattern']}";
            }
            break;

          case 'enum':
            if (!in_array($value, $rules['values'], true)) {
              $errors[$key] = "Must be one of: " . implode(', ', $rules['values']);
            }
            break;
        }
      }
    }

    return [
      'valid' => empty($errors),
      'errors' => $errors,
    ];
  }

  /**
   * Reset configuration to defaults
   *
   * @return bool True if reset successfully
   */
  public static function resetToDefaults(): bool
  {
    $config = ActorCriticConfig::getInstance();
    $config->reset();
    return true;
  }

  /**
   * Get configuration value with type casting
   *
   * @param string $key Configuration key
   * @param string $type Expected type (int, float, bool, string)
   * @param mixed $default Default value
   * @return mixed Configuration value cast to specified type
   */
  public static function getTyped(string $key, string $type, mixed $default = null): mixed
  {
    $config = ActorCriticConfig::getInstance();
    $value = $config->get($key, $default);

    return match ($type) {
      'int', 'integer' => (int)$value,
      'float', 'double' => (float)$value,
      'bool', 'boolean' => (bool)$value,
      'string' => (string)$value,
      default => $value,
    };
  }

  /**
   * Check if configuration has been modified from defaults
   *
   * @return bool True if any configuration differs from defaults
   */
  public static function isModified(): bool
  {
    $differences = self::compareWithDefaults();
    return !empty($differences);
  }

  /**
   * Get configuration change log
   *
   * @return array List of changes from defaults
   */
  public static function getChangeLog(): array
  {
    $differences = self::compareWithDefaults();
    $log = [];

    foreach ($differences as $key => $change) {
      $log[] = [
        'parameter' => $key,
        'from' => $change['default'],
        'to' => $change['current'],
      ];
    }

    return $log;
  }
}
