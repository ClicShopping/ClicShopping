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
 * The single autonomy guard the engine honours: may this agent create a local objective.
 * Read at `AnalyticsObjectiveRunner::createLocalObjective()`, which throws when it denies.
 *
 * Source order: `rag_agent_autonomous_config` row `config_key = 'global'` (a JSON object,
 * merged over the defaults below), then the defaults. The row has no writer in the code —
 * it is an operator lever, edited in base, never through a screen.
 *
 * An agent absent from `agents` is DENIED: declare it here to let it create objectives.
 * Never add an accessor here for a rule no decision point reads — a setting that commands
 * nothing reads as a governance guarantee.
 */
class AutonomousConfig
{
  private $db;
  private bool $debug;
  private array $config = [];

  private const DEFAULTS = [
    'autonomous_enabled' => true,
    'agents' => [
      'AnalyticsAgent' => [
        'autonomous_enabled' => true,
        'can_create_objectives' => true
      ]
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

    $this->loadFromDatabase();
    $this->config = array_replace_recursive(self::DEFAULTS, $this->config);
  }

  /**
   * Check if an agent is allowed to create objectives
   *
   * @param string $agentId Agent identifier
   * @return bool True if allowed
   */
  public function canAgentCreateObjectives(string $agentId): bool
  {
    if (!$this->get('autonomous_enabled', true)) {
      return false;
    }

    if (!$this->get("agents.{$agentId}.autonomous_enabled", false)) {
      return false;
    }

    return (bool)$this->get("agents.{$agentId}.can_create_objectives", false);
  }

  /**
   * Get a configuration value using dot notation
   *
   * @param string $key Configuration key (e.g., 'agents.AnalyticsAgent.can_create_objectives')
   * @param mixed $default Default value if key not found
   * @return mixed Configuration value
   */
  private function get(string $key, mixed $default = null): mixed
  {
    $value = $this->config;

    foreach (explode('.', $key) as $k) {
      if (!isset($value[$k])) {
        return $default;
      }
      $value = $value[$k];
    }

    return $value;
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
      $this->config = [];
    }
  }
}
