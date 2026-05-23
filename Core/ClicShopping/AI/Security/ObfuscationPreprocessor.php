<?php
/**
 * Obfuscation Preprocessor for Security Analysis
 * 
 * Detects and normalizes obfuscated queries before LLM security analysis.
 * Handles encoding (Base64, Hex, URL, HTML entities, ROT13) and obfuscation
 * (spacing, leetspeak, unicode) while preserving legitimate SQL operators.
 * 
 * Integration:
 * - Uses HTMLOverrideCommon for HTML entity decoding
 * - Preserves SQL operators: >, <, >=, <=, =, !=
 * - Detects encoding patterns without breaking legitimate queries
 * - Boosts threat confidence when obfuscation detected
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Security;

use ClicShopping\Sites\Common\HTMLOverrideCommon;
use ClicShopping\AI\DomainsAI\CoreAI\Patterns\Security\ObfuscationPatterns;

class ObfuscationPreprocessor
{
  /**
   * Preprocess query to detect and normalize obfuscation
   * 
   * @param string $query Original query
   * @return array Preprocessing results with normalized query and detected obfuscation
   */
  public static function preprocess(string $query): array
  {
    $original = $query;
    $normalized = $query;
    $detectedObfuscation = [];
    $confidenceBoost = 0.0;
    
    // Step 1: Detect and decode HTML entities (using existing method)
    if (self::containsHtmlEntities($query)) {
      $decoded = html_entity_decode($query, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      if ($decoded !== $query) {
        $normalized = $decoded;
        $detectedObfuscation[] = 'html_entities';
        $confidenceBoost += ObfuscationPatterns::$confidenceBoosts['html_entities'];
      }
    }
    
    // Step 2: Detect and decode ROT13
    if (self::looksLikeROT13($query)) {
      $decoded = str_rot13($query);
      if (self::containsMaliciousPatterns($decoded)) {
        $normalized = $decoded;
        $detectedObfuscation[] = 'rot13';
        $confidenceBoost += ObfuscationPatterns::$confidenceBoosts['rot13'];
      }
    }
    
    // Step 3: Detect Base64 encoding
    if (self::looksLikeBase64($query)) {
      $decoded = base64_decode($query, true);
      if ($decoded !== false && self::isValidText($decoded)) {
        $normalized = $decoded;
        $detectedObfuscation[] = 'base64';
        $confidenceBoost += ObfuscationPatterns::$confidenceBoosts['base64'];
      }
    }
    
    // Step 4: Detect hexadecimal encoding
    if (self::looksLikeHex($query)) {
      $decoded = self::decodeHex($query);
      if ($decoded !== null && self::isValidText($decoded)) {
        $normalized = $decoded;
        $detectedObfuscation[] = 'hexadecimal';
        $confidenceBoost += ObfuscationPatterns::$confidenceBoosts['hexadecimal'];
      }
    }
    
    // Step 5: Detect URL encoding
    if (self::containsUrlEncoding($query)) {
      $decoded = rawurldecode($query);
      if ($decoded !== $query) {
        $normalized = $decoded;
        $detectedObfuscation[] = 'url_encoding';
        $confidenceBoost += ObfuscationPatterns::$confidenceBoosts['url_encoding'];
      }
    }
    
    // Step 6: Normalize spacing (preserve SQL operators)
    $spacingNormalized = self::normalizeSpacing($normalized);
    if ($spacingNormalized !== $normalized) {
      $normalized = $spacingNormalized;
      $detectedObfuscation[] = 'excessive_spacing';
      $confidenceBoost += ObfuscationPatterns::$confidenceBoosts['excessive_spacing'];
    }
    
    // Step 7: Remove invisible characters (using existing method)
    $cleaned = HTMLOverrideCommon::removeInvisibleCharacters($normalized);
    if ($cleaned !== $normalized) {
      $normalized = $cleaned;
      $detectedObfuscation[] = 'invisible_characters';
      $confidenceBoost += ObfuscationPatterns::$confidenceBoosts['invisible_characters'];
    }
    
    // Step 8: Detect leetspeak (don't decode, just flag)
    if (self::containsLeetspeak($normalized)) {
      $detectedObfuscation[] = 'leetspeak';
      $confidenceBoost += ObfuscationPatterns::$confidenceBoosts['leetspeak'];
    }
    
    // Cap confidence boost at max value from patterns
    $confidenceBoost = min(ObfuscationPatterns::$maxConfidenceBoost, $confidenceBoost);
    
    return [
      'original' => $original,
      'normalized' => $normalized,
      'obfuscation_detected' => $detectedObfuscation,
      'confidence_boost' => $confidenceBoost,
      'requires_analysis' => !empty($detectedObfuscation)
    ];
  }
  
  /**
   * Check if query contains HTML entities
   */
  private static function containsHtmlEntities(string $query): bool
  {
    // Detect numeric entities (&#123;) or named entities (&nbsp;)
    foreach (ObfuscationPatterns::$htmlEntityPatterns as $pattern) {
      if (preg_match($pattern, $query) === 1) {
        return true;
      }
    }
    return false;
  }
  
  /**
   * Check if query looks like ROT13 encoding
   * ROT13 has characteristic patterns: high frequency of uncommon letter combinations
   */
  private static function looksLikeROT13(string $query): bool
  {
    // ROT13 creates unusual letter patterns
    // Check for multiple uncommon bigrams
    $matches = 0;
    
    foreach (ObfuscationPatterns::$rot13Bigrams as $bigram) {
      if (stripos($query, $bigram) !== false) {
        $matches++;
      }
    }
    
    // If 3+ uncommon bigrams, likely ROT13
    return $matches >= 3;
  }
  
  /**
   * Check if decoded text contains malicious patterns
   */
  private static function containsMaliciousPatterns(string $text): bool
  {
    $lowerText = strtolower($text);
    foreach (ObfuscationPatterns::$maliciousKeywords as $keyword) {
      if (strpos($lowerText, $keyword) !== false) {
        return true;
      }
    }
    
    return false;
  }
  
  /**
   * Check if query looks like Base64 encoding
   */
  private static function looksLikeBase64(string $query): bool
  {
    // Base64 pattern: alphanumeric + / + = padding
    // Must be at least 8 characters and match pattern
    $trimmed = trim($query);
    
    if (strlen($trimmed) < ObfuscationPatterns::$minLengths['base64']) {
      return false;
    }
    
    // Check if it matches Base64 pattern
    return preg_match(ObfuscationPatterns::$base64Pattern, $trimmed) === 1;
  }
  
  /**
   * Check if query looks like hexadecimal encoding
   */
  private static function looksLikeHex(string $query): bool
  {
    // Hex pattern: 0x followed by hex digits, or just hex digits
    $trimmed = trim($query);
    
    if (strlen($trimmed) < ObfuscationPatterns::$minLengths['hex']) {
      return false;
    }
    
    // Check for 0x prefix or pure hex
    foreach (ObfuscationPatterns::$hexPatterns as $pattern) {
      if (preg_match($pattern, $trimmed) === 1) {
        return true;
      }
    }
    
    return false;
  }
  
  /**
   * Decode hexadecimal string
   */
  private static function decodeHex(string $hex): ?string
  {
    // Remove 0x prefix if present
    $hex = preg_replace('/^0x/i', '', $hex);
    
    // Decode hex to binary
    $decoded = @hex2bin($hex);
    
    return $decoded !== false ? $decoded : null;
  }
  
  /**
   * Check if query contains URL encoding
   */
  private static function containsUrlEncoding(string $query): bool
  {
    // Detect %XX patterns
    return preg_match(ObfuscationPatterns::$urlEncodingPattern, $query) === 1;
  }
  
  /**
   * Normalize spacing while preserving SQL operators
   * 
   * CRITICAL: Preserve >, <, >=, <=, =, != for SQL queries
   * Only flag as obfuscation if excessive spacing detected (3+ consecutive spaces)
   */
  private static function normalizeSpacing(string $query): string
  {
    // Check if there's excessive spacing (3+ consecutive spaces or single-char spacing)
    $hasExcessiveSpacing = preg_match('/\s{3,}/', $query) === 1 || 
                          preg_match('/(\w\s){5,}/', $query) === 1;
    
    if (!$hasExcessiveSpacing) {
      // No excessive spacing, return as-is
      return $query;
    }
    
    // First, protect SQL operators by adding markers
    $protected = preg_replace('/([><=!]+)/', '§§§$1§§§', $query);
    
    // Normalize excessive spacing (3+ spaces to 1 space)
    $normalized = preg_replace('/\s{3,}/', ' ', $protected);
    
    // Detect single-character spacing pattern (obfuscation)
    // Pattern: "a b c d e" (5+ consecutive single chars with spaces)
    if (preg_match('/(\w\s){5,}/', $normalized)) {
      // Remove spaces between single characters
      $normalized = preg_replace('/(?<!§)(\w)\s+(?=\w)(?!§)/', '$1', $normalized);
    }
    
    // Restore SQL operators
    $normalized = str_replace('§§§', '', $normalized);
    
    return trim($normalized);
  }
  
  /**
   * Check if text is valid UTF-8 text (not binary)
   */
  private static function isValidText(string $text): bool
  {
    // Check if valid UTF-8
    if (!mb_check_encoding($text, 'UTF-8')) {
      return false;
    }
    
    // Check if contains mostly printable characters
    $printable = preg_match_all('/[\x20-\x7E\x80-\xFF]/', $text);
    $total = strlen($text);
    
    // At least 80% printable characters
    return $total > 0 && ($printable / $total) >= 0.8;
  }
  
  /**
   * Check if query contains leetspeak patterns
   */
  private static function containsLeetspeak(string $query): bool
  {
    // Common leetspeak patterns
    $matches = 0;
    foreach (ObfuscationPatterns::$leetspeakPatterns as $pattern) {
      if (preg_match($pattern, $query)) {
        $matches++;
      }
    }
    
    // If 2+ patterns match, likely leetspeak
    return $matches >= 2;
  }
  
  /**
   * Get preprocessing statistics
   */
  public static function getStats(): array
  {
    // Could be extended to track preprocessing metrics
    return [
      'total_preprocessed' => 0,
      'obfuscation_detected' => 0,
      'encoding_types' => []
    ];
  }
}
