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
 * model write it: the plan keeps the intent, the shape becomes deterministic. Compiles additive
 * metrics and ratios of sums of the signed event axis, with their companions, broken down by calendar units, columns of one axis projection,
 * a grain entity (key + name) and the levels of the domain hierarchy, optionally restricted to one
 * named node's sub-tree, over the window PeriodResolver computed. Successive calendar periods are
 * read on a dense axis, each plan metric carrying its variation against the period before. Any other
 * plan returns null and the caller falls back on the model.
 *
 * A metric is compilable when its catalogue entry declares `axis`: projection => what it sums, in
 * order of preference - an expression over the projection's columns and `measures`, or for a ratio
 * {numerator, denominator, scale?}, each summed and divided after. A companion is a catalogue
 * metric bound to the same projection or one of its `measures`; one that resolves nowhere leaves
 * the plan to the model. Output keeps {{table_prefix}} and {{language_id}} for PromptPlaceholders.
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
   * @param array{axis: array, projections: array<string, array{grain: string, columns: array<string, string>, joins?: array<int, string>, measures?: array<string, string>}>, hierarchy?: array} $eventAxis Domain event axis declaration, with its optional hierarchy (see HierarchySql)
   * @param array<string, array<int, string>> $grainKeys Grain => [key column, name column]: a plan dimension named after a grain
   * @return string|null SQL with placeholders left, or null when the plan is outside the compiled slices
   */
  public static function compile(array $plan, array $catalog, array $eventAxis, array $grainKeys = []): ?string
  {
    $periods = $plan['periods'] ?? [];
    $metrics = $plan['metrics'] ?? [];
    $hierarchy = $eventAxis['hierarchy'] ?? null;
    $levels = $hierarchy['dimensions'] ?? [];

    if ($metrics === [] || !empty($plan['rankings']) || !empty($plan['unsatisfiable'])
      || ($periods['compare'] ?? PeriodResolver::COMPARE_NONE) !== PeriodResolver::COMPARE_NONE
      || ($periods['time_grain'] ?? 'window') !== 'window'
      || !empty($periods['set_bounded']) || !empty($periods['period_missing'])
      || empty($periods['current']['to'])) {
      return null;
    }

    $calendar = [];
    $members = [];
    $columns = [];
    $entities = [];
    $asked = [];

    foreach ($plan['dimensions'] ?? [] as $dimension) {
      $dimension = (string)$dimension;

      if (isset(self::CALENDAR_EXPRESSIONS[$dimension])) {
        $calendar[] = $dimension;
      } elseif (isset($levels[$dimension])) {
        $asked[$levels[$dimension]] = true;
      } elseif (count($grainKeys[$dimension] ?? []) === 2) {
        $entities[] = $grainKeys[$dimension];
      } elseif (preg_match('/^\w+$/', $dimension) === 1) {
        $columns[] = $dimension;
      } else {
        return null;
      }
    }

    $filters = is_array($plan['filters'] ?? null) ? $plan['filters'] : [];

    // ponytail: only the hierarchy filter compiles; any other one stays with the model (PLAN-COMPILER-FILTERS1).
    if (array_diff(array_keys($filters), array_keys($levels)) !== []) {
      return null;
    }

    $treeUsed = $asked !== [] || $filters !== [];
    $needed = [...$columns, ...array_merge(...$entities)];
    $projection = self::sharedProjection($metrics, $catalog, $eventAxis['projections'] ?? [], $needed, $treeUsed ? (string)$eventAxis['axis']['lines']['grain'] : null);

    if ($projection === null) {
      return null;
    }

    $body = $eventAxis['projections'][$projection];
    $figures = self::figures($metrics, $catalog, $projection, $body);

    if ($figures === null) {
      return null;
    }
    $select = [];
    $groupBy = [];

    foreach ($calendar as $unit) {
      $select[] = self::CALENDAR_EXPRESSIONS[$unit] . ' AS ' . $unit;
      $groupBy[] = $unit;
    }

    if ($treeUsed) {
      $line = $eventAxis['axis']['lines'];
      $item = $line['alias'] . '.' . $line['item'];

      // Root beside a path takes its own name: the root alias alone means "one row per root".
      foreach ([HierarchySql::ROOT, HierarchySql::PATH] as $level) {
        if (isset($asked[$level])) {
          $alias = $level === HierarchySql::ROOT && isset($asked[HierarchySql::PATH])
            ? $hierarchy['root_beside_path'] : $hierarchy['aliases'][$level];
          $body['columns'][$alias] = HierarchySql::member($hierarchy, $level, $item);
          $select[] = 'ev.' . $alias;
          $groupBy[] = 'ev.' . $alias;
          $members[] = $alias;
        }
      }

      foreach ($filters as $value) {
        $condition = is_scalar($value) ? HierarchySql::within($hierarchy, $item, (string)$value) : null;

        if ($condition === null) {
          return null;
        }

        $body['conditions'][] = $condition;
      }
    }

    foreach ($entities as [$key, $name]) {
      $select[] = 'ev.' . $key;
      $select[] = "MAX(ev.{$name}) AS {$name}";
      $groupBy[] = 'ev.' . $key;
      $members = [...$members, $key, $name];
    }

    foreach ($columns as $column) {
      $select[] = 'ev.' . $column;
      $groupBy[] = 'ev.' . $column;
      $members[] = $column;
    }

    foreach ($figures as $name => $figure) {
      $select[] = $figure . ' AS ' . $name;
    }

    $where = [];

    if (!empty($periods['current']['from'])) {
      $where[] = "ev.event_date >= '" . self::date($periods['current']['from']) . "'";
    }

    $where[] = "ev.event_date < '" . self::date($periods['current']['to'], '+1 day') . "'";

    $sql = 'SELECT ' . implode(', ', $select)
      . ' FROM (' . EventAxisSql::render($eventAxis['axis'], $body) . ') ev'
      . ' WHERE ' . implode(' AND ', $where)
      . ($groupBy !== [] ? ' GROUP BY ' . implode(', ', $groupBy) : '');

    // Periods read in order; members of a category or a product by the first measure, largest first.
    $orderBy = $treeUsed || $entities !== [] ? [...$calendar, (string)$metrics[0]['name'] . ' DESC'] : $groupBy;

    if ($calendar !== []) {
      $names = array_unique(array_map(static fn(array $m): string => (string)$m['name'], $metrics));
      $successive = self::successive($sql, $calendar, $members, $figures, $names, $catalog, $periods['current'], $orderBy);

      return $successive === null ? null : ($treeUsed ? HierarchySql::cte($hierarchy) . ', ' : 'WITH ') . $successive;
    }

    return ($treeUsed ? HierarchySql::cte($hierarchy) . ' ' : '') . $sql
      . ($groupBy !== [] ? ' ORDER BY ' . implode(', ', $orderBy) : '');
  }

  /**
   * Successive calendar periods on a dense axis: every period of the window is a row, an empty one
   * at 0, and each plan metric carries its variation against the period just before (NULL first).
   * Crossed with members, a member keeps its active periods and the one right after each: the fall to
   * 0 shows once, and the result stays within twice the grouped rows.
   *
   * @param string $aggregate The grouped query, without ORDER BY
   * @param array<int, string> $calendar Calendar units, plan order
   * @param array<int, string> $members Non-calendar output columns
   * @param array<string, string> $figures Compiled figures, name => aggregate
   * @param array<int, string> $planned Plan metric names: the ones that carry a variation
   * @param array $catalog Domain metric catalogue
   * @param array $window Current window {from, to}
   * @param array<int, string> $orderBy Order of the grouped query
   * @return string|null CTE list and final SELECT, to follow WITH; null without a first day
   */
  private static function successive(string $aggregate, array $calendar, array $members, array $figures, array $planned, array $catalog, array $window, array $orderBy): ?string
  {
    // ponytail: an all-time window has no first day to start the axis from; it stays with the model.
    if (empty($window['from'])) {
      return null;
    }

    $start = self::date((string)$window['from']);
    $days = (int)(new \DateTimeImmutable($start))->diff(new \DateTimeImmutable(self::date((string)$window['to'])))->days;
    $units = array_map(static fn(string $u): string => self::CALENDAR_EXPRESSIONS[$u] . ' AS ' . $u, $calendar);

    $ctes = ["agg AS ({$aggregate})",
      'cal AS (SELECT DISTINCT ' . implode(', ', $units) . " FROM (SELECT DATE_ADD('{$start}', INTERVAL seq DAY) AS event_date FROM seq_0_to_{$days}) ev)"];
    $from = 'cal s';
    $on = array_map(static fn(string $u): string => "a.{$u} = s.{$u}", $calendar);
    $select = array_map(static fn(string $u): string => 's.' . $u, $calendar);

    if ($members !== []) {
      $ctes[] = 'mem AS (SELECT DISTINCT ' . implode(', ', $members) . ' FROM agg)';
      $from .= ' CROSS JOIN mem m';

      foreach ($members as $member) {
        // A member filed nowhere is NULL: matched null-safe.
        $on[] = "a.{$member} <=> m.{$member}";
        $select[] = 'm.' . $member;
      }
    }

    $over = ' OVER (' . ($members !== [] ? 'PARTITION BY m.' . implode(', m.', $members) . ' ' : '')
      . 'ORDER BY s.' . implode(', s.', $calendar) . ')';

    foreach ($figures as $name => $figure) {
      // Only an absent period counts 0: a NULL the period computed (rate, unknown cost) stays NULL.
      $rate = ($catalog[$name]['type'] ?? '') === MetricType::RATE;
      $value = $rate ? "a.{$name}" : "IF(a.{$calendar[0]} IS NULL, 0, a.{$name})";
      $select[] = "{$value} AS {$name}";

      if (in_array($name, $planned, true)) {
        $previous = "LAG({$value}){$over}";
        $select[] = (MetricType::variesInPoints((string)($catalog[$name]['type'] ?? ''))
          ? "ROUND({$value} - {$previous}, 2)"
          : "ROUND(({$value} - {$previous}) / NULLIF({$previous}, 0) * 100, 2)") . " AS {$name}_variation";
      }
    }

    $join = " FROM {$from} LEFT JOIN agg a ON " . implode(' AND ', $on);
    $order = ' ORDER BY ' . implode(', ', array_map(static fn(string $o): string => str_replace('ev.', '', $o), $orderBy));

    if ($members === []) {
      return implode(', ', $ctes) . ' SELECT ' . implode(', ', $select) . $join . $order;
    }

    // Filtered outside the grid: a WHERE beside LAG would run before it.
    $seen = "a.{$calendar[0]} IS NOT NULL";
    $output = array_map(static fn(string $column): string => (string)preg_replace('/^.* AS |^[sm]\./', '', $column), $select);
    $ctes[] = 'grid AS (SELECT ' . implode(', ', $select) . ", {$seen} AS seen, LAG({$seen}){$over} AS seen_before{$join})";

    return implode(', ', $ctes) . ' SELECT ' . implode(', ', $output) . ' FROM grid WHERE seen OR seen_before' . $order;
  }

  /**
   * First projection, in the first metric's order of preference, that every metric is bound to and
   * that carries every non-calendar dimension as a column - at the lines' grain when the hierarchy is read.
   *
   * @param array $metrics Plan metrics
   * @param array $catalog Domain metric catalogue
   * @param array $projections Event axis projections
   * @param array<int, string> $columns Non-calendar dimensions
   * @param string|null $lineGrain The lines' grain when the hierarchy is read off each line's item
   * @return string|null Projection name, or null when none serves the whole plan
   */
  private static function sharedProjection(array $metrics, array $catalog, array $projections, array $columns, ?string $lineGrain): ?string
  {
    $candidates = null;

    foreach ($metrics as $metric) {
      $entry = $catalog[(string)($metric['name'] ?? '')] ?? [];

      if (!in_array($entry['aggregation'] ?? '', [MetricAggregation::ADDITIVE, MetricAggregation::RATIO_OF_SUMS], true)
        || !empty($metric['series_unit']) || empty($entry['axis'])) {
        return null;
      }

      $bound = array_keys($entry['axis']);
      $candidates = $candidates === null ? $bound : array_values(array_intersect($candidates, $bound));
    }

    foreach ($candidates ?? [] as $name) {
      if (isset($projections[$name]) && array_diff($columns, array_keys($projections[$name]['columns'])) === []
        && ($lineGrain === null || $projections[$name]['grain'] === $lineGrain)) {
        return $name;
      }
    }

    return null;
  }

  /**
   * Plan metrics then their companions, name => compiled aggregate, in that order; null when one
   * does not resolve on the projection.
   *
   * @param array $metrics Plan metrics
   * @param array $catalog Domain metric catalogue
   * @param string $projection Chosen projection name
   * @param array $body Its declaration (columns, measures)
   * @return array<string, string>|null
   */
  private static function figures(array $metrics, array $catalog, string $projection, array $body): ?array
  {
    $names = array_map(static fn(array $m): string => (string)$m['name'], $metrics);

    foreach ($names as $name) {
      $names = [...$names, ...($catalog[$name]['companions'] ?? [])];
    }

    $figures = [];

    foreach (array_unique($names) as $name) {
      $bound = $catalog[$name]['axis'][$projection] ?? null;

      if ($bound === null && isset($body['measures'][$name])) {
        // ponytail: a measure is rounded as an amount; declare its type the day a count is one.
        $figures[$name] = 'ROUND(' . self::sum($name, $body) . ', 2)';
        continue;
      }

      if ($bound === null) {
        return null;
      }

      if (is_array($bound)) {
        // A rate over a base that nets to zero or below is undefined, not infinite.
        $ratio = self::sum((string)$bound['numerator'], $body) . ' / NULLIF(GREATEST(' . self::sum((string)$bound['denominator'], $body) . ', 0), 0)'
          . (isset($bound['scale']) ? ' * ' . (int)$bound['scale'] : '');
        $figures[$name] = "ROUND({$ratio}, 2)";
        continue;
      }

      $sum = self::sum((string)$bound, $body);
      $figures[$name] = ($catalog[$name]['type'] ?? '') === MetricType::AMOUNT ? "ROUND({$sum}, 2)" : $sum;
    }

    return $figures;
  }

  /**
   * Signed sum of an expression over the projection: each column read off `ev`, each measure
   * expanded to its own expression first.
   *
   * @param string $expression Column, measure or expression over them
   * @param array $body Projection declaration
   * @return string
   */
  private static function sum(string $expression, array $body): string
  {
    $expression = (string)($body['measures'][$expression] ?? $expression);

    foreach ($body['measures'] ?? [] as $measure => $definition) {
      $expression = (string)preg_replace('/\b' . preg_quote((string)$measure, '/') . '\b/', '(' . $definition . ')', $expression);
    }

    $columns = implode('|', array_map(static fn($c): string => preg_quote((string)$c, '/'), array_keys($body['columns'])));
    $expression = (string)preg_replace('/(?<![\w.])(' . $columns . ')\b/', 'ev.$1', $expression);

    return preg_match('/^ev\.\w+$/', $expression) === 1 ? "SUM(ev.sign * {$expression})" : "SUM(ev.sign * ({$expression}))";
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
