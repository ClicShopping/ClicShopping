<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Validator;

use ClicShopping\AI\DomainsAI\Analytics\Planning\MetricAggregation;

/**
 * MetricExpressionFilter
 *
 * Deterministic post-generation check of the domain contract `expression`: a plan metric whose
 * catalogue entry declares its formula must find that formula in the SQL, compared without table
 * qualifiers, case or whitespace. A base expression reads columns or declared TERMS (named
 * row-level expressions, each owing its definition somewhere in the SQL); a derived one names
 * other metrics and holds when they hold and its SQL functions are called, its arithmetic unchecked. Presence only: the weight and the window have their own
 * contracts. Model-independent; reports, never rewrites.
 */
final class MetricExpressionFilter
{
  /**
   * @param string $sql Executable SQL
   * @param array $planMetrics Plan metrics (`[['name' => …], …]`)
   * @param array $catalog Domain metric catalogue (`name => ['expression' => …, 'aggregation' => …]`)
   * @param array<string, string> $terms Domain terms, name => row-level expression over columns
   * @return array<string, string> Violations, metric name => the declared formula it lacks
   */
  public static function violations(string $sql, array $planMetrics, array $catalog, array $terms = []): array
  {
    $normalized = self::normalize($sql);
    $violations = [];

    foreach ($planMetrics as $metric) {
      $name = (string)($metric['name'] ?? '');

      if (!self::holds($name, $normalized, $catalog, $terms, [])) {
        $violations[$name] = self::describe($name, $catalog, $terms);
      }
    }

    return $violations;
  }

  /**
   * The formula as the prompt and the correction message show it, followed by every metric and
   * term it reads, transitively.
   *
   * @param string $name Metric name
   * @param array $catalog Domain metric catalogue
   * @param array<string, string> $terms Domain terms
   * @return string '' when the metric declares no expression
   */
  public static function describe(string $name, array $catalog, array $terms = []): string
  {
    $expression = (string)($catalog[$name]['expression'] ?? '');

    if ($expression === '') {
      return '';
    }

    $used = [];
    $pending = self::identifiers($expression);

    while (($identifier = array_shift($pending)) !== null) {
      $definition = $terms[$identifier]
        ?? ($identifier !== $name && ($catalog[$identifier]['expression'] ?? '') !== '' ? self::formula($identifier, $catalog) : null);

      if ($definition !== null && !isset($used[$identifier])) {
        $used[$identifier] = $identifier . ' = ' . $definition;
        $pending = array_merge($pending, self::identifiers($definition));
      }
    }

    return self::formula($name, $catalog) . ($used === [] ? '' : ' (' . implode('; ', $used) . ')');
  }

  /**
   * Lowercase, unquoted, unqualified, spaces kept only between two words: `ROUND(SUM(ev.sign * ev.x), 2)`
   * and `round(sum(sign*x),2)` compare equal.
   *
   * @param string $sql SQL or expression
   * @return string Normalized text
   */
  public static function normalize(string $sql): string
  {
    $s = strtolower(str_replace(['`', '"'], ['', "'"], $sql));
    $s = (string)preg_replace('/\b[a-z_][a-z0-9_]*\.(?=[a-z_])/', '', $s);

    $s = (string)preg_replace('/\s+/', ' ', $s);

    return trim((string)preg_replace('/\s*([^a-z0-9_\s])\s*/', '$1', $s));
  }

  /**
   * @param string $name Metric name
   * @param array $catalog Domain metric catalogue
   * @return string The declared expression, wrapped in its counting form for a distinct count
   */
  private static function formula(string $name, array $catalog): string
  {
    $expression = (string)($catalog[$name]['expression'] ?? '');

    return ($catalog[$name]['aggregation'] ?? '') === MetricAggregation::DISTINCT_COUNT
      ? 'COUNT(DISTINCT ' . $expression . ')'
      : $expression;
  }

  /**
   * @param string $name Metric name
   * @param string $sql Normalized SQL
   * @param array $catalog Domain metric catalogue
   * @param array<string, string> $terms Domain terms
   * @param array<string, true> $seen Metrics on the current resolution path (cycle guard)
   * @return bool True when the SQL carries the metric's declared formula, or none is declared
   */
  private static function holds(string $name, string $sql, array $catalog, array $terms, array $seen): bool
  {
    $expression = (string)($catalog[$name]['expression'] ?? '');

    if ($expression === '' || isset($seen[$name])) {
      return true;
    }

    $seen[$name] = true;
    $identifiers = self::identifiers($expression);
    $usedTerms = array_values(array_filter($identifiers, static fn(string $i): bool => isset($terms[$i])));
    $metrics = array_values(array_filter($identifiers, static fn(string $i): bool => !isset($terms[$i]) && isset($catalog[$i]) && $i !== $name));

    // Derived: its metric parts and the SQL functions it names (STDDEV_SAMP, AVG) are checked;
    // its arithmetic and the terms it names (guards, rounding, scaling, a weighted denominator) are free.
    if ($metrics !== []) {
      preg_match_all('/\b([a-z_][a-z0-9_]*)\s*\(/', strtolower($expression), $calls);

      foreach (array_unique($calls[1]) as $function) {
        if (!self::contains($sql, $function . '(')) {
          return false;
        }
      }

      foreach ($metrics as $metric) {
        if (!self::holds($metric, $sql, $catalog, $terms, $seen)) {
          return false;
        }
      }

      return true;
    }

    $needle = self::normalize(self::formula($name, $catalog));

    // A population count is naturally conditional: COUNT(DISTINCT CASE WHEN … THEN key END).
    if (($catalog[$name]['aggregation'] ?? '') === MetricAggregation::DISTINCT_COUNT
      && preg_match('/count\(distinct case when .+?(?<![a-z0-9_])then ' . preg_quote(self::normalize($expression), '/') . ' end\)/', $sql) === 1) {
      return true;
    }

    if (self::contains($sql, $needle) && self::termsDefined($usedTerms, $sql, $terms)) {
      return true;
    }

    // The same formula with its terms written inline instead of aliased.
    foreach (self::inlined($needle, $usedTerms, $terms) as $variant) {
      if (self::contains($sql, $variant)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Whole-identifier containment: `final_price` is not found inside `products_final_price`.
   *
   * @param string $sql Normalized SQL
   * @param string $needle Normalized expression
   * @return bool True when the needle occurs with no identifier character glued to either end
   */
  private static function contains(string $sql, string $needle): bool
  {
    $before = preg_match('/^[a-z0-9_]/', $needle) === 1 ? '(?<![a-z0-9_])' : '';
    $after = preg_match('/[a-z0-9_]$/', $needle) === 1 ? '(?![a-z0-9_])' : '';

    return preg_match('/' . $before . preg_quote($needle, '/') . $after . '/', $sql) === 1;
  }

  /**
   * @param array<int, string> $used Term names the expression reads
   * @param string $sql Normalized SQL
   * @param array<string, string> $terms Domain terms
   * @return bool True when every term is defined by its declared expression somewhere in the SQL
   */
  private static function termsDefined(array $used, string $sql, array $terms): bool
  {
    foreach ($used as $term) {
      if (!self::contains($sql, self::normalize($terms[$term]))) {
        return false;
      }
    }

    return true;
  }

  /**
   * @param string $needle Normalized expression
   * @param array<int, string> $used Term names it reads
   * @param array<string, string> $terms Domain terms
   * @return array<int, string> The expression with each term replaced by its definition, bare or parenthesized
   */
  private static function inlined(string $needle, array $used, array $terms): array
  {
    $variants = [$needle];

    foreach ($used as $term) {
      $definition = self::normalize($terms[$term]);
      $next = [];

      foreach ($variants as $variant) {
        foreach ([$definition, '(' . $definition . ')'] as $replacement) {
          $next[] = (string)preg_replace('/\b' . preg_quote($term, '/') . '\b/', $replacement, $variant);
        }
      }

      $variants = $next;
    }

    return array_values(array_diff($variants, [$needle]));
  }

  /**
   * @param string $expression Declared expression
   * @return array<int, string> Lowercase identifiers it reads, SQL keywords and functions included
   */
  private static function identifiers(string $expression): array
  {
    preg_match_all('/\b[a-z_][a-z0-9_]*\b/', strtolower($expression), $matches);

    return array_values(array_unique($matches[0]));
  }
}
