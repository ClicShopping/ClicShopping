<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Planning\SubPlanExecutor;


use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Infrastructure\Metrics\CalculatorTool;
use ClicShopping\AI\DomainsAI\WebSearch\Cache\SearchCacheManager;
use ClicShopping\AI\DomainsAI\WebSearch\Tool\WebSearchTool;

/**
 * ToolExecutor Class
 *
 * Responsible for executing tools (Calculator, WebSearch).
 * Separated from PlanExecutor to follow Single Responsibility Principle.
 *
 * Responsibilities:
 * - Execute calculator operations
 * - Execute web searches
 * - Check tool availability
 * - Handle tool errors
 * - Manage search cache
 */

class ToolExecutor
{
  private SecurityLogger $logger;
  private bool $debug;
  private ?CalculatorTool $calculatorTool = null;
  private ?WebSearchTool $webSearchTool = null;
  private ?SearchCacheManager $cacheManager = null;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->debug = $debug;
    $this->cacheManager = new SearchCacheManager();

    // Initialize calculator if enabled
    if (defined('CLICSHOPPING_APP_CHATGPT_CALCULATOR_ENABLED') && 
        CLICSHOPPING_APP_CHATGPT_CALCULATOR_ENABLED === 'True') {
      $this->calculatorTool = new CalculatorTool();
    }

    // Initialize web search if API key available
    $this->initializeWebSearch();

    if ($this->debug) {
      $this->logger->logSecurityEvent("ToolExecutor initialized", 'info');
    }
  }

  /**
   * Execute calculator operation
   *
   * @param string $expression Expression to calculate
   * @return array Result
   */
  public function executeCalculator(string $expression): array
  {
    try {
      if (!$this->isToolAvailable('calculator')) {
        throw new \Exception("Calculator tool not available");
      }

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Executing calculator: {$expression}",
          'info'
        );
      }

      $result = $this->calculatorTool->calculate($expression);

      return [
        'type' => 'calculator',
        'success' => true,
        'result' => $result,
        'text_response' => "Calculation result: {$result}",
      ];

    } catch (\Exception $e) {
      return $this->handleToolError('calculator', $e);
    }
  }

  /**
   * Execute web search
   *
   * @param string $query Query to search
   * @return array Result
   */
  public function executeWebSearch(string $query): array
  {
    try {
      if (!$this->isToolAvailable('web_search')) {
        throw new \Exception("Web search tool not available");
      }

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Executing web search: {$query}",
          'info'
        );
      }

      // Check cache first
      $cachedResult = $this->cacheManager->get($query); // not worl to check
      if ($cachedResult !== null) {
        if ($this->debug) {
          $this->logger->logSecurityEvent("Web search result from cache", 'info');
        }
        return $cachedResult;
      }

      // Execute search
      $result = $this->webSearchTool->search($query);

      // Cache result
      $formattedResult = [
        'type' => 'web_search',
        'success' => true,
        'results' => $result,
        'text_response' => $this->formatWebSearchResults($result),
      ];

      $this->cacheManager->set($query, $formattedResult);

      return $formattedResult;

    } catch (\Exception $e) {
      return $this->handleToolError('web_search', $e);
    }
  }

  /**
   * Check if tool is available
   *
   * @param string $toolName Tool name (calculator, web_search)
   * @return bool True if available
   */
  public function isToolAvailable(string $toolName): bool
  {
    switch ($toolName) {
      case 'calculator':
        return $this->calculatorTool !== null;
      case 'web_search':
        return $this->webSearchTool !== null;
      default:
        return false;
    }
  }

  /**
   * Handle tool error
   *
   * @param string $toolName Tool name
   * @param \Exception $e Exception
   * @return array Error result
   */
  public function handleToolError(string $toolName, \Exception $e): array
  {
    $this->logger->logSecurityEvent(
      "Tool '{$toolName}' failed: " . $e->getMessage(),
      'error'
    );

    return [
      'type' => $toolName,
      'success' => false,
      'error' => $e->getMessage(),
      'text_response' => "Tool '{$toolName}' failed: " . $e->getMessage(),
    ];
  }

  /**
   * Initialize web search tool
   *
   * @return void
   */
  private function initializeWebSearch(): void
  {
    // Check for SerpApi key
    $serpApiKey = getenv('SERP_API_KEY');
    
    if (empty($serpApiKey) && defined('CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI')) {
      $serpApiKey = CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI;
    }

    if (!empty($serpApiKey)) {
      try {
        putenv('SERP_API_KEY=' . $serpApiKey);
        $this->webSearchTool = new WebSearchTool();
        
        if ($this->debug) {
          $this->logger->logSecurityEvent("WebSearchTool initialized", 'info');
        }
      } catch (\Exception $e) {
        $this->webSearchTool = null;
        
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "WebSearchTool initialization failed: " . $e->getMessage(),
            'warning'
          );
        }
      }
    }
  }

  /**
   * Format web search results
   *
   * @param array $results Search results
   * @return string Formatted text
   */
  private function formatWebSearchResults(array $results): string
  {
    if (empty($results)) {
      return "No results found.";
    }

    $formatted = "Web search results:\n\n";
    foreach (array_slice($results, 0, 3) as $i => $result) {
      $formatted .= ($i + 1) . ". " . ($result['title'] ?? 'No title') . "\n";
      $formatted .= "   " . ($result['snippet'] ?? 'No description') . "\n\n";
    }

    return $formatted;
  }
}
