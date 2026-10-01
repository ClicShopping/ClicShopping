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
 * returns plausible but wrong figures. Schema-based, model-independent; reports, never rewrites.
 */
final class MetricWeightFilter
{
  /**
   * @param string $sql Executable SQL
   * @param array $planMetrics Plan metrics (`[['name' => …], …]`)
   * @param array $catalog Domain metric catalogue (`name => ['weighted_by' => column, …]`)
   * @return array<string, string> Violations, metric name => weight column it fails to keep
   */
  public static function violations(string $sql, array $planMetrics, array $catalog): array
  {
    $violations = [];
    $parsed = null;

    foreach ($planMetrics as $metric) {
      $name = (string)($metric['name'] ?? '');
      $column = (string)($catalog[$name]['weighted_by'] ?? '');

      if ($column === '') {
        continue;
      }

      $parsed ??= SqlSelectBlocks::parse($sql);

      if (!self::keepsWeightOne($parsed, $column)) {
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
   * @param string $operator Normalised comparison operator
   * @param string $operand Literal or parenthesised list
   * @return bool Whether weight 1 satisfies the predicate
   */
  private static function admitsOne(string $operator, string $operand): bool
  {
    if ($operator === 'IN' || $operator === 'NOT IN') {
      $values = array_map(static fn(string $v): int => (int)str_replace(' ', '', $v), explode(',', trim($operand, '() ')));

      return in_array(1, $values, true) === ($operator === 'IN');
    }

    $value = (int)str_replace(' ', '', $operand);

    return match ($operator) {
      '<>', '!=' => 1 !== $value,
      '=' => 1 === $value,
      '>' => 1 > $value,
      '>=' => 1 >= $value,
      '<' => 1 < $value,
      '<=' => 1 <= $value,
      default => true,
    };
  }
}
