<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Config;

use ClicShopping\OM\Registry;

/**
 * AutonomousConfig
 *
 * Configuration management for autonomous agent features.
 * Provides per-agent toggles, quality thresholds, and consensus parameters.
 *
 * Configuration can be stored in:
 * - Database (rag_agent_autonomous_config table)
 * - Configuration constants
 * - Runtime overrides
 *
 * Priority order: Runtime > Database > Constants > Defaults
 *
 */
class AutonomousConfig
{
  private $db;
  private bool $debug;
  private array $config = [];
  private array $runtimeOverrides = [];

  // Default configuration values
  private const DEFAULTS = [
    // Global autonomous features toggle
    'autonomous_enabled' => true,

    // Per-agent autonomous feature toggles
    'agents' => [
      'AnalyticsAgent' => [
        'autonomous_enabled' => true,
        'can_create_objectives' => true,
        'can_evaluate_peers' => true,
        'can_receive_feedback' => true,
        'can_collaborate' => true
      ],
      'ReasoningAgent' => [
        'autonomous_enabled' => true,
        'can_create_objectives' => true,
        'can_evaluate_peers' => true,
        'can_receive_feedback' => true,
        'can_collaborate' => true
      ],
      'ValidationAgent' => [
        'autonomous_enabled' => true,
        'can_create_objectives' => true,
        'can_evaluate_peers' => true,
        'can_receive_feedback' => true,
        'can_collaborate' => true
      ]
    ],

    // Quality thresholds
    'quality_thresholds' => [
      'evaluation_score' => 0.70,      // Minimum score to pass evaluation
      'consensus_agreement' => 0.15,   // Max std deviation for consensus
      'correction_trigger' => 0.70,    // Score below which correction is triggered
      'suspension_threshold' => 0.50   // Score below which agent is suspended
    ],

    // Consensus parameters
    'consensus' => [
      'min_evaluators' => 2,           // Minimum evaluators for consensus
      'max_evaluators' => 5,           // Maximum evaluators to select
      'default_evaluators' => 3,       // Default number of evaluators
      'timeout_seconds' => 300,        // Consensus timeout (5 minutes)
      'max_discussion_rounds' => 3     // Max discussion rounds before escalation
    ],

    // Objective parameters
    'objectives' => [
      'require_approval_for_conflicts' => true,
      'require_approval_for_high_priority' => false,
      'max_active_per_agent' => 5,
      'default_estimated_time' => 1800  // 30 minutes
    ],

    // Feedback parameters
    'feedback' => [
      'enabled' => true,
      'require_acknowledgment' => true,
      'track_improvement_patterns' => true
    ]
  ];

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->db = Registry::get('Db');
    $this->debug = $debug;

    // Load configuration from database
    $this->loadFromDatabase();

    // Merge with defaults
    $this->config = array_replace_recursive(self::DEFAULTS, $this->config);
  }

  /**
   * Check if autonomous features are enabled globally
   *
   * @return bool True if enabled
   */
  public function isAutonomousEnabled(): bool
  {
    return $this->get('autonomous_enabled', true);
  }

  /**
   * Check if autonomous features are enabled for a specific agent
   *
   * @param string $agentId Agent identifier
   * @return bool True if enabled
   */
  public function isAgentAutonomousEnabled(string $agentId): bool
  {
    if (!$this->isAutonomousEnabled()) {
      return false;
    }

    return $this->get("agents.{$agentId}.autonomous_enabled", false);
  }

  /**
   * Check if an agent can create objectives
   *
   * @param string $agentId Agent identifier
   * @return bool True if allowed
   */
  public function canAgentCreateObjectives(string $agentId): bool
  {
    if (!$this->isAgentAutonomousEnabled($agentId)) {
      return false;
    }

    return $this->get("agents.{$agentId}.can_create_objectives", false);
  }

  /**
   * Check if an agent can evaluate peers
   *
   * @param string $agentId Agent identifier
   * @return bool True if allowed
   */
  public function canAgentEvaluatePeers(string $agentId): bool
  {
    if (!$this->isAgentAutonomousEnabled($agentId)) {
      return false;
    }

    return $this->get("agents.{$agentId}.can_evaluate_peers", false);
  }

  /**
   * Check if an agent can receive feedback
   *
   * @param string $agentId Agent identifier
   * @return bool True if allowed
   */
  public function canAgentReceiveFeedback(string $agentId): bool
  {
    if (!$this->isAgentAutonomousEnabled($agentId)) {
      return false;
    }

    return $this->get("agents.{$agentId}.can_receive_feedback", false);
  }

  /**
   * Check if an agent can collaborate
   *
   * @param string $agentId Agent identifier
   * @return bool True if allowed
   */
  public function canAgentCollaborate(string $agentId): bool
  {
    if (!$this->isAgentAutonomousEnabled($agentId)) {
      return false;
    }

    return $this->get("agents.{$agentId}.can_collaborate", false);
  }

  /**
   * Get quality threshold for evaluation scores
   *
   * @return float Threshold value (0.0 - 1.0)
   */
  public function getEvaluationScoreThreshold(): float
  {
    return (float)$this->get('quality_thresholds.evaluation_score', 0.70);
  }

  /**
   * Get consensus agreement threshold
   *
   * @return float Max standard deviation for consensus
   */
  public function getConsensusAgreementThreshold(): float
  {
    return (float)$this->get('quality_thresholds.consensus_agreement', 0.15);
  }

  /**
   * Get correction trigger threshold
   *
   * @return float Score below which correction is triggered
   */
  public function getCorrectionTriggerThreshold(): float
  {
    return (float)$this->get('quality_thresholds.correction_trigger', 0.70);
  }

  /**
   * Get suspension threshold
   *
   * @return float Score below which agent is suspended
   */
  public function getSuspensionThreshold(): float
  {
    return (float)$this->get('quality_thresholds.suspension_threshold', 0.50);
  }

  /**
   * Get minimum number of evaluators for consensus
   *
   * @return int Minimum evaluators
   */
  public function getMinEvaluators(): int
  {
    return (int)$this->get('consensus.min_evaluators', 2);
  }

  /**
   * Get maximum number of evaluators
   *
   * @return int Maximum evaluators
   */
  public function getMaxEvaluators(): int
  {
    return (int)$this->get('consensus.max_evaluators', 5);
  }

  /**
   * Get default number of evaluators
   *
   * @return int Default evaluators
   */
  public function getDefaultEvaluators(): int
  {
    return (int)$this->get('consensus.default_evaluators', 3);
  }

  /**
   * Get consensus timeout in seconds
   *
   * @return int Timeout in seconds
   */
  public function getConsensusTimeout(): int
  {
    return (int)$this->get('consensus.timeout_seconds', 300);
  }

  /**
   * Get maximum discussion rounds before escalation
   *
   * @return int Max rounds
   */
  public function getMaxDiscussionRounds(): int
  {
    return (int)$this->get('consensus.max_discussion_rounds', 3);
  }

  /**
   * Check if approval is required for conflicting objectives
   *
   * @return bool True if approval required
   */
  public function requiresApprovalForConflicts(): bool
  {
    return $this->get('objectives.require_approval_for_conflicts', true);
  }

  /**
   * Check if approval is required for high-priority objectives
   *
   * @return bool True if approval required
   */
  public function requiresApprovalForHighPriority(): bool
  {
    return $this->get('objectives.require_approval_for_high_priority', false);
  }

  /**
   * Get maximum active objectives per agent
   *
   * @return int Max active objectives
   */
  public function getMaxActiveObjectivesPerAgent(): int
  {
    return (int)$this->get('objectives.max_active_per_agent', 5);
  }

  /**
   * Get default estimated time for objectives
   *
   * @return int Time in seconds
   */
  public function getDefaultEstimatedTime(): int
  {
    return (int)$this->get('objectives.default_estimated_time', 1800);
  }

  /**
   * Check if feedback is enabled
   *
   * @return bool True if enabled
   */
  public function isFeedbackEnabled(): bool
  {
    return $this->get('feedback.enabled', true);
  }

  /**
   * Check if feedback acknowledgment is required
   *
   * @return bool True if required
   */
  public function requiresFeedbackAcknowledgment(): bool
  {
    return $this->get('feedback.require_acknowledgment', true);
  }

  /**
   * Check if improvement pattern tracking is enabled
   *
   * @return bool True if enabled
   */
  public function trackImprovementPatterns(): bool
  {
    return $this->get('feedback.track_improvement_patterns', true);
  }

  /**
   * Get a configuration value using dot notation
   *
   * @param string $key Configuration key (e.g., 'agents.AnalyticsAgent.autonomous_enabled')
   * @param mixed $default Default value if key not found
   * @return mixed Configuration value
   */
  public function get(string $key, mixed $default = null): mixed
  {
    // Check runtime overrides first
    if (isset($this->runtimeOverrides[$key])) {
      return $this->runtimeOverrides[$key];
    }

    // Parse dot notation
    $keys = explode('.', $key);
    $value = $this->config;

    foreach ($keys as $k) {
      if (!isset($value[$k])) {
        return $default;
      }
      $value = $value[$k];
    }

    return $value;
  }

  /**
   * Set a configuration value at runtime
   *
   * @param string $key Configuration key
   * @param mixed $value Configuration value
   */
  public function set(string $key, mixed $value): void
  {
    $this->runtimeOverrides[$key] = $value;
  }

  /**
   * Save configuration to database
   *
   * @return bool True if successful
   */
  public function save(): bool
  {
    try {
      // Merge runtime overrides into config
      foreach ($this->runtimeOverrides as $key => $value) {
        $keys = explode('.', $key);
        $current = &$this->config;

        foreach ($keys as $i => $k) {
          if ($i === count($keys) - 1) {
            $current[$k] = $value;
          } else {
            if (!isset($current[$k])) {
              $current[$k] = [];
            }
            $current = &$current[$k];
          }
        }
      }

      // Save to database
      $configJson = json_encode($this->config);

      $sql = "INSERT INTO :table_rag_agent_autonomous_config (config_key, config_value, created_at, updated_at)
              VALUES ('global', :config_value, NOW(), NOW())
              ON DUPLICATE KEY UPDATE config_value = :config_value, updated_at = NOW()";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':config_value', $configJson);
      $stmt->execute();

      // Clear runtime overrides after save
      $this->runtimeOverrides = [];

      return true;

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("AutonomousConfig: Failed to save configuration - " . $e->getMessage());
      }
      return false;
    }
  }

  /**
   * Load configuration from database
   */
  private function loadFromDatabase(): void
  {
    try {
      $sql = "SELECT config_value 
             FROM :table_rag_agent_autonomous_config 
             WHERE config_key = 'global' LIMIT 1
             ";
      $stmt = $this->db->prepare($sql);
      $stmt->execute();

      $row = $stmt->fetch();
      if ($row && !empty($row['config_value'])) {
        $this->config = json_decode($row['config_value'], true) ?? [];
      }

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("AutonomousConfig: Failed to load configuration from database - " . $e->getMessage());
      }
      // Use defaults if database load fails
      $this->config = [];
    }
  }

  /**
   * Reset configuration to defaults
   */
  public function resetToDefaults(): void
  {
    $this->config = self::DEFAULTS;
    $this->runtimeOverrides = [];
  }

  /**
   * Get all configuration as array
   *
   * @return array Complete configuration
   */
  public function toArray(): array
  {
    $config = $this->config;

    // Apply runtime overrides
    foreach ($this->runtimeOverrides as $key => $value) {
      $keys = explode('.', $key);
      $current = &$config;

      foreach ($keys as $i => $k) {
        if ($i === count($keys) - 1) {
          $current[$k] = $value;
        } else {
          if (!isset($current[$k])) {
            $current[$k] = [];
          }
          $current = &$current[$k];
        }
      }
    }

    return $config;
  }
}
