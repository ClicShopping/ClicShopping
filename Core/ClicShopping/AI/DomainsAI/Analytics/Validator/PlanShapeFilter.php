<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Validator;

/**
 * PlanShapeFilter
 *
 * Deterministic post-generation check of the result SHAPE the plan binds: a metric's grain is the
 * level it is computed at, never a breakdown of the final rows, and a companion the catalogue
 * declares as a metric of its own is always selected. Model-independent; reports, never rewrites.
 */
final class PlanShapeFilter
{
  /**
   * Grain key columns the top-level GROUP BY reads, or the top-level select list returns as a bare
   * column, while the plan asks for no such breakdown.
   *
   * @param string $sql Executable SQL
   * @param array $plan Validated analysis plan
   * @param array<string, array<int, string>> $grainKeys Domain declaration, grain => key columns
   * @return array<int, string> Violations, the key columns found
   */
  public static function unplannedBreakdowns(string $sql, array $plan, array $grainKeys): array
  {
    // A ranking returns the grain's own rows; a filter does only when it names that grain.
    if ($grainKeys === [] || !empty($plan['rankings'])) {
      return [];
    }

    $dimensions = strtolower(implode(' ', array_filter($plan['dimensions'] ?? [], 'is_string')));
    $filterKeys = strtolower(implode(' ', array_map('strval', array_keys(is_array($plan['filters'] ?? null) ? $plan['filters'] : []))));
    [$groupBy, $selectList] = self::topLevelClauses($sql);

    $violations = [];

    foreach ($plan['metrics'] ?? [] as $metric) {
      $grain = strtolower((string)($metric['grain'] ?? ''));

      if ($grain === '' || str_contains($dimensions, $grain) || str_contains($filterKeys, $grain)) {
        continue;
      }

      foreach ($grainKeys[$grain] ?? [] as $column) {
        $c = preg_quote($column, '/');

        if (preg_match('/\b' . $c . '\b/i', $groupBy) === 1
          || preg_match('/(?:^|,)\s*(?:\w+\.)?' . $c . '\s*(?:AS\s+\w+\s*)?(?=,|$)/i', $selectList) === 1) {
          $violations[] = $column;
        }
      }
    }

    return array_values(array_unique($violations));
  }

  /**
   * Companions that are catalogue metrics of the SAME grain (the same measure read another way),
   * and that no column of the SQL is aliased to. A dense day axis renames its columns, so it is skipped.
   *
   * @param string $sql Executable SQL
   * @param array $plan Validated analysis plan
   * @param array $catalog Domain metric catalogue (`name => ['companions' => […], …]`)
   * @return array<int, string> Violations, the missing companion names
   */
  public static function missingCompanions(string $sql, array $plan, array $catalog): array
  {
    if (($plan['periods']['time_grain'] ?? 'window') === 'day') {
      return [];
    }

    $missing = [];

    foreach ($plan['metrics'] ?? [] as $metric) {
      $entry = $catalog[(string)($metric['name'] ?? '')] ?? [];

      foreach ($entry['companions'] ?? [] as $companion) {
        // A window affix (current_, _previous) still names the companion; any other suffix names another column.
        if (($catalog[$companion]['grain'] ?? null) === ($entry['grain'] ?? '') && preg_match('/\bAS\s+`?(?:\w+_)?' . preg_quote($companion, '/') . '(?:_current|_previous)?\b/i', $sql) !== 1) {
          $missing[] = $companion;
        }
      }
    }

    return array_values(array_unique($missing));
  }

  /**
   * Plan metrics that no column of the SQL is aliased to, under their name or an alias the
   * catalogue declares (`aliases`): a metric merged into another or dropped is reported, never served.
   * A dense day axis renames its columns, so it is skipped.
   *
   * @param string $sql Executable SQL
   * @param array $plan Validated analysis plan
   * @param array $catalog Domain metric catalogue (`name => ['aliases' => […], …]`)
   * @return array<int, string> Violations, the missing metric names
   */
  public static function missingMetrics(string $sql, array $plan, array $catalog): array
  {
    if (($plan['periods']['time_grain'] ?? 'window') === 'day') {
      return [];
    }

    $missing = [];

    foreach ($plan['metrics'] ?? [] as $metric) {
      $name = (string)($metric['name'] ?? '');

      if ($name === '') {
        continue;
      }

      $names = implode('|', array_map(static fn(string $n): string => preg_quote($n, '/'), [$name, ...($catalog[$name]['aliases'] ?? [])]));

      if (preg_match(self::metricAliasPattern($names), $sql) !== 1) {
        $missing[] = $name;
      }
    }

    return array_values(array_unique($missing));
  }

  /**
   * A window prefix or suffix (current, previous) still names the metric; any other affix names
   * another column (cancelled_or_refunded_orders is neither metric).
   *
   * @param string $names Alternation of quoted metric names
   * @return string
   */
  public static function metricAliasPattern(string $names): string
  {
    return '/\bAS\s+[`"]?(?:(?:current|previous)(?:_year|_period)?_)?(?:' . $names . ')(?:_current|_previous)?\b/i';
  }

  /**
   * @param string $sql Executable SQL
   * @return array{0: string, 1: string} Top-level GROUP BY text and select list text, '' when none
   */
  private static function topLevelClauses(string $sql): array
  {
    $parsed = SqlSelectBlocks::parse($sql);
    $masked = $parsed['masked'];
    $out = '';
    $selectList = '';

    foreach ($parsed['specs'] as $spec) {
      if ($spec['block'] === 0 && $spec['from'] !== null) {
        $selectList .= ',' . substr($masked, $spec['select'] + 6, $spec['from'] - $spec['select'] - 6);
      }
    }

    preg_match_all('/\bGROUP\s+BY\b/i', $masked, $matches, PREG_OFFSET_CAPTURE);

    foreach ($matches[0] as [$word, $pos]) {
      if ($parsed['blockAt'][$pos] !== 0) {
        continue;
      }

      $rest = substr($masked, $pos + strlen($word));
      $out .= ' ' . (preg_split('/\b(ORDER\s+BY|HAVING|LIMIT|WINDOW|UNION)\b|\)/i', $rest)[0] ?? '');
    }

    return [$out, trim($selectList)];
  }
}
