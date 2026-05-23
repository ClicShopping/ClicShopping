<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Processor;

use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\AI\Config\DomainFields;
use ClicShopping\AI\Security\SecurityLogger;

/**
 * AnalyticsProcessor Class
 *
 * 🔧 PRIORITY 3 - PHASE 3.3: Analytics query processing logic
 *
 * Responsible for processing analytics queries:
 * - Detecting analytics patterns
 * - Calculating analytics confidence
 * - Extracting analytics metadata (aggregations, filters, time ranges)
 * - Validating analytics requirements
 *
 * Separated from IntentAnalyzer to follow Single Responsibility Principle
 * and improve maintainability.
 *
 * @package ClicShopping\AI\Helper\Intent
 */
class AnalyticsProcessor
{
  private SecurityLogger $logger;
  private bool $debug;
  
  /**
   * Cached pattern bypass check result
   * 
   * Cache the result once in constructor instead of checking repeatedly
   * 
   * @var bool True if Pure LLM mode (patterns disabled), False if Pattern mode
   */
  private bool $usePureLlmMode;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->debug = $debug;
    
    // This eliminates 3 repeated checks throughout the class
    $this->usePureLlmMode = !defined('USE_PATTERN_BASED_DETECTION') || USE_PATTERN_BASED_DETECTION === 'False';
  }

  /**
   * Calculate confidence for analytics query
   *
   * Uses analytics patterns to determine confidence score.
   * Higher confidence for queries with multiple analytics indicators.
   *
   * PATTERN BYPASS: Respects USE_PATTERN_BASED_DETECTION flag
   * - Pure LLM mode: Returns low confidence (LLM handles classification)
   * - Pattern mode: Uses AnalyticsPattern for detection
   *
   * @param string $query Query to analyze
   * @return array Result with confidence and matched patterns
   */
  public function calculateConfidence(string $query): array
  {
    if ($this->debug) {
      error_log("--- ANALYTICS CONFIDENCE CALCULATION ---");
      error_log("Query: '{$query}'");
    }

    if ($this->usePureLlmMode) {
      // Pure LLM mode: Return low confidence
      // LLM handles analytics classification through prompts
      if ($this->debug) {
        error_log("Analytics confidence calculation bypassed (Pure LLM mode)");
        error_log("Returning low confidence (0.5) - LLM will handle classification");
        error_log("--- END ANALYTICS CONFIDENCE ---");
      }

      return [
        'confidence' => 0.5,
        'match_count' => 0,
        'matched_patterns' => [],
        'word_count' => str_word_count($query),
        'detection_method' => 'llm',
      ];
    }
  }

  /**
   * Extract analytics metadata from query
   *
   * Extracts analytics-specific metadata like:
   * - Aggregation type (count, sum, average, etc.)
   * - Time ranges
   * - Filters
   * - Sorting
   * - Limits
   *
   * @param string $query Query to analyze
   * @return array Analytics metadata
   */
  public function extractMetadata(string $query): array
  {
    $metadata = [
      'aggregation_type' => null,
      'time_range' => null,
      'filters' => [],
      'sorting' => null,
      'limit' => null,
      'grouping' => null,
    ];

    // Detect aggregation type
    if (preg_match('/\b(count|total|sum|nombre)\b/i', $query)) {
      $metadata['aggregation_type'] = 'count';
    } elseif (preg_match('/\b(average|avg|mean|moyenne)\b/i', $query)) {
      $metadata['aggregation_type'] = 'average';
    } elseif (preg_match('/\b(maximum|max|highest|plus élevé)\b/i', $query)) {
      $metadata['aggregation_type'] = 'max';
    } elseif (preg_match('/\b(minimum|min|lowest|plus bas)\b/i', $query)) {
      $metadata['aggregation_type'] = 'min';
    }

    // Extract time range
    if (preg_match('/\b(today|yesterday|this week|this month|this year|aujourd\'hui|hier|cette semaine|ce mois|cette année)\b/i', $query, $matches)) {
      $metadata['time_range'] = strtolower($matches[1]);
    } elseif (preg_match('/\b(last|past|previous|dernier|précédent)\s+(\d+)\s+(days?|weeks?|months?|years?|jours?|semaines?|mois|années?)\b/i', $query, $matches)) {
      $metadata['time_range'] = [
        'type' => 'relative',
        'value' => (int)$matches[2],
        'unit' => strtolower($matches[3]),
      ];
    }

    // Extract limit
    if (preg_match('/\b(top|first|last|premier|dernier)\s+(\d+)\b/i', $query, $matches)) {
      $metadata['limit'] = (int)$matches[2];
    }

    // Extract sorting
    if (preg_match('/\b(best|worst|highest|lowest|most|least|meilleur|pire|plus|moins)\b/i', $query, $matches)) {
      $metadata['sorting'] = strtolower($matches[1]);
    }

    // Detect grouping
    if (preg_match('/\b(by|per|par)\s+(product|category|brand|supplier|manufacturer|customer|produit|catégorie|marque|fournisseur|client)\b/i', $query, $matches)) {
      $metadata['grouping'] = strtolower($matches[2]);
    }

    // Extract filters
    if (preg_match('/\b(where|with|having|status|état)\b/i', $query)) {
      $metadata['filters'][] = 'conditional_filter';
    }

    return $metadata;
  }

  /**
   * Check if analytics query is simple (no filters/grouping)
   *
   * Simple analytics queries don't need entity context.
   * Examples: "count all products", "show me sales", "list orders"
   *
   * 🔧 MIGRATION: Domain-agnostic implementation
   * - Uses EntityConfig::getEntityTypes() when Ecommerce domain is active
   * - Uses generic patterns when no domain is configured
   *
   * @param string $query Query to check
   * @param float $confidence Query confidence
   * @return bool True if simple analytics query
   */
  public function isSimpleAnalyticsQuery(string $query, float $confidence): bool
  {
    // Must have high confidence to be considered simple
    if ($confidence < 0.8) {
      return false;
    }

    // Get active domain
    $domain = DomainConfig::getActivities();
    
    // Build entity list dynamically based on domain
    $entityList = '';
    $entityConfigClass = DomainFields::resolveAppClass($domain, 'EntityConfig');
    if ($entityConfigClass !== null) {
      try {
        if (method_exists($entityConfigClass, 'getEntityTypes')) {
          $entities = $entityConfigClass::getEntityTypes();
          if (!empty($entities)) {
            $entityList = implode('|', array_map('strtolower', $entities));
          }
        }
      } catch (\Exception $e) {
        // Fallback to generic pattern if EntityConfig fails
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            'Failed to load EntityConfig: ' . $e->getMessage(),
            'warning'
          );
        }
      }
    }
    
    // If no entity list available, use generic pattern
    if (empty($entityList)) {
      $entityList = 'items|records|entries|data';
    }

    // Check for simple analytics patterns with dynamic entity list
    $simplePatterns = [
      '/\b(count|total|sum|average|list|show)\s+(all|total)?\s*(' . $entityList . ')\b/i',
      '/\b(how many|combien)\s+(' . $entityList . ')\b/i',
    ];

    foreach ($simplePatterns as $pattern) {
      if (preg_match($pattern, $query)) {
        return true;
      }
    }

    // Check for filters/grouping (not simple if present)
    $complexPatterns = [
      '/\b(by|per|par|where|with|having)\b/i',
      '/\b(for|pour|of|de)\s+(product|category|brand|supplier)/i',
    ];

    foreach ($complexPatterns as $pattern) {
      if (preg_match($pattern, $query)) {
        return false;
      }
    }

    return false;
  }
}
