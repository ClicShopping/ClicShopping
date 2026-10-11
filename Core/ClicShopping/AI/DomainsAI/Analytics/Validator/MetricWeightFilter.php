<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Validator;

/**
 * MetricWeightFilter
 *
 * Deterministic post-generation check of the domain contract `weighted_by`: a plan metric whose
 * catalogue entry names a weight column needs one SELECT that reads that column and keeps the
 * rows of weight 1. A SQL that never reads it, or filters every SELECT down to other weights,
 * returns plausible but wrong figures. A metric counting ONE population (`population_weight`) may
 * skip the column, but the rows it reads must not have been filtered down to other weights.
 * Schema-based, model-independent; reports, never rewrites.
 */
final class MetricWeightFilter
{
  /**
   * @param string $sql Executable SQL
   * @param array $planMetrics Plan metrics (`[['name' => …], …]`)
   * @param array $catalog Domain metric catalogue (`name => ['weighted_by' => column, 'population_weight' => int, …]`)
   * @param array<int, string> $pinColumns Domain columns that select a population by value
   * @param bool $planFilters Whether the plan carries an explicit filter the question stated
   * @return array<string, string> Violations, metric name => weight column it fails to keep
   */
  public static function violations(string $sql, array $planMetrics, array $catalog, array $pinColumns = [], bool $planFilters = false): array
  {
    // A population the question named and the SQL pins by value replaces the weight.
    if ($planFilters && self::pinsPopulation($sql, $pinColumns)) {
      return [];
    }

    $violations = [];
    $parsed = null;

    foreach ($planMetrics as $metric) {
      $name = (string)($metric['name'] ?? '');
      $column = (string)($catalog[$name]['weighted_by'] ?? '');

      if ($column === '') {
        continue;
      }

      $parsed ??= SqlSelectBlocks::parse($sql);
      $weight = $catalog[$name]['population_weight'] ?? null;

      $kept = is_int($weight)
        ? self::populationKept($parsed, $column, $weight, [$name, ...($catalog[$name]['aliases'] ?? [])])
        : self::keepsWeightOne($parsed, $column);

      if (!$kept) {
        $violations[$name] = $column;
      }
    }

    return $violations;
  }

  /**
   * True when one SELECT reads the column and none of its filters (FROM/ON/WHERE/HAVING) is
   * only predicates excluding weight 1. A read in the select list never filters rows.
   *
   * @param array $parsed SqlSelectBlocks::parse() output
   * @param string $column Weight column name
   * @return bool
   */
  private static function keepsWeightOne(array $parsed, string $column): bool
  {
    $pattern = '/\b' . preg_quote($column, '/') . '\b\s*(NOT\s+IN|IN|<>|!=|>=|<=|=|>|<)?\s*(\([^)]*\)|-\s*\d+|\d+)?/i';

    if (preg_match_all($pattern, $parsed['masked'], $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === 0) {
      return false;
    }

    foreach ($parsed['specs'] as $spec) {
      $reads = false;
      $filters = [];

      foreach ($matches as $m) {
        $pos = $m[0][1];

        if ($parsed['blockAt'][$pos] !== $spec['block'] || $pos < $spec['select'] || $pos >= $spec['end']) {
          continue;
        }

        $reads = true;

        if ($pos > $spec['from'] && ($m[1][0] ?? '') !== '' && ($m[2][0] ?? '') !== '') {
          $filters[] = self::admitsOne(strtoupper((string)preg_replace('/\s+/', ' ', $m[1][0])), $m[2][0]);
        }
      }

      if ($reads && ($filters === [] || in_array(true, $filters, true))) {
        return true;
      }
    }

    return false;
  }

  /**
   * Follows each column aliased to the metric back through derived tables and CTEs: the rows it
   * counts keep weight v unless every source on the way filters the weight column to other values.
   *
   * @param array $parsed SqlSelectBlocks::parse() output
   * @param string $column Weight column name
   * @param int $weight Weight of the population the metric counts
   * @param array<int, string> $names Metric name and its declared aliases
   * @return bool
   */
  private static function populationKept(array $parsed, string $column, int $weight, array $names): bool
  {
    $masked = $parsed['masked'];
    $quoted = implode('|', array_map(static fn(string $n): string => preg_quote($n, '/'), $names));

    if (preg_match_all(PlanShapeFilter::metricAliasPattern($quoted), $masked, $aliases, PREG_OFFSET_CAPTURE) === 0) {
      return true;
    }

    foreach ($aliases[0] as [, $pos]) {
      foreach ($parsed['specs'] as $spec) {
        if ($parsed['blockAt'][$pos] !== $spec['block'] || $pos < $spec['select'] || $pos >= $spec['from']) {
          continue;
        }

        // A scalar subquery inside the aliased expression is the metric's own source.
        $start = self::expressionStart($masked, $pos, $spec['select']);
        $own = array_filter($parsed['specs'], static fn(array $s): bool => $s['block'] > $start && $s['block'] < $pos);
        $sources = $own !== [] ? $own : [$spec];
        $kept = false;

        foreach ($sources as $source) {
          $kept = $kept || self::specKeeps($parsed, $source, $column, $weight, 0);
        }

        if (!$kept) {
          return false;
        }
      }
    }

    return true;
  }

  /**
   * @param string $masked SQL with literals blanked
   * @param int $alias Position of the AS keyword
   * @param int $select Position of the SELECT keyword of its spec
   * @return int Position where the aliased select-list item starts
   */
  private static function expressionStart(string $masked, int $alias, int $select): int
  {
    $depth = 0;

    for ($i = $alias - 1; $i > $select + 6; $i--) {
      $char = $masked[$i];

      if ($char === ')') {
        $depth++;
      } elseif ($char === '(') {
        $depth--;
      } elseif ($char === ',' && $depth === 0) {
        return $i;
      }
    }

    return $select + 6;
  }

  /**
   * @param array $parsed SqlSelectBlocks::parse() output
   * @param array $spec One SELECT spec
   * @param string $column Weight column name
   * @param int $weight Weight to keep
   * @param int $depth Recursion guard
   * @return bool Whether rows of that weight reach this SELECT
   */
  private static function specKeeps(array $parsed, array $spec, string $column, int $weight, int $depth): bool
  {
    $masked = $parsed['masked'];
    $pattern = '/\b' . preg_quote($column, '/') . '\b\s*(NOT\s+IN|IN|<>|!=|>=|<=|=|>|<)\s*(\([^)]*\)|-\s*\d+|\d+)/i';
    $filters = [];

    if (preg_match_all($pattern, $masked, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) > 0) {
      foreach ($matches as $m) {
        $pos = $m[0][1];

        if ($parsed['blockAt'][$pos] === $spec['block'] && $pos > $spec['from'] && $pos < $spec['end']) {
          $filters[] = self::admits(strtoupper((string)preg_replace('/\s+/', ' ', $m[1][0])), $m[2][0], $weight);
        }
      }
    }

    if ($filters !== []) {
      return in_array(true, $filters, true);
    }

    $source = self::sourceBlock($masked, $spec['from']);

    if ($source === null || $depth > 8) {
      return true;
    }

    foreach ($parsed['specs'] as $member) {
      if ($member['block'] === $source && self::specKeeps($parsed, $member, $column, $weight, $depth + 1)) {
        return true;
      }
    }

    return false;
  }

  /**
   * @param string $masked SQL with literals blanked
   * @param int $from Position of the FROM keyword
   * @return int|null Block id of the first FROM item when it is a derived table or a CTE, null for a base table
   */
  private static function sourceBlock(string $masked, int $from): ?int
  {
    if (preg_match('/\G\s*(\(|`?(\w+)`?)/', $masked, $m, PREG_OFFSET_CAPTURE, $from + 4) !== 1) {
      return null;
    }

    if ($m[1][0] === '(') {
      return $m[1][1];
    }

    if (preg_match('/\b' . preg_quote($m[2][0], '/') . '\s+AS\s*(\()\s*(?:SELECT|WITH)\b/i', $masked, $cte, PREG_OFFSET_CAPTURE) === 1) {
      return $cte[1][1];
    }

    return null;
  }

  /**
   * True when a declared column is compared to a literal (= or IN), never to another column.
   *
   * @param string $sql Executable SQL
   * @param array<int, string> $pinColumns Domain columns that select a population by value
   * @return bool
   */
  private static function pinsPopulation(string $sql, array $pinColumns): bool
  {
    foreach ($pinColumns as $column) {
      if (preg_match('/\b' . preg_quote($column, '/') . '\b\s*(=\s*(\'[^\']*\'|\d+)|IN\s*\()/i', $sql) === 1) {
        return true;
      }
    }

    return false;
  }

  /**
   * @param string $operator Normalised comparison operator
   * @param string $operand Literal or parenthesised list
   * @return bool Whether weight 1 satisfies the predicate
   */
  private static function admitsOne(string $operator, string $operand): bool
  {
    return self::admits($operator, $operand, 1);
  }

  /**
   * @param string $operator Normalised comparison operator
   * @param string $operand Literal or parenthesised list
   * @param int $weight Weight tested
   * @return bool Whether that weight satisfies the predicate
   */
  private static function admits(string $operator, string $operand, int $weight): bool
  {
    if ($operator === 'IN' || $operator === 'NOT IN') {
      $values = array_map(static fn(string $v): int => (int)str_replace(' ', '', $v), explode(',', trim($operand, '() ')));

      return in_array($weight, $values, true) === ($operator === 'IN');
    }

    $value = (int)str_replace(' ', '', $operand);

    return match ($operator) {
      '<>', '!=' => $weight !== $value,
      '=' => $weight === $value,
      '>' => $weight > $value,
      '>=' => $weight >= $value,
      '<' => $weight < $value,
      '<=' => $weight <= $value,
      default => true,
    };
  }
}
