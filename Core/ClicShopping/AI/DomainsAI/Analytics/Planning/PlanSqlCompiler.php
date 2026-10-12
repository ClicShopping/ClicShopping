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
 * PlanSqlCompiler
 *
 * Builds the SQL of a validated analysis plan from the domain's declarations instead of letting the
 * model write it: the plan keeps the intent, the shape becomes deterministic. Slice 1 compiles
 * additive metrics of the signed event axis, broken down by calendar units and by columns of one
 * axis projection, over the window PeriodResolver computed. Any other plan returns null and the
 * caller falls back on the model.
 *
 * A metric is compilable when its catalogue entry declares `axis`: projection => measure column,
 * in order of preference. Output keeps {{table_prefix}} and {{language_id}} for PromptPlaceholders.
 *
 * @package ClicShopping\AI\DomainsAI\Analytics\Planning
 */
final class PlanSqlCompiler
{
  /** Calendar unit => period label over the event date; quarter and week carry their year. */
  private const CALENDAR_EXPRESSIONS = [
    'year' => 'YEAR(ev.event_date)',
    'quarter' => "CONCAT(YEAR(ev.event_date), '-Q', QUARTER(ev.event_date))",
    'month' => "DATE_FORMAT(ev.event_date, '%Y-%m')",
    'week' => "DATE_FORMAT(ev.event_date, '%x-W%v')",
    'day' => 'DATE(ev.event_date)',
  ];

  /**
   * @param array $plan Validated analysis plan (AnalysisPlanValidator output)
   * @param array $catalog Domain metric catalogue, name => entry with an optional `axis`
   * @param array{axis: array, projections: array<string, array{grain: string, columns: array<string, string>, joins?: array<int, string>}>} $eventAxis Domain event axis declaration
   * @return string|null SQL with placeholders left, or null when the plan is outside the compiled slices
   */
  public static function compile(array $plan, array $catalog, array $eventAxis): ?string
  {
    $periods = $plan['periods'] ?? [];
    $metrics = $plan['metrics'] ?? [];

    if ($metrics === [] || !empty($plan['rankings']) || !empty($plan['filters']) || !empty($plan['unsatisfiable'])
      || ($periods['compare'] ?? PeriodResolver::COMPARE_NONE) !== PeriodResolver::COMPARE_NONE
      || ($periods['time_grain'] ?? 'window') !== 'window'
      || !empty($periods['set_bounded']) || !empty($periods['period_missing'])
      || empty($periods['current']['to'])) {
      return null;
    }

    $calendar = [];
    $columns = [];

    foreach ($plan['dimensions'] ?? [] as $dimension) {
      $dimension = (string)$dimension;

      if (isset(self::CALENDAR_EXPRESSIONS[$dimension])) {
        $calendar[] = $dimension;
      } elseif (preg_match('/^\w+$/', $dimension) === 1) {
        $columns[] = $dimension;
      } else {
        return null;
      }
    }

    $projection = self::sharedProjection($metrics, $catalog, $eventAxis['projections'] ?? [], $columns);

    if ($projection === null) {
      return null;
    }

    $select = [];

    foreach ($calendar as $unit) {
      $select[] = self::CALENDAR_EXPRESSIONS[$unit] . ' AS ' . $unit;
    }

    foreach ($columns as $column) {
      $select[] = 'ev.' . $column;
    }

    $groupBy = [...$calendar, ...array_map(static fn(string $c): string => 'ev.' . $c, $columns)];

    foreach ($metrics as $metric) {
      $name = (string)$metric['name'];
      $sum = 'SUM(ev.sign * ev.' . $catalog[$name]['axis'][$projection] . ')';
      $select[] = ($catalog[$name]['type'] === MetricType::AMOUNT ? "ROUND({$sum}, 2)" : $sum) . ' AS ' . $name;
    }

    $where = [];

    if (!empty($periods['current']['from'])) {
      $where[] = "ev.event_date >= '" . self::date($periods['current']['from']) . "'";
    }

    $where[] = "ev.event_date < '" . self::date($periods['current']['to'], '+1 day') . "'";

    $sql = 'SELECT ' . implode(', ', $select)
      . ' FROM (' . EventAxisSql::render($eventAxis['axis'], $eventAxis['projections'][$projection]) . ') ev'
      . ' WHERE ' . implode(' AND ', $where);

    if ($groupBy !== []) {
      $sql .= ' GROUP BY ' . implode(', ', $groupBy) . ' ORDER BY ' . implode(', ', $groupBy);
    }

    return $sql;
  }

  /**
   * First projection, in the first metric's order of preference, that every metric is bound to and
   * that carries every non-calendar dimension as a column.
   *
   * @param array $metrics Plan metrics
   * @param array $catalog Domain metric catalogue
   * @param array $projections Event axis projections
   * @param array<int, string> $columns Non-calendar dimensions
   * @return string|null Projection name, or null when none serves the whole plan
   */
  private static function sharedProjection(array $metrics, array $catalog, array $projections, array $columns): ?string
  {
    $candidates = null;

    foreach ($metrics as $metric) {
      $entry = $catalog[(string)($metric['name'] ?? '')] ?? [];

      if (($entry['aggregation'] ?? '') !== MetricAggregation::ADDITIVE || !empty($metric['series_unit']) || empty($entry['axis'])) {
        return null;
      }

      $bound = array_keys($entry['axis']);
      $candidates = $candidates === null ? $bound : array_values(array_intersect($candidates, $bound));
    }

    foreach ($candidates ?? [] as $name) {
      if (isset($projections[$name]) && array_diff($columns, array_keys($projections[$name]['columns'])) === []) {
        return $name;
      }
    }

    return null;
  }

  /**
   * @param string $date Y-m-d bound resolved by PeriodResolver
   * @param string $shift Optional relative shift
   * @return string Y-m-d, re-formatted so nothing but a date reaches the SQL
   * @throws \InvalidArgumentException When the bound is not a date
   */
  private static function date(string $date, string $shift = ''): string
  {
    $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

    if ($parsed === false) {
      throw new \InvalidArgumentException('The plan carries an unreadable period bound');
    }

    return ($shift === '' ? $parsed : $parsed->modify($shift))->format('Y-m-d');
  }
}
