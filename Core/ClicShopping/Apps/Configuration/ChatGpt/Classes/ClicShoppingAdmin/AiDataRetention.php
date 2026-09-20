<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\WeightAuditLogger;

/**
 * Time-based retention for the AI agent journals (AIACT-1, ex-OBS-1).
 *
 * Deletes append-only telemetry older than the configured window. Two tables are delegated to
 * WeightAuditLogger::cleanupOldRecords(), which already implements them.
 */
class AiDataRetention
{
  /**
   * Append-only journals, with the column carrying their age.
   *
   * A table is here only if a row is a past EVENT. Accumulating state, registries, configuration
   * and the measurement corpus are excluded on purpose — see EXCLUDED.
   *
   * @var array<string, string>
   */
  /**
   * The measurement corpus: it carries the user's own words, so the operator sets its window
   * separately and it is never purged on the journals' one.
   *
   * @var array<string, string>
   */
  private const CORPUS = [
    'rag_interactions' => 'date_added',
    'rag_feedback' => 'date_added',
  ];

  private const JOURNALS = [
    'rag_agent_critic_evaluations' => 'evaluated_at',
    'rag_agent_actor_executions' => 'executed_at',
    'rag_agent_coordinated_results' => 'created_at',
    'rag_agent_consensus_sessions' => 'created_at',
    'rag_agent_weight_consensus' => 'created_at',
    'rag_agent_objective_state_transitions' => 'transitioned_at',
    'rag_agent_audit_log' => 'timestamp',
    'rag_agent_abstentions' => 'abstained_at',
    'rag_memory_retention_log' => 'timestamp_recorded',
  ];

  /**
   * Never purged here, and why. Read this before adding a table above.
   *
   * @var array<string, string>
   */
  public const EXCLUDED = [
    'rag_agent_reputation' => 'accumulating state — deleting it is what REPUT-1 repaired',
    'rag_agent_reputation_history' => 'feeds the reputation calculation, not a log',
    'rag_agent_reputation_evaluation_outcomes' => 'same calculation input',
    'rag_agent_objectives' => 'a queue, not a journal — purging pending drops work if a consumer is ever turned on (GOV-AUTO2)',
    'rag_agent_actor_registry' => 'registry; its throwaway identities are REPUT-3, not retention',
    'rag_feedback_journal' => 'findings history — the trend over runs is the point, and it carries no user words',
  ];

  private mixed $db;
  private string $prefix;

  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->prefix = (string)CLICSHOPPING::getConfig('db_table_prefix');
  }

  /**
   * Whether the operator enabled retention. Off unless explicitly turned on: the job deletes.
   */
  public static function isEnabled(): bool
  {
    return defined('CLICSHOPPING_APP_CHATGPT_ASY_DATA_RETENTION_STATUS')
      && CLICSHOPPING_APP_CHATGPT_ASY_DATA_RETENTION_STATUS == 'True';
  }

  /**
   * Configured window in days. A non-positive value disables the job rather than deleting all.
   */
  public static function retentionDays(): int
  {
    $days = defined('CLICSHOPPING_APP_CHATGPT_ASY_DATA_RETENTION_DAYS')
      ? (int)CLICSHOPPING_APP_CHATGPT_ASY_DATA_RETENTION_DAYS
      : 90;

    return $days > 0 ? $days : 0;
  }

  /**
   * Window for the measurement corpus, chosen by the operator. Zero — the default — keeps it for
   * ever: enabling the journal purge must never silently delete what the user actually said.
   */
  public static function corpusRetentionDays(): int
  {
    $days = defined('CLICSHOPPING_APP_CHATGPT_ASY_DATA_RETENTION_CORPUS_DAYS')
      ? (int)CLICSHOPPING_APP_CHATGPT_ASY_DATA_RETENTION_CORPUS_DAYS
      : 0;

    return $days > 0 ? $days : 0;
  }

  /**
   * Rows that WOULD be deleted, per table. Read-only — the operator sees the cost before enabling.
   *
   * @return array<string, int>
   */
  public function report(?int $retentionDays = null): array
  {
    $days = $retentionDays ?? self::retentionDays();
    $counts = [];

    if ($days <= 0) {
      return $counts;
    }

    foreach (self::JOURNALS as $table => $column) {
      $counts[$table] = $this->countExpired($table, $column, $days);
    }

    $counts['rag_agent_adaptive_weights'] = $this->countExpired('rag_agent_adaptive_weights', 'created_at', $days);
    $counts['rag_agent_critic_weight_history'] = $this->countExpired('rag_agent_critic_weight_history', 'timestamp', $days);

    $corpusDays = self::corpusRetentionDays();

    if ($corpusDays > 0) {
      foreach (self::CORPUS as $table => $column) {
        $counts[$table] = $this->countExpired($table, $column, $corpusDays);
      }
    }

    return $counts;
  }

  /**
   * Deletes expired rows. Returns the count per table; a table that failed is absent, never zero.
   *
   * @return array<string, int>
   */
  public function run(?int $retentionDays = null): array
  {
    $days = $retentionDays ?? self::retentionDays();
    $deleted = [];

    if ($days <= 0) {
      return $deleted;
    }

    foreach (self::JOURNALS as $table => $column) {
      $count = $this->deleteExpired($table, $column, $days);

      if ($count !== null) {
        $deleted[$table] = $count;
      }
    }

    $corpusDays = self::corpusRetentionDays();

    if ($corpusDays > 0) {
      foreach (self::CORPUS as $table => $column) {
        $count = $this->deleteExpired($table, $column, $corpusDays);

        if ($count !== null) {
          $deleted[$table] = $count;
        }
      }
    }

    try {
      $deleted['weight_audit'] = (new WeightAuditLogger())->cleanupOldRecords($days);
    } catch (\Throwable $e) {
      error_log('[AiDataRetention] weight audit cleanup failed: ' . $e->getMessage());
    }

    return $deleted;
  }

  private function countExpired(string $table, string $column, int $days): int
  {
    try {
      $q = $this->db->prepare(
        'SELECT COUNT(*) AS n FROM ' . $this->prefix . $table
        . ' WHERE ' . $column . ' < DATE_SUB(NOW(), INTERVAL :days DAY)'
      );
      $q->bindInt(':days', $days);
      $q->execute();
      $q->fetch();

      return (int)$q->value('n');
    } catch (\Throwable $e) {
      error_log('[AiDataRetention] count failed on ' . $table . ': ' . $e->getMessage());

      return 0;
    }
  }

  private function deleteExpired(string $table, string $column, int $days): ?int
  {
    try {
      $expected = $this->countExpired($table, $column, $days);

      if ($expected === 0) {
        return 0;
      }

      $q = $this->db->prepare(
        'DELETE FROM ' . $this->prefix . $table
        . ' WHERE ' . $column . ' < DATE_SUB(NOW(), INTERVAL :days DAY)'
      );
      $q->bindInt(':days', $days);
      $q->execute();

      return $expected;
    } catch (\Throwable $e) {
      error_log('[AiDataRetention] delete failed on ' . $table . ': ' . $e->getMessage());

      return null;
    }
  }
}
