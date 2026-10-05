<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Validator;

/**
 * SensitiveOutputFilter
 *
 * Deterministic check of what a statement SERVES among the columns tables declare sensitive
 * (`ai_sensitive_contact` / `ai_sensitive_identity`). Model-independent; reports, never rewrites.
 */
final class SensitiveOutputFilter
{
  /** Aggregates that reduce a column to a number: what they wrap is never served. */
  private const string REDUCING = 'COUNT|SUM|AVG|STD|STDDEV|STDDEV_POP|STDDEV_SAMP|VARIANCE|VAR_POP|VAR_SAMP|BIT_AND|BIT_OR|BIT_XOR';

  /**
   * A column is served when a SELECT list names it at any level (expression, alias and
   * GROUP_CONCAT included) or reaches it through `*` / `t.*` on its table. Read only in
   * WHERE/ON/GROUP BY/ORDER BY/HAVING, or reduced by COUNT/SUM/AVG…, it is not served.
   * Conservative on purpose: a false positive caps a list, a false negative leaks one.
   *
   * @param string $sql Executable SQL
   * @param array<string, array<string, list<string>>> $declared Level => unprefixed table => columns
   * @param string $prefix Table prefix of the install
   * @return array{contact: list<string>, identity: list<string>} Served columns per level
   */
  public static function served(string $sql, array $declared, string $prefix): array
  {
    $served = ['contact' => [], 'identity' => []];
    $parsed = SqlSelectBlocks::parse($sql);
    $masked = $parsed['masked'];

    foreach ($parsed['specs'] as $spec) {
      $list = self::withoutReducingAggregates(substr($masked, $spec['select'] + 6, $spec['from'] - $spec['select'] - 6));
      $star = preg_match('/(?:^|,)\s*(?:DISTINCT\s+|ALL\s+)?(?:[`\w]+\.)?\*\s*(?:,|$)/i', trim($list)) === 1;
      $source = $star ? substr($masked, $spec['from'], $spec['end'] - $spec['from']) : '';

      foreach ($declared as $level => $tables) {
        foreach ($tables as $table => $columns) {
          $reached = $star && self::names($source, $prefix . $table);

          foreach ($columns as $column) {
            if ($reached || self::names($list, $column)) {
              $served[$level][$column] = true;
            }
          }
        }
      }
    }

    return [
      'contact' => array_keys($served['contact'] ?? []),
      'identity' => array_keys($served['identity'] ?? []),
    ];
  }

  /**
   * @param string $list A SELECT list, literals masked
   * @return string The same list, every reducing aggregate replaced by a constant
   */
  private static function withoutReducingAggregates(string $list): string
  {
    while (preg_match('/\b(?:' . self::REDUCING . ')\s*\(/i', $list, $m, PREG_OFFSET_CAPTURE) === 1) {
      $start = $m[0][1];
      $depth = 0;
      $length = strlen($list);

      for ($i = $start + strlen($m[0][0]) - 1; $i < $length; $i++) {
        if ($list[$i] === '(') {
          $depth++;
        } elseif ($list[$i] === ')' && --$depth === 0) {
          break;
        }
      }

      $list = substr($list, 0, $start) . ' 0 ' . substr($list, $i + 1);
    }

    return $list;
  }

  private static function names(string $code, string $identifier): bool
  {
    return preg_match('/(?<![A-Za-z0-9_$])' . preg_quote($identifier, '/') . '(?![A-Za-z0-9_$])/i', $code) === 1;
  }
}
