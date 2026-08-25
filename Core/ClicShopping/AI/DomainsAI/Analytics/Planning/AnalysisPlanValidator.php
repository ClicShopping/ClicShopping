<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\AI\DomainsAI\Analytics\Planning;

/**
 * AnalysisPlanValidator
 *
 * Turns a plan the model proposed into a plan the generator may trust: every metric is known
 * to the domain catalogue, its grain and type come FROM that catalogue rather than from the
 * model, and the comparison window is computed. What the catalogue cannot honour is removed
 * and named, so the answer can announce the gap instead of returning a zero that looks like
 * a result.
 *
 * Agnostic: it never reads a metric NAME, only the catalogue it is given.
 *
 * @package ClicShopping\AI\DomainsAI\Analytics\Planning
 */
class AnalysisPlanValidator
{
  /** @var array<string, array{grain: string, type: string, definition: string}> */
  private array $catalog;

  /**
   * @param array<string, array{grain: string, type: string, definition: string}> $catalog Domain metric catalogue
   */
  public function __construct(array $catalog)
  {
    $this->catalog = $catalog;
  }

  /**
   * `no_metric_proposed` separates "this question is not a metric aggregation" from "everything
   * proposed was rejected". Only the second is a refusal the user must be told about.
   *
   * @param array $plan Raw plan as parsed from the model's JSON
   * @return array{plan: array|null, unsatisfiable: array<int, array{element: string, label: string, reason: string}>, errors: array<int, string>, no_metric_proposed: bool}
   */
  public function validate(array $plan): array
  {
    $unsatisfiable = [];
    $errors = [];

    // Kept apart from the rejections until the very end: what the model DECLARED out of
    // catalogue must never turn a question that aggregates nothing into a refusal.
    $declared = $this->collectDeclaredUnsupported($plan['unsupported'] ?? []);

    // Normalise metrics: must be an array, not a string or other scalar.
    $rawMetrics = $plan['metrics'] ?? [];
    if (!is_array($rawMetrics)) {
      $errors[] = 'the plan carries a metrics field that is not a list';
      $rawMetrics = [];
    }

    // Normalise rankings: must be an array, not a string or other scalar.
    $rawRankings = $plan['rankings'] ?? [];
    if (!is_array($rawRankings)) {
      $errors[] = 'the plan carries a rankings field that is not a list';
      $rawRankings = [];
    }

    // Normalise periods: must be an array, not a string or other scalar - PeriodResolver
    // is strict-typed and would otherwise let a TypeError escape as an uncaught crash.
    $rawPeriods = $plan['periods'] ?? [];
    if (!is_array($rawPeriods)) {
      $errors[] = 'the plan carries a periods field that is not an object';
      $rawPeriods = [];
    }

    $metrics = $this->validateMetrics($rawMetrics, $unsatisfiable);

    if ($metrics === []) {
      // Nothing proposed and nothing malformed: the question simply is not a metric aggregation
      // (a stock level, a list of active promotions, one product's price). The caller degrades
      // instead of refusing. Anything else here IS a refusal, and must stay named.
      //
      // A DECLARED unsupported measure does NOT count here, and that was measured: making it a
      // refusal cost `reviews_avg_bounded` and `return_rate` their 3/3, both falling to 0/3 with
      // no SQL generated at all. Absent from the METRIC catalogue is not unanswerable — the
      // catalogue holds the plan stage's analytic metrics, while the generator answers plenty
      // beyond it straight from the schema. Only a proposal REJECTED here is a refusal.
      $noMetricProposed = $rawMetrics === [] && $errors === [] && $unsatisfiable === [];

      if ($rawMetrics === []) {
        $errors[] = 'the plan carries no metrics';
      }

      return ['plan' => null, 'unsatisfiable' => array_merge($unsatisfiable, $declared), 'errors' => $errors,
              'no_metric_proposed' => $noMetricProposed];
    }

    $kept = array_column($metrics, 'name');
    $rankings = $this->validateRankings($rawRankings, $kept, $unsatisfiable);

    try {
      $periods = PeriodResolver::resolve($rawPeriods);
    } catch (\InvalidArgumentException $e) {
      $errors[] = $e->getMessage();

      return ['plan' => null, 'unsatisfiable' => array_merge($unsatisfiable, $declared), 'errors' => $errors,
              'no_metric_proposed' => false];
    }

    $unsatisfiable = array_merge($unsatisfiable, $declared);
    $dimensions = is_array($plan['dimensions'] ?? null) ? $plan['dimensions'] : [];
    $periods['time_grain'] = self::timeGrain($periods, $dimensions, $rankings);

    // Build the returned plan as an explicit allow-list: only these keys are trusted.
    $returnedPlan = [
      'periods' => $periods,
      'metrics' => $metrics,
      'dimensions' => $dimensions,
      'rankings' => $rankings,
      'filters' => is_array($plan['filters'] ?? null) ? $plan['filters'] : [],
      'complexity' => is_numeric($plan['complexity'] ?? null) ? (int)$plan['complexity'] : 1,
      'unsatisfiable' => $unsatisfiable,
    ];

    return ['plan' => $returnedPlan, 'unsatisfiable' => $unsatisfiable, 'errors' => $errors,
            'no_metric_proposed' => false];
  }

  /**
   * The TEMPORAL grain the comparison is computed at - a different axis from a metric's
   * entity grain, and never the model's to choose.
   *
   * A comparison aggregated once per window destroys the cumulative curve, the month-to-date
   * and the daily drill-down, and none of them can be rebuilt from two totals afterwards. A
   * breakdown already carries its own grain: crossing it with the day would multiply the rows
   * without answering anything the question asked.
   *
   * @param array $periods Resolved periods
   * @param array $dimensions Dimensions the result is broken down by
   * @param array $rankings Validated rankings
   * @return string `day` when the comparison must be computed daily, `window` otherwise
   */
  private static function timeGrain(array $periods, array $dimensions, array $rankings): string
  {
    $compared = ($periods['compare'] ?? PeriodResolver::COMPARE_NONE) !== PeriodResolver::COMPARE_NONE;

    return $compared && $dimensions === [] && $rankings === [] ? 'day' : 'window';
  }

  /**
   * Read what the model itself declared out of catalogue.
   *
   * The validator can only name what was proposed then rejected; the plan prompt asks the
   * model to leave an unknown measure out of `metrics`, so it never reaches the loop below.
   * This channel is the only one that carries it, which is what makes the removal sayable.
   *
   * @param mixed $unsupported Raw `unsupported` field as the model wrote it
   * @return array<int, array{element: string, label: string, reason: string}> Declared removals
   */
  private function collectDeclaredUnsupported(mixed $unsupported): array
  {
    if (!is_array($unsupported)) {
      return [];
    }

    $declared = [];

    foreach ($unsupported as $entry) {
      // Accept both a bare string and a {"name": …} object: the model writes either.
      $raw = is_array($entry) ? ($entry['name'] ?? $entry['element'] ?? '') : $entry;
      $label = is_string($raw) ? trim($raw) : '';

      if ($label === '') {
        continue;
      }

      $declared[] = [
        'element' => 'requested:' . $label,
        'label' => $label,
        'reason' => 'the domain metric catalogue carries no such measure',
      ];
    }

    return $declared;
  }

  /**
   * Keep the metrics the catalogue knows, and stamp each with ITS grain and type.
   *
   * @param array $metrics Metrics as proposed
   * @param array $unsatisfiable Collected removals, by reference
   * @return array<int, array{name: string, grain: string, type: string}> Surviving metrics
   */
  private function validateMetrics(array $metrics, array &$unsatisfiable): array
  {
    $kept = [];

    foreach ($metrics as $metric) {
      if (is_array($metric)) {
        // Guard against a non-string "name" (e.g. a nested array): casting it would emit
        // "Array to string conversion" and silently yield the literal metric "Array".
        $rawName = $metric['name'] ?? '';
        $name = is_string($rawName) ? $rawName : '';
      } else {
        $name = (string)$metric;
      }

      if ($name === '') {
        $unsatisfiable[] = [
          'element' => 'metric:<unnamed>',
          'label' => '',
          'reason' => 'the plan carries a metric with no name',
        ];

        continue;
      }

      if (!isset($this->catalog[$name])) {
        $unsatisfiable[] = [
          'element' => 'metric:' . $name,
          'label' => $name,
          'reason' => 'unknown to the domain metric catalogue',
        ];

        continue;
      }

      // The catalogue wins over what the model declared.
      $kept[] = [
        'name' => $name,
        'grain' => $this->catalog[$name]['grain'],
        'type' => $this->catalog[$name]['type'],
      ];
    }

    return $kept;
  }

  /**
   * A ranking on a metric that did not survive would rank nothing. Rebuild with allow-list.
   *
   * @param array $rankings Rankings as proposed
   * @param array<int, string> $keptMetrics Names of the surviving metrics
   * @param array $unsatisfiable Collected removals, by reference
   * @return array<int, array> Surviving rankings
   */
  private function validateRankings(array $rankings, array $keptMetrics, array &$unsatisfiable): array
  {
    $kept = [];

    foreach ($rankings as $ranking) {
      // A bare string/int ranking entry (not an array) has no "metric" to read - mirror
      // how an unnamed metric is recorded, instead of collapsing to an empty name.
      $rawMetric = is_array($ranking) ? ($ranking['metric'] ?? '') : null;
      $metric = is_string($rawMetric) ? $rawMetric : '';

      if ($metric === '' || !in_array($metric, $keptMetrics, true)) {
        $unsatisfiable[] = [
          'element' => 'ranking:' . ($metric !== '' ? $metric : '<unnamed>'),
          'label' => $metric,
          'reason' => $metric === ''
            ? 'the plan carries a ranking with no metric name'
            : 'ranks a metric that is not in the plan',
        ];

        continue;
      }

      // Rebuild with allow-list: exactly these four keys.
      $direction = ($ranking['direction'] ?? '') === 'bottom' ? 'bottom' : 'top';
      $n = is_numeric($ranking['n'] ?? null) ? (int)$ranking['n'] : 3;
      $partitionBy = is_string($ranking['partition_by'] ?? null) && $ranking['partition_by'] !== '' ? $ranking['partition_by'] : null;

      $kept[] = [
        'metric' => $metric,
        'direction' => $direction,
        'n' => $n,
        'partition_by' => $partitionBy,
      ];
    }

    return $kept;
  }
}
