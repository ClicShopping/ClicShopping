<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Validator;

/**
 * AggregateSourceFilter
 *
 * Deterministic post-generation check of the domain contract "forbidden aggregate sources": a
 * row the domain declares unfit for any total or average (column = value) must never be selected
 * by a query that aggregates. Model-independent; reports, never rewrites.
 */
final class AggregateSourceFilter
{
  /**
   * ponytail: statement-wide, not per SELECT block — a query that aggregates anywhere and
   * selects a forbidden row anywhere is flagged; split per block if a false positive shows.
   *
   * @param string $sql Executable SQL
   * @param array<string, array<int, string>> $forbidden Domain declaration, column => forbidden values
   * @return array<int, string> Violations, as "column = 'value'"
   */
  public static function violations(string $sql, array $forbidden): array
  {
    if ($forbidden === [] || preg_match('/\b(SUM|AVG)\s*\(/i', $sql) !== 1) {
      return [];
    }

    $violations = [];

    foreach ($forbidden as $column => $values) {
      $c = preg_quote((string)$column, '/');

      foreach ($values as $value) {
        $v = preg_quote((string)$value, '/');

        if (preg_match("/\\b{$c}\\s*(=\\s*'{$v}'|IN\\s*\\([^)]*'{$v}'[^)]*\\))/i", $sql) === 1) {
          $violations[] = "{$column} = '{$value}'";
        }
      }
    }

    return $violations;
  }
}
