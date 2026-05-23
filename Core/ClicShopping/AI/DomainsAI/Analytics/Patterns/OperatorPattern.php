<?php
/**
 * OperatorPattern
 *
 * Pattern class for mapping natural language operators to SQL operators.
 * Extracted from QueryAnalyzer to follow pattern separation principle.
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * REFACTORING: Extracted from QueryAnalyzer (2026-01-05)
 *
 * @deprecated Pattern-based logic superseded by Pure LLM Mode
 *             Scheduled for removal in Q3 2026
 *             Use UnifiedQueryAnalyzer for intent classification instead
 *             See Domain/Patterns/DEPRECATED.md for migration guide
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Patterns;

// DEPRECATED: Pattern-based logic superseded by Pure LLM Mode. Scheduled for removal in Q3 2026.

class OperatorPattern
{
  /**
   * Get operator mapping
   *
   * Maps natural language operators to standard SQL operators.
   * Supports synonyms like "greater than", "more than", "over", etc.
   *
   * @return array Operator mapping (synonym => standard operator)
   */
  public static function getOperatorMap(): array
  {
    return [
      // Greater than synonyms
      'greater than' => '>',
      'more than' => '>',
      'over' => '>',
      'above' => '>',
      '>' => '>',

      // Less than synonyms
      'less than' => '<',
      'lower than' => '<',
      'under' => '<',
      'below' => '<',
      '<' => '<',

      // Equal to synonyms
      'equal to' => '=',
      'is' => '=',
      '=' => '='
    ];
  }

  /**
   * Get operator regex pattern
   *
   * Returns a regex pattern that matches all operator synonyms.
   * Used for extracting operators from queries.
   *
   * @return string Regex pattern for operator matching
   */
  public static function getOperatorRegexPattern(): string
  {
    $operators = array_keys(self::getOperatorMap());
    $escapedOperators = array_map(fn($k) => preg_quote($k, '/'), $operators);
    return implode('|', $escapedOperators);
  }

  /**
   * Translate operator synonym to standard operator
   *
   * Converts natural language operator to SQL operator (>, <, =).
   *
   * @param string $operator Natural language operator
   * @return string Standard SQL operator (>, <, =)
   */
  public static function translate(string $operator): string
  {
    $map = self::getOperatorMap();
    return $map[$operator] ?? '='; // Default to '=' if not found
  }

  /**
   * Get reverse operator map (for text formatting)
   *
   * Maps SQL operators to human-readable text.
   * Used for formatting filters in prompts.
   *
   * @return array Reverse mapping (operator => text)
   */
  public static function getReverseMap(): array
  {
    return [
      '>' => 'greater than',
      '<' => 'less than',
      '=' => 'equal to'
    ];
  }

  /**
   * Get metadata about this pattern
   *
   * @return array Pattern metadata
   */
  public static function getMetadata(): array
  {
    return [
      'name' => 'Operator Pattern',
      'description' => 'Maps natural language operators to SQL operators (>, <, =)',
      'operator_count' => count(self::getOperatorMap()),
      'supported_operators' => ['>', '<', '='],
      'synonym_count' => [
        '>' => 5,
        '<' => 5,
        '=' => 3
      ],
      'usage' => 'QueryAnalyzer::extractQueryCriteria(), QueryAnalyzer::formatStructuredFiltersToText()',
      'examples' => [
        'price greater than 100',
        'stock less than 10',
        'rating equal to 5'
      ]
    ];
  }
}
