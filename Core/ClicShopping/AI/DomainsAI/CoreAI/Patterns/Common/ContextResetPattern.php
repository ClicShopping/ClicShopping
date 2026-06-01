<?php
/**
 * ContextResetPattern.php
 * 
 * Pattern-based detection for context reset markers in user queries.
 * 
 * PURPOSE:
 * Detects when users explicitly want to start a new conversation context
 * by using phrases like "new question", "change of topic", "start over", etc.
 * 
 * WHY THIS EXISTS:
 * - Provides fast, deterministic detection of context reset intent
 * - Prevents conversation context from interfering with new queries
 * - Supports feedback learning by identifying topic boundaries
 * 
 * ARCHITECTURE:
 * User Query (any language) → Translation → English Query → ContextResetPattern
 * 
 * ⚠️ IMPORTANT - POST-TRANSLATION PATTERN:
 * This pattern is called AFTER translation to English. All queries are
 * translated before reaching this pattern, so only English patterns are needed.
 * 
 * USAGE:
 * - Used by ContextSwitchDetector to identify explicit context resets
 * - Complements semantic domain detection for context management
 * - Helps determine when to clear conversation memory
 * 
 * RESTRUCTURATION: Relocated to Common (2026-01-22)
 * 
 * @package ClicShopping\AI\DomainsAI\CoreAI\Patterns\Common
 * @subpackage AI\DomainsAI\CoreAI\Patterns
 * @date 2026-01-02
 * @author ClicShopping Team
  *
 * @deprecated Pattern-based logic superseded by Pure LLM Mode
 *             Scheduled for removal in Q3 2026
 *             Use UnifiedQueryAnalyzer for intent classification instead
 *             See Domain/Patterns/DEPRECATED.md for migration guide
 **/

namespace ClicShopping\AI\DomainsAI\CoreAI\Patterns\Common;




// DEPRECATED: Pattern-based logic superseded by Pure LLM Mode. Scheduled for removal in Q3 2026.

class ContextResetPattern
{
  /**
   * Get context reset markers
   * 
   * Returns keywords that indicate the user wants to start a new conversation context.
   * Keywords are in ENGLISH because queries are translated before processing.
   * 
   * MARKERS CATEGORIES:
   * - New topic: "new", "new question", "new topic"
   * - Topic change: "change of topic", "something else"
   * - Transition: "now", "let's move on to", "let's talk about"
   * - Explicit reset: "start over", "reset context", "forget previous", "ignore previous"
   * 
   * @return array<string> List of reset markers
   */
  public static function getResetMarkers(): array
  {
    return [
      // New topic markers
      'new',
      'new question',
      'new topic',
      
      // Topic change markers
      'change of topic',
      'something else',
      
      // Transition markers
      'now',
      "let's move on to",
      "let's talk about",
      
      // Explicit reset markers
      'start over',
      'reset context',
      'forget previous',
      'ignore previous'
    ];
  }
  
  /**
   * Check if query contains a context reset marker
   * 
   * Performs case-insensitive substring matching to detect reset markers.
   * 
   * ⚠️ IMPORTANT: This method is called AFTER translation to English.
   * All queries are translated before reaching this pattern, so only
   * English patterns are needed.
   * 
   * EXAMPLES:
   * - "New question: what is the price?" → true (contains "new question")
   * - "Let's talk about products" → true (contains "let's talk about")
   * - "What is the price?" → false (no reset marker)
   * 
   * @param string $query The query to check (already translated to English)
   * @return bool True if reset marker found
   */
  public static function hasResetMarker(string $query): bool
  {
    $markers = self::getResetMarkers();
    $queryLower = mb_strtolower($query);
    
    foreach ($markers as $marker) {
      if (str_contains($queryLower, $marker)) {
        return true;
      }
    }
    
    return false;
  }
  
  /**
   * Detect context reset with detailed information
   * 
   * Returns detailed information about context reset detection,
   * including which marker was found and confidence level.
   * 
   * @param string $query The query to check
   * @return array Detection result with details
   */
  public static function detectReset(string $query): array
  {
    $markers = self::getResetMarkers();
    $queryLower = mb_strtolower($query);
    $foundMarkers = [];
    
    foreach ($markers as $marker) {
      if (str_contains($queryLower, $marker)) {
        $foundMarkers[] = $marker;
      }
    }
    
    $hasReset = !empty($foundMarkers);
    
    return [
      'has_reset' => $hasReset,
      'markers_found' => $foundMarkers,
      'confidence' => $hasReset ? 1.0 : 0.0,
      'detection_method' => 'pattern_based',
      'reasoning' => $hasReset 
        ? 'Explicit context reset marker detected: ' . implode(', ', $foundMarkers)
        : 'No context reset markers found'
    ];
  }
}
