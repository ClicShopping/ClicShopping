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
   * Grain key columns the top-level GROUP BY reads while the plan asks for no such breakdown.
   *
   * ponytail: reads the top-level GROUP BY only — a final SELECT over a CTE already grouped by
   * the grain, with no GROUP BY of its own, passes; check the final select list if that shows.
   *
   * @param string $sql Executable SQL
   * @param array $plan Validated analysis plan
   * @param array<string, array<int, string>> $grainKeys Domain declaration, grain => key columns
   * @return array<int, string> Violations, the key columns found
   */
  public static function unplannedBreakdowns(string $sql, array $plan, array $grainKeys): array
  {
    // A ranking or a filter legitimately returns the grain's own rows.
    if ($grainKeys === [] || !empty($plan['rankings']) || !empty($plan['filters'])) {
      return [];
    }

    $dimensions = strtolower(implode(' ', array_filter($plan['dimensions'] ?? [], 'is_string')));
    $groupBy = self::topLevelGroupBy($sql);

    if ($groupBy === '') {
      return [];
    }

    $violations = [];

    foreach ($plan['metrics'] ?? [] as $metric) {
      $grain = (string)($metric['grain'] ?? '');

      if ($grain === '' || str_contains($dimensions, strtolower($grain))) {
        continue;
      }

      foreach ($grainKeys[$grain] ?? [] as $column) {
        if (preg_match('/\b' . preg_quote($column, '/') . '\b/i', $groupBy) === 1) {
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
        if (($catalog[$companion]['grain'] ?? null) === ($entry['grain'] ?? '') && preg_match('/\bAS\s+`?' . preg_quote($companion, '/') . '\b/i', $sql) !== 1) {
          $missing[] = $companion;
        }
      }
    }

    return array_values(array_unique($missing));
  }

  /**
   * @param string $sql Executable SQL
   * @return string Text of the top-level GROUP BY clauses, '' when none
   */
  private static function topLevelGroupBy(string $sql): string
  {
    $parsed = SqlSelectBlocks::parse($sql);
    $masked = $parsed['masked'];
    $out = '';

    preg_match_all('/\bGROUP\s+BY\b/i', $masked, $matches, PREG_OFFSET_CAPTURE);

    foreach ($matches[0] as [$word, $pos]) {
      if ($parsed['blockAt'][$pos] !== 0) {
        continue;
      }

      $rest = substr($masked, $pos + strlen($word));
      $out .= ' ' . (preg_split('/\b(ORDER\s+BY|HAVING|LIMIT|WINDOW|UNION)\b|\)/i', $rest)[0] ?? '');
    }

    return $out;
  }
}
