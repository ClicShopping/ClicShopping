<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Gouvernance\Feedback;

use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\OM\CLICSHOPPING;

/**
 * FeedbackJournal
 *
 * Reads what users reported against what the system actually DID, and returns it as EVIDENCE:
 * every figure comes with the population it was measured on, so a reader can oppose it.
 *
 * Findings are returned as CODES, never as prose: the wording belongs to the surface that renders
 * them, this layer stays agnostic and free of language files.
 *
 * `report()` only reads. `persist()` writes the findings so two screens consulted at different
 * moments read the same analysis instead of recomputing two of them.
 */
class FeedbackJournal
{
  /**
   * Every code this journal can emit. A surface renders one label per code, so a code absent
   * from here is a finding nobody will ever be able to read.
   */
  public const CODES = [
    'unjoinable_feedback',
    'blind_trace',
    'negative_concentration',
    'silent_comment',
    'verdict_disagreement',
    'thin_population'
  ];

  /**
   * Intents whose answer is built from SQL. A semantic answer carries none, and counting it as
   * a missing trace would fabricate a defect.
   */
  public const SQL_BEARING_INTENTS = ['analytics', 'hybrid'];

  /**
   * Below this many reports over the window, a rate describes anecdotes.
   */
  public const THIN_POPULATION = 30;

  /**
   * How long a stored run stays the current one. Two surfaces opened the same day must read the
   * same analysis, so a run is reused rather than recomputed per visit.
   */
  public const REFRESH_AFTER_HOURS = 24;

  private string $prefix;

  public function __construct()
  {
    $this->prefix = (string)CLICSHOPPING::getConfig('db_table_prefix');
  }

  /**
   * What the reports say, and what the system can prove about them.
   *
   * @param int $days Window in days
   * @return array{generated_at:string, period_days:int, population:array, findings:array<int, array{code:string, severity:string, population:int, keys:array, figures:array}>}
   */
  public function report(int $days = 30): array
  {
    $days = max(1, $days);
    $population = $this->population($days);

    $findings = [];

    foreach (['unjoinable', 'blindTrace', 'negativeConcentration', 'silentComment', 'verdictDisagreement', 'thinPopulation'] as $probe) {
      $finding = $this->{$probe}($days, $population);

      if ($finding !== null) {
        $findings[] = $finding;
      }
    }

    return [
      'generated_at' => date('Y-m-d H:i:s'),
      'period_days' => $days,
      'population' => $population,
      'findings' => $findings
    ];
  }

  /**
   * Store the findings of a report so both surfaces read the same analysis.
   *
   * @param array $report Output of report()
   * @return int Findings written
   */
  public function persist(array $report): int
  {
    $written = 0;

    foreach ($report['findings'] ?? [] as $finding) {
      DoctrineOrm::insert('rag_feedback_journal', [
        'generated_at' => $report['generated_at'],
        'period_days' => $report['period_days'],
        'code' => $finding['code'],
        'severity' => $finding['severity'],
        'population' => $finding['population'],
        'agent_used' => $finding['keys']['agent_used'] ?? null,
        'intent_type' => $finding['keys']['intent_type'] ?? null,
        'figures' => json_encode($finding['figures'], JSON_UNESCAPED_UNICODE)
      ]);

      $written++;
    }

    return $written;
  }

  /**
   * The analysis both surfaces read: the stored run while it is current, a new one otherwise.
   *
   * This is the single entry point of a rendering surface. Producing per visit would give the
   * manager and the data scientist two different analyses of the same window.
   *
   * @param int $days Window in days
   * @return array{generated_at:?string, period_days:int, findings:array<int, array<string, mixed>>, refreshed:bool}
   */
  public function refresh(int $days = 30): array
  {
    if (!$this->isStale($days)) {
      $findings = $this->latest();

      return [
        'generated_at' => $findings[0]['generated_at'] ?? null,
        'period_days' => (int)($findings[0]['period_days'] ?? $days),
        'findings' => $findings,
        'refreshed' => false
      ];
    }

    $report = $this->report($days);
    $this->persist($report);

    return [
      'generated_at' => $report['generated_at'],
      'period_days' => $report['period_days'],
      'population' => $report['population'],
      'findings' => $this->latest(),
      'refreshed' => true
    ];
  }

  /**
   * Whether the stored run is too old, taken on the same window. A run measured over another
   * window answers another question and never stands for this one.
   *
   * @param int $days Window in days
   * @return bool
   */
  private function isStale(int $days): bool
  {
    $last = DoctrineOrm::selectValue("
      SELECT MAX(generated_at)
      FROM {$this->prefix}rag_feedback_journal
      WHERE period_days = ?
        AND generated_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
    ", [$days, self::REFRESH_AFTER_HOURS]);

    return $last === null || $last === false;
  }

  /**
   * The findings of the most recent run, for a surface that renders but never recomputes.
   *
   * @param int $limit Maximum findings returned
   * @return array<int, array<string, mixed>>
   */
  public function latest(int $limit = 50): array
  {
    $rows = DoctrineOrm::select("
      SELECT code, severity, population, agent_used, intent_type, figures, period_days, generated_at
      FROM {$this->prefix}rag_feedback_journal
      WHERE generated_at = (SELECT MAX(generated_at) FROM {$this->prefix}rag_feedback_journal)
      ORDER BY FIELD(severity, 'critical', 'high', 'medium', 'low'), code
      LIMIT " . max(1, $limit)
    );

    foreach ($rows as &$row) {
      $row['figures'] = json_decode((string)$row['figures'], true) ?: [];
    }

    return $rows;
  }

  /**
   * What the window actually contains. Every rate below divides by one of these.
   *
   * @param int $days Window in days
   * @return array{total:int, joined:int, negative:int, positive:int, sql_bearing:int, with_verdict:int}
   */
  private function population(int $days): array
  {
    $intents = implode(',', array_fill(0, count(self::SQL_BEARING_INTENTS), '?'));

    $row = DoctrineOrm::selectOne("
      SELECT COUNT(*) AS total,
             SUM(i.interaction_id IS NOT NULL) AS joined_rows,
             SUM(f.feedback_type = 'negative') AS negative,
             SUM(f.feedback_type = 'positive') AS positive,
             SUM(i.intent_type IN ({$intents})) AS sql_bearing,
             SUM(i.validation_action IS NOT NULL) AS with_verdict
      FROM {$this->prefix}rag_feedback f
      LEFT JOIN {$this->prefix}rag_interactions i ON i.client_interaction_id = f.interaction_id
      WHERE f.date_added >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ", [...self::SQL_BEARING_INTENTS, $days]);

    return [
      'total' => (int)($row['total'] ?? 0),
      'joined' => (int)($row['joined_rows'] ?? 0),
      'negative' => (int)($row['negative'] ?? 0),
      'positive' => (int)($row['positive'] ?? 0),
      'sql_bearing' => (int)($row['sql_bearing'] ?? 0),
      'with_verdict' => (int)($row['with_verdict'] ?? 0)
    ];
  }

  /**
   * A report that reaches no interaction cannot be acted on: nothing says what was answered.
   */
  private function unjoinable(int $days, array $population): ?array
  {
    $unjoinable = $population['total'] - $population['joined'];

    if ($unjoinable <= 0) {
      return null;
    }

    return $this->finding('unjoinable_feedback', 'high', $population['total'], [
      'unjoinable' => $unjoinable,
      'total' => $population['total']
    ]);
  }

  /**
   * A report on a SQL-bearing answer whose SQL or verdict was never stored: the human is asked
   * to correct what the system will not show.
   */
  private function blindTrace(int $days, array $population): ?array
  {
    if ($population['sql_bearing'] === 0) {
      return null;
    }

    $intents = implode(',', array_fill(0, count(self::SQL_BEARING_INTENTS), '?'));

    $row = DoctrineOrm::selectOne("
      SELECT SUM(i.sql_query IS NULL) AS without_sql,
             SUM(i.validation_action IS NULL) AS without_verdict
      FROM {$this->prefix}rag_feedback f
      INNER JOIN {$this->prefix}rag_interactions i ON i.client_interaction_id = f.interaction_id
      WHERE f.date_added >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND i.intent_type IN ({$intents})
    ", [$days, ...self::SQL_BEARING_INTENTS]);

    $withoutSql = (int)($row['without_sql'] ?? 0);
    $withoutVerdict = (int)($row['without_verdict'] ?? 0);

    if ($withoutSql === 0 && $withoutVerdict === 0) {
      return null;
    }

    return $this->finding(
      'blind_trace',
      $withoutSql === $population['sql_bearing'] ? 'critical' : 'high',
      $population['sql_bearing'],
      ['without_sql' => $withoutSql, 'without_verdict' => $withoutVerdict, 'sql_bearing' => $population['sql_bearing']]
    );
  }

  /**
   * Where the complaints land. One pair carrying them all is the first thing to look at, and it
   * is also what makes the finding crossable between the two surfaces.
   */
  private function negativeConcentration(int $days, array $population): ?array
  {
    if ($population['negative'] === 0) {
      return null;
    }

    $row = DoctrineOrm::selectOne("
      SELECT i.agent_used, i.intent_type, COUNT(*) AS negatives
      FROM {$this->prefix}rag_feedback f
      INNER JOIN {$this->prefix}rag_interactions i ON i.client_interaction_id = f.interaction_id
      WHERE f.date_added >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND f.feedback_type = 'negative'
      GROUP BY i.agent_used, i.intent_type
      ORDER BY negatives DESC
      LIMIT 1
    ", [$days]);

    if ($row === null) {
      return null;
    }

    $negatives = (int)$row['negatives'];
    $share = round($negatives / $population['negative'] * 100, 1);

    return $this->finding(
      'negative_concentration',
      $share >= 100.0 ? 'high' : 'medium',
      $population['negative'],
      ['negatives' => $negatives, 'reported' => $population['negative'], 'share' => $share],
      ['agent_used' => $row['agent_used'], 'intent_type' => $row['intent_type']]
    );
  }

  /**
   * A negative report with no words attached says something is wrong and nothing else.
   */
  private function silentComment(int $days, array $population): ?array
  {
    if ($population['negative'] === 0) {
      return null;
    }

    $silent = (int)DoctrineOrm::selectValue("
      SELECT COUNT(*)
      FROM {$this->prefix}rag_feedback
      WHERE date_added >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND feedback_type = 'negative'
        AND COALESCE(TRIM(JSON_UNQUOTE(JSON_EXTRACT(feedback_data, '$.feedback_text'))), '') = ''
    ", [$days]);

    if ($silent === 0) {
      return null;
    }

    return $this->finding('silent_comment', 'medium', $population['negative'], [
      'silent' => $silent,
      'negatives' => $population['negative']
    ]);
  }

  /**
   * The machine critic passed an answer its reader rejected. That disagreement is the whole
   * point of keeping both verdicts.
   */
  private function verdictDisagreement(int $days, array $population): ?array
  {
    if ($population['with_verdict'] === 0) {
      return null;
    }

    $disagreements = (int)DoctrineOrm::selectValue("
      SELECT COUNT(*)
      FROM {$this->prefix}rag_feedback f
      INNER JOIN {$this->prefix}rag_interactions i ON i.client_interaction_id = f.interaction_id
      WHERE f.date_added >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND f.feedback_type = 'negative'
        AND i.validation_action = 'pass'
    ", [$days]);

    if ($disagreements === 0) {
      return null;
    }

    return $this->finding('verdict_disagreement', 'high', $population['with_verdict'], [
      'disagreements' => $disagreements,
      'with_verdict' => $population['with_verdict']
    ]);
  }

  /**
   * Said before any rate is read, not after: under this many reports, a percentage is a story
   * about two or three rows.
   */
  private function thinPopulation(int $days, array $population): ?array
  {
    if ($population['total'] >= self::THIN_POPULATION) {
      return null;
    }

    return $this->finding('thin_population', 'medium', $population['total'], [
      'total' => $population['total'],
      'threshold' => self::THIN_POPULATION,
      'days' => $days
    ]);
  }

  /**
   * One finding, in the shape every surface reads.
   *
   * @param string $code Finding code, never prose
   * @param string $severity critical|high|medium|low
   * @param int $population What the figures were measured on
   * @param array $figures The figures themselves
   * @param array $keys Cross keys, when the finding points at one agent or intent
   * @return array{code:string, severity:string, population:int, keys:array, figures:array}
   */
  private function finding(string $code, string $severity, int $population, array $figures, array $keys = []): array
  {
    return [
      'code' => $code,
      'severity' => $severity,
      'population' => $population,
      'keys' => $keys,
      'figures' => $figures
    ];
  }
}
