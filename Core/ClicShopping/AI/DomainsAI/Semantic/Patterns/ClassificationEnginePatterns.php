<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Semantic\Patterns;



/**
 * ClassificationEnginePatterns - Regex patterns for JSON cleaning and validation
 *
 * NOTE: These patterns are for JSON response cleaning from LLMs.
 * They handle common malformations in LLM-generated JSON.
 *
 * @package ClicShopping\AI\DomainsAI\Semantic\Patterns
 * @since 2025-12-30
 * @moved 2025-12-31 from SubSemantics\Patterns to Domain\Patterns
 * @moved 2026-01-09 from Domain\Patterns to Domain\Patterns\Semantic
 * @moved 2026-01-22 from Domain\Patterns\Semantic to DomainsAI\Semantic\Patterns
 *
 * @deprecated Pattern-based logic superseded by Pure LLM Mode
 *             Scheduled for removal in Q3 2026
 *             Use UnifiedQueryAnalyzer for intent classification instead
 *             See Domain/Patterns/DEPRECATED.md for migration guide
 **/

// DEPRECATED: Pattern-based logic superseded by Pure LLM Mode. Scheduled for removal in Q3 2026.

class ClassificationEnginePatterns
{
  /**
   * Pattern for fixing malformed array closing
   * 
   * LLMs sometimes close arrays with }] instead of ]]
   * Example: ["analytics", "semantic"}] → ["analytics", "semantic"]]
   * 
   * This pattern finds: quote followed by } followed by ]
   * and replaces the }] with just ]
   *
   * @var string
   */
  public const MALFORMED_ARRAY_CLOSING_PATTERN = '/(["\'])\s*}\s*\]/';

  /**
   * Malformed HTML entity patterns (without semicolon)
   * 
   * LLMs sometimes output entities without semicolons:
   * - &quot instead of &quot;
   * - &apos instead of &apos;
   * - &amp instead of &amp;
   * - &lt instead of &lt;
   * - &gt instead of &gt;
   *
   * @var array<string, string> Entity name => replacement character
   */
  public const MALFORMED_ENTITY_PATTERNS = [
    '/&quot(?![a-z0-9;])/i' => '"',
    '/&apos(?![a-z0-9;])/i' => "'",
    '/&amp(?![a-z0-9;])/i' => '&',
    '/&lt(?![a-z0-9;])/i' => '<',
    '/&gt(?![a-z0-9;])/i' => '>',
  ];

  /**
   * Standard HTML entities (with semicolon)
   * 
   * Common entities that should be replaced before html_entity_decode
   *
   * @var array<string, string> Entity => replacement character
   */
  public const STANDARD_ENTITIES = [
    '&nbsp;' => ' ',
    '&amp;' => '&',
    '&quot;' => '"',
    '&lt;' => '<',
    '&gt;' => '>',
    '&apos;' => "'",
  ];

  /**
   * Fix malformed array closing in JSON string
   *
   * @param string $json JSON string to fix
   * @return string Fixed JSON string
   */
  public static function fixMalformedArrayClosing(string $json): string
  {
    return preg_replace(self::MALFORMED_ARRAY_CLOSING_PATTERN, '$1]', $json);
  }

  /**
   * Clean malformed HTML entities from text
   *
   * @param string $text Text to clean
   * @return string Cleaned text
   */
  public static function cleanMalformedEntities(string $text): string
  {
    foreach (self::MALFORMED_ENTITY_PATTERNS as $pattern => $replacement) {
      $text = preg_replace($pattern, $replacement, $text);
    }
    return $text;
  }

  /**
   * Replace standard HTML entities
   *
   * @param string $text Text to clean
   * @return string Cleaned text
   */
  public static function replaceStandardEntities(string $text): string
  {
    return str_replace(
      array_keys(self::STANDARD_ENTITIES),
      array_values(self::STANDARD_ENTITIES),
      $text
    );
  }

  /**
   * Get all patterns as an array
   *
   * @return array<string, mixed> All patterns
   */
  public static function getAllPatterns(): array
  {
    return [
      'malformed_array_closing' => self::MALFORMED_ARRAY_CLOSING_PATTERN,
      'malformed_entities' => self::MALFORMED_ENTITY_PATTERNS,
      'standard_entities' => self::STANDARD_ENTITIES,
    ];
  }
}
