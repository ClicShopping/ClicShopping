<?php
/**
 * WebSearchHandler
 * 
 * Handles web search queries with product database integration.
 * Searches internal product database first before calling external web search.
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Handler;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\WebSearch\WebSearchFacade;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

/**
 * WebSearchHandler Class
 *
 * Orchestrates web search with product database integration:
 * 1. Extract product name from query
 * 2. Search product in internal database
 * 3. If found, use exact name for web search
 * 4. Compare prices and format results
 * 
 * Updated to use WebSearchFacade (unified engine) instead of legacy WebSearchTool.
 */

class WebSearchHandler
{
  private SecurityLogger $logger;
  private ?WebSearchFacade $webSearchFacade;
  private mixed $db;
  private bool $debug;

  /**
   * Constructor
   *
   * @param string $userId User ID
   * @param int $languageId Language ID
   * @param bool $debug Enable debug logging
   */
  public function __construct(string $userId = 'system', int $languageId = 1, bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->debug = $debug;
    $this->db = Registry::get('Db');

    try {
      $this->webSearchFacade = new WebSearchFacade();
    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "⚠️ WebSearchFacade initialization failed: " . $e->getMessage(),
        'warning'
      );
      $this->webSearchFacade = null;
    }

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "WebSearchHandler initialized for user: {$userId}",
        'info'
      );
    }
  }

  /**
   * Main search method
   * 
   * Note: This handler should receive the product name from Analytics Agent.
   * It only performs external web search, not database lookups.
   *
   * @param string $query Search query (product name or search term)
   * @param array $context Context information (can include product data from Analytics)
   * @return array Search results with metadata
   */
  public function search(string $query, array $context = []): array
  {
    $startTime = microtime(true);

    try {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "🔍 WebSearchHandler: Processing query '{$query}'",
          'info'
        );
      }

      // Check if web search facade is available
      if ($this->webSearchFacade === null) {
        return [
          'success' => false,
          'error' => 'Web search not configured',
          'text_response' => 'La recherche web n\'est pas disponible actuellement. Veuillez configurer la clé API SerpAPI.'
        ];
      }

      // Perform web search via WebSearchFacade
      $webResults = $this->webSearchFacade->search($query, [
        'max_results' => 10,
        'language_id' => 1 // English
      ]);

      if (!$webResults['success']) {
        return [
          'success' => false,
          'error' => 'Web search failed',
          'text_response' => "La recherche web a échoué. Erreur: " . ($webResults['error'] ?? 'Unknown error')
        ];
      }

      $executionTime = microtime(true) - $startTime;

      return [
        'success' => true,
        'type' => 'web_search',
        'source' => 'web_search',
        'web_results' => $webResults,
        'text_response' => $this->formatWebResults($webResults),
        'metadata' => [
          'execution_time' => $executionTime,
          'web_results_count' => count($webResults['items'] ?? [])
        ]
      ];

    } catch (\Exception $e) {
      $executionTime = microtime(true) - $startTime;

      $this->logger->logSecurityEvent(
        "❌ WebSearchHandler error: " . $e->getMessage(),
        'error',
        ['stack_trace' => $e->getTraceAsString()]
      );

      return [
        'success' => false,
        'error' => $e->getMessage(),
        'text_response' => 'Une erreur est survenue lors de la recherche web.',
        'metadata' => [
          'execution_time' => $executionTime
        ]
      ];
    }
  }

  /**
   * Format web search results as readable text
   *
   * @param array $webResults Web search results
   * @return string Formatted response
   */
  private function formatWebResults(array $webResults): string
  {
    if (empty($webResults['items'])) {
      return "Aucun résultat trouvé sur le web.";
    }

    $response = "Résultats de recherche web :\n\n";
    
    $count = 0;
    foreach ($webResults['items'] as $item) {
      $count++;
      if ($count > 5) break; // Limit to top 5 results
      
      $title = $item['title'] ?? 'Sans titre';
      $snippet = $item['snippet'] ?? '';
      $source = $item['source'] ?? 'Unknown';
      
      $response .= "{$count}. {$title}\n";
      if (!empty($snippet)) {
        $response .= "   " . substr($snippet, 0, 250) . "...\n";
      }
      $response .= "   Source: {$source}\n\n";
    }

    return $response;
  }
}
