<?php
/**
 * SubTaskPlannerWebSearch
 * 
 * Planner spécialisé pour la recherche web externe
 * Responsibility : Createsr des plans pour recherches web via SERAPI
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Planning\SubTaskPlanning;


use ClicShopping\AI\CoreAI\Planning\TaskStep;
use ClicShopping\AI\Security\SecurityLogger;

/**
 * SubTaskPlannerWebSearch Class
 * 
 * Creates execution plans for web search queries that require external data
 * from search engines (Google, Bing, etc.) via SERAPI
 */

class SubTaskPlannerWebSearch
{
  private bool $debug;
  private ?SecurityLogger $securityLogger;

  /**
   * Constructor
   * 
   * @param bool $debug Enable debug logging
   * @param SecurityLogger|null $securityLogger Logger instance
   */
  public function __construct(bool $debug = false, ?SecurityLogger $securityLogger = null)
  {
    $this->debug = $debug;
    $this->securityLogger = $securityLogger;
  }

  /**
   * Detects si une requête concerne la recherche web
   * 
   * @param string $query Query to analyze
   * @return bool True if this planner can handle the query
   */
  public function canHandle(string $query): bool
  {
    // La recherche web est généralement déterminée par l'intent type
    // plutôt que par des patterns dans la requête
    return true; // Accepte toutes les requêtes pour ce type
  }

  /**
   * Creates le plan de recherche web (1 étape simple)
   * 
   * Extract product name from intent for price_comparison queries
   * 
   * 
   * @param array $intent Intent analysis result
   * @param string $query Original query
   * @return array Array of TaskStep objects
   */
  public function createPlan(array $intent, string $query): array
  {
    if ($this->debug) {
      $this->logDebug("Creating web search plan for query: " . substr($query, 0, 100));
    }

    $steps = [];

    // 🔧 FIX (2026-05-10): Extract clean search query for price_comparison
    // For price_comparison queries, use the extracted product name from intent
    // instead of the full query to avoid sending "price" keywords to shopping engines
    $searchQuery = $query; // Default: use full query
    
    if (isset($intent['intent']) && $intent['intent'] === 'price_comparison') {
      // Check if intent has extracted product name
      if (!empty($intent['product'])) {
        $searchQuery = $intent['product'];
        
        if ($this->debug) {
          $this->logDebug("Price comparison detected - using extracted product name: '{$searchQuery}' (original query: '{$query}')");
        }
      } else {
        // Fallback: use full query if product extraction failed
        if ($this->debug) {
          $this->logDebug("Price comparison detected but no product extracted - using full query: '{$query}'");
        }
      }
    }

    // Step unique: Recherche web via SERAPI
    $step1 = new TaskStep(
      'step_1',
      'web_search',
      $searchQuery, // Use extracted product name for price_comparison, full query otherwise
      [
        'intent' => $intent,
        'search_type' => 'web_search',
        'data_source' => 'external_web',
        'search_engine' => 'google', // Default engine
        'max_results' => 10,
        'use_cache' => true,
        'cache_ttl' => 86400, // 24 hours
        'depends_on' => [],
        'can_run_parallel' => false,
        'is_final' => true,
        'search_query' => $searchQuery, // 🔧 FIX: Add explicit search_query metadata
        'original_query' => $query, // Keep original query for reference
      ]
    );
    $steps[] = $step1;

    if ($this->debug) {
      $this->logDebug("Created web search plan with " . count($steps) . " step (search_query: '{$searchQuery}')");
    }

    return $steps;
  }

  /**
   * Gets les planner metadata
   * 
   * @return array Planner metadata
   */
  public function getMetadata(): array
  {
    return [
      'name' => 'Web Search Planner',
      'description' => 'Specialized planner for external web search via SERAPI',
      'steps_count' => 1,
      'step_types' => ['web_search'],
      'data_sources' => ['external_web'],
      'search_engines' => ['google', 'bing', 'duckduckgo'],
      'supports_fallback' => true,
      'requires_external_data' => true,
      'requires_api_key' => true
    ];
  }

  /**
   * Log debug message
   * 
   * @param string $message Message to log
   */
  private function logDebug(string $message): void
  {
    if ($this->securityLogger) {
      $this->securityLogger->logSecurityEvent($message, 'info');
    }

    if ($this->debug) {
      error_log("[SubTaskPlannerWebSearch] $message");
    }
  }
}
