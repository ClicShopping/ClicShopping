<?php
/**
 * ObfuscationPatterns.php
 * 
 * Pattern definitions for obfuscation detection in security analysis.
 * Contains ONLY pattern arrays - no logic.
 * 
 * Used by ObfuscationPreprocessor to detect encoding and obfuscation attempts.
 * 
 * @package ClicShopping\AI\DomainsAI\CoreAI\Patterns\Security
 * @since 2026-01-07
 *
 * @deprecated Pattern-based logic superseded by Pure LLM Mode
 *             Scheduled for removal in Q3 2026
 *             Use UnifiedQueryAnalyzer for intent classification instead
 *             See Domain/Patterns/DEPRECATED.md for migration guide
 **/

namespace ClicShopping\AI\DomainsAI\CoreAI\Patterns\Security;




// DEPRECATED: Pattern-based logic superseded by Pure LLM Mode. Scheduled for removal in Q3 2026.

class ObfuscationPatterns
{
  /**
   * Malicious keywords to detect in decoded text
   * English-only (processing is always in English)
   */
  public static array $maliciousKeywords = [
    'ignore',
    'forget',
    'instructions',
    'prompt',
    'system',
    'database',
    'schema',
    'configuration',
    'credentials',
    'reveal',
    'show',
    'display',
    'tell me',
    'what is',
    'password',
    'token',
    'api key',
    'secret',
    'admin',
    'root',
    'override',
    'bypass',
    'disable'
  ];
  
  /**
   * ROT13 uncommon bigrams
   * ROT13 creates unusual letter patterns
   */
  public static array $rot13Bigrams = [
    'vt', 'vf', 'va', 'ny', 'pr', 'ce', 'iv', 'bh',
    'vy', 'vp', 'vq', 'vr', 'vs', 'vw', 'vx', 'vz'
  ];
  
  /**
   * Leetspeak character mappings
   * Common substitutions used in leetspeak
   */
  public static array $leetspeakMappings = [
    '0' => 'o',
    '1' => 'i',
    '3' => 'e',
    '4' => 'a',
    '5' => 's',
    '7' => 't',
    '8' => 'b',
    '9' => 'g',
    '@' => 'a',
    '$' => 's',
    '!' => 'i'
  ];
  
  /**
   * Leetspeak detection patterns (regex)
   */
  public static array $leetspeakPatterns = [
    '/[a-z][0134579]/i',        // letter immediately followed by a leet digit (l3, s5)
    '/[0134579][a-z]/i',        // leet digit immediately followed by a letter (3t, 4g)
    '/\$/',                     // $ for s
    '/@/',                      // @ for a
    '/[a-z][0-9][a-z]/i'       // Letter-number-letter pattern
  ];
  
  /**
   * SQL operators to preserve during normalization
   * CRITICAL: These must NOT be removed or altered
   */
  public static array $sqlOperators = [
    '>',
    '<',
    '>=',
    '<=',
    '=',
    '!=',
    '<>',
    'AND',
    'OR',
    'NOT',
    'IN',
    'LIKE',
    'BETWEEN'
  ];
  
  /**
   * Invisible Unicode characters to remove
   * These are already handled by HTMLOverrideCommon::removeInvisibleCharacters()
   * Listed here for reference
   */
  public static array $invisibleCharacters = [
    "\u{200B}", // Zero Width Space
    "\u{200C}", // Zero Width Non-Joiner
    "\u{200D}", // Zero Width Joiner
    "\u{200E}", // Left-to-Right Mark
    "\u{200F}", // Right-to-Left Mark
    "\u{00A0}", // Non-breaking space
    "\u{202F}", // Narrow non-breaking space
    "\u{2060}", // Word joiner
    "\u{2028}", // Line separator
    "\u{2029}", // Paragraph separator
  ];
  
  /**
   * HTML entity patterns (regex)
   */
  public static array $htmlEntityPatterns = [
    '/&#\d+;/',           // Numeric entities (&#123;)
    '/&#x[0-9a-f]+;/i',   // Hex entities (&#x7B;)
    '/&[a-z]+;/i'         // Named entities (&nbsp;)
  ];
  
  /**
   * Base64 detection pattern (regex)
   */
  public static string $base64Pattern = '/^[A-Za-z0-9+\/]+=*$/';
  
  /**
   * Hexadecimal detection patterns (regex)
   */
  public static array $hexPatterns = [
    '/^0x[0-9a-f]+$/i',   // With 0x prefix
    '/^[0-9a-f]+$/i'      // Without prefix (must be 8+ chars)
  ];
  
  /**
   * URL encoding detection pattern (regex)
   */
  public static string $urlEncodingPattern = '/%[0-9A-F]{2}/i';
  
  /**
   * Unicode escape sequence patterns (regex)
   */
  public static array $unicodeEscapePatterns = [
    '/\\\\u[0-9a-f]{4}/i',     // \uXXXX format
    '/\\\\x[0-9a-f]{2}/i',     // \xXX format
    '/\\\\[0-7]{3}/'           // \XXX octal format
  ];
  
  /**
   * Excessive spacing patterns (regex)
   */
  public static array $spacingPatterns = [
    '/\s{3,}/',                 // 3+ consecutive spaces
    '/(\w)\s+(\w)/',            // Space between every character
    '/\t{2,}/'                  // Multiple tabs
  ];
  
  /**
   * Homoglyph character mappings
   * Visually similar characters from different alphabets
   */
  public static array $homoglyphMappings = [
    // Cyrillic lookalikes
    'а' => 'a', // Cyrillic a
    'е' => 'e', // Cyrillic e
    'о' => 'o', // Cyrillic o
    'р' => 'p', // Cyrillic p
    'с' => 'c', // Cyrillic c
    'х' => 'x', // Cyrillic x
    
    // Greek lookalikes
    'α' => 'a', // Greek alpha
    'ο' => 'o', // Greek omicron
    'ν' => 'v', // Greek nu
    'ι' => 'i', // Greek iota
    'ε' => 'e', // Greek epsilon
  ];
  
  /**
   * Minimum lengths for encoding detection
   * Prevents false positives on short strings
   */
  public static array $minLengths = [
    'base64' => 8,
    'hex' => 8,
    'rot13' => 10,
    'url_encoding' => 5
  ];
  
  /**
   * Confidence boost values for detected obfuscation
   * Applied to threat scores when obfuscation is detected
   */
  public static array $confidenceBoosts = [
    'html_entities' => 0.3,
    'rot13' => 0.3,
    'base64' => 0.3,
    'hexadecimal' => 0.3,
    'url_encoding' => 0.2,
    'excessive_spacing' => 0.1,
    'invisible_characters' => 0.1,
    'leetspeak' => 0.2,
    'homoglyphs' => 0.2,
    'unicode_escapes' => 0.3
  ];
  
  /**
   * Maximum confidence boost cap
   * Total boost cannot exceed this value
   */
  public static float $maxConfidenceBoost = 0.5;
}
