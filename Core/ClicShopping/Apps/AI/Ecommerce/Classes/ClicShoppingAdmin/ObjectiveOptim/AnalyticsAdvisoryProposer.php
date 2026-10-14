<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\ObjectiveOptim;

use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\LocalObjective;
use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\ObjectiveProposal;
use ClicShopping\AI\InterfacesAI\ObjectiveProposerInterface;
use ClicShopping\OM\Registry;

/**
 * AnalyticsAdvisoryProposer — Ecommerce-domain proposer for slow-analytics-query
 * objectives (§Z Z3 v1, advisory).
 *
 * measureBaseline()/measureResult() re-time the objective's stored SELECT via the same
 * Registry::get('Db') + microtime path the live analytics executor uses. propose() emits
 * an advisory recommendation; apply() records it but mutates NOTHING (advisory v1), so
 * before ≈ after — the engine's before/after machinery is what a future mutating proposer
 * will exercise for a real delta. evaluateSuccess() compares measured ms vs the criteria.
 */
class AnalyticsAdvisoryProposer implements ObjectiveProposerInterface
{
  private array $recordedAdvice = [];

  public function __construct(private readonly bool $debug = false)
  {
  }

  public function measureBaseline(LocalObjective $objective): array
  {
    return ['execution_time_ms' => $this->timeQuery($objective->getSuccessCriteria()['query'] ?? '')];
  }

  public function propose(LocalObjective $objective, array $baseline): ?ObjectiveProposal
  {
    $criteria = $objective->getSuccessCriteria();
    $query = (string)($criteria['query'] ?? '');
    $maxMs = (float)($criteria['max_execution_time_ms'] ?? 0.0);
    $measured = (float)($baseline['execution_time_ms'] ?? 0.0);

    // Decline if the query is empty, has no defined budget, or is already within budget.
    if ($query === '' || $maxMs <= 0.0 || $measured <= $maxMs) {
      return null;
    }

    $advice = sprintf(
      'Slow analytics query (%.0f ms > %.0f ms budget). Advisory: review indexing / '
      . 'consider a cached projection for this query class.',
      $measured,
      $maxMs
    );

    return new ObjectiveProposal('analytics_advisory', ['query' => $query, 'measured_ms' => $measured], $advice);
  }

  public function apply(ObjectiveProposal $proposal): void
  {
    // Advisory v1: record the recommendation, mutate nothing.
    $this->recordedAdvice[] = $proposal->getDescription();
    if ($this->debug) {
      error_log('AnalyticsAdvisoryProposer: recorded advisory (no mutation) - ' . $proposal->getDescription());
    }
  }

  public function measureResult(LocalObjective $objective): array
  {
    // Advisory v1 mutates nothing, so this re-measures the same query (≈ baseline).
    return ['execution_time_ms' => $this->timeQuery($objective->getSuccessCriteria()['query'] ?? '')];
  }

  public function evaluateSuccess(array $baseline, array $result, array $successCriteria): bool
  {
    $maxMs = (float)($successCriteria['max_execution_time_ms'] ?? 0.0);
    if ($maxMs <= 0.0) {
      return false;
    }
    return (float)($result['execution_time_ms'] ?? PHP_FLOAT_MAX) <= $maxMs;
  }

  /** Recorded advisories (read for tests / dormant introspection). */
  public function getRecordedAdvice(): array
  {
    return $this->recordedAdvice;
  }

  /**
   * Time a read-only SELECT via the analytics DB paradigm. Guarded to SELECT-only;
   * returns PHP_FLOAT_MAX on any error or non-SELECT so the objective fails closed.
   */
  private function timeQuery(string $query): float
  {
    // Read-only guard: must start with SELECT and contain no INTO (blocks SELECT ... INTO
    // OUTFILE/DUMPFILE writes). /s so the INTO check spans multi-line SQL.
    if (!preg_match('/^\s*SELECT\b(?!.*\bINTO\b)/is', $query)) {
      return PHP_FLOAT_MAX;
    }

    try {
      $db = Registry::get('Db');
      $start = microtime(true);
      $stmt = $db->prepare($query);
      $stmt->execute();
      // Drain a bounded number of rows so timing reflects real result materialisation
      // without loading an unbounded set into memory.
      $rows = 0;
      while ($stmt->fetch() !== false && $rows < 1000) {
        $rows++;
      }
      return (microtime(true) - $start) * 1000.0;
    } catch (\Throwable $e) {
      if ($this->debug) {
        error_log('AnalyticsAdvisoryProposer: timeQuery failed - ' . $e->getMessage());
      }
      return PHP_FLOAT_MAX;
    }
  }
}
