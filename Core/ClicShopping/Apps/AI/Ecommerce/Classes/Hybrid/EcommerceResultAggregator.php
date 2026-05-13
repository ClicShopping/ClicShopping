<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\Hybrid;

use ClicShopping\AI\DomainsAI\Hybrid\Processor\ResultAggregator;
use ClicShopping\AI\DomainsAI\Hybrid\Helper\Formatter\ResultFormatter;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\EcommerceWebSearchFacade;

/**
 * EcommerceResultAggregator - E-commerce specific result aggregation
 *
 * Extends ResultAggregator with e-commerce domain-specific logic:
 * - Product data extraction
 * - Price comparison aggregation
 * - Product display formatting
 *
 * This class contains all e-commerce business logic that was previously
 * in the agnostic ResultAggregator class.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\Hybrid
 * @since 2026-04-28
 */
class EcommerceResultAggregator extends ResultAggregator
{
  /**
   * Entity data extractor
   *
   * @var EntityDataExtractor
   */
  private EntityDataExtractor $entityExtractor;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    parent::__construct($debug);
    $this->entityExtractor = new EntityDataExtractor($debug);
  }

  /**
   * Override to handle e-commerce specific aggregation types
   *
   * @param string $aggregationType Aggregation type
   * @param array $successfulResults Successful results
   * @param array $failedResults Failed results
   * @return array Aggregated result
   */
  protected function aggregateDomainSpecific(string $aggregationType, array $successfulResults, array $failedResults): array
  {
    return match($aggregationType) {
      'price_comparison' => $this->aggregatePriceComparison($successfulResults, $failedResults),
      default => parent::aggregateDomainSpecific($aggregationType, $successfulResults, $failedResults)
    };
  }

  /**
   * Aggregate price comparison results (analytics + web_search)
   *
   * E-commerce specific: compares internal product prices with competitor prices
   *
   * @param array $successfulResults Successful sub-query results
   * @param array $failedResults Failed sub-query results
   * @return array Aggregated result
   */
  protected function aggregatePriceComparison(array $successfulResults, array $failedResults): array
  {
    // Step 1: Extract product data from Analytics result
    $productData = null;
    $query = '';
    
    foreach ($successfulResults as $subResult) {
      $type = $subResult['type'] ?? '';
      
      if ($type === 'analytics') {
        $data = $subResult['result']['result']['data'] ?? [];
        if (!empty($data)) {
          $productData = $this->entityExtractor->extractFromRow($data[0]);
        }
      } elseif ($type === 'web_search') {
        $query = $subResult['query'] ?? '';
      }
    }
    
    // Step 2: Validate we have required data
    if ($productData === null || empty($query)) {
      return $this->buildBasicPriceComparison($productData, null, [], $successfulResults, $failedResults);
    }
    
    // Step 3: Call EcommerceWebSearchFacade.comparePrice() (SINGLE SOURCE OF TRUTH)
    try {
      $facade = new EcommerceWebSearchFacade();
      
      // Convert product data to expected format
      $product = [
        'products_id' => $productData['id'] ?? null,
        'products_name' => $productData['name'] ?? 'Unknown Product',
        'products_price' => $productData['price'] ?? 0.0,
        'products_model' => $productData['model'] ?? ''
      ];
      
      $comparison = $facade->comparePrice($product, $query);
      
      // Step 4: Format result
      $aggregatedText = ResultFormatter::formatPriceComparisonAsText($comparison);
      
      return $this->formatAggregatedResult(
        'price_comparison',
        $aggregatedText,
        ['comparison_data' => $comparison, 'product' => $productData],
        $this->collectSources($comparison),
        $successfulResults,
        $failedResults
      );
    } catch (\Exception $e) {
      $this->logWarning("Error in price comparison", ['error' => $e->getMessage()]);
      return $this->buildBasicPriceComparison($productData, null, [], $successfulResults, $failedResults);
    }
  }

  /**
   * Build basic price comparison when WebSearchTool is unavailable
   *
   * @param array|null $productData Product data
   * @param array|null $webSearchResults Web search results
   * @param array $sources Sources
   * @param array $successfulResults Successful results
   * @param array $failedResults Failed results
   * @return array Aggregated result
   */
  private function buildBasicPriceComparison(?array $productData, ?array $webSearchResults, array $sources, array $successfulResults, array $failedResults): array
  {
    $text = "";

    // Display product information with fallback strategies
    if ($productData !== null) {
      $text .= $this->formatProductDisplay($productData);
    } else {
      $text .= "Product information not available.\n\n";
    }

    // Display competitor information
    $competitorInfo = [];
    if ($webSearchResults !== null) {
      $response = $webSearchResults['result']['text_response'] ?? $webSearchResults['response'] ?? '';
      if (!empty($response)) {
        $competitorInfo[] = $response;
      }
    }

    if (!empty($competitorInfo)) {
      $text .= "Competitor Information:\n" . implode("\n", $competitorInfo) . "\n";
    }

    $text = $this->addFailedQueryWarning($text, $failedResults);

    return $this->formatAggregatedResult(
      'price_comparison',
      trim($text),
      [
        'product' => $productData,
        'competitor_info' => $competitorInfo
      ],
      $sources,
      $successfulResults,
      $failedResults
    );
  }

  /**
   * Format product display with fallback strategies for missing fields
   *
   * This method provides intelligent display formatting that adapts to
   * available fields instead of assuming specific field names.
   *
   * Display strategy:
   * 1. Show name/title if available
   * 2. Show price if available
   * 3. Show model/SKU if available
   * 4. Show entity type if detected
   * 5. Gracefully handle missing fields
   *
   * @param array $productData Product data from EntityDataExtractor
   * @return string Formatted product display text
   */
  private function formatProductDisplay(array $productData): string
  {
    $lines = [];

    // Display name/title
    if (!empty($productData['name']) && $productData['name'] !== 'Unknown Item') {
      $lines[] = "Item: " . $productData['name'];
    }

    // Display price
    if (!empty($productData['price'])) {
      $lines[] = "Our Price: $" . number_format($productData['price'], 2);
    }

    // Display model/SKU
    if (!empty($productData['model'])) {
      $lines[] = "Model: " . $productData['model'];
    }

    // Display entity type if detected (helps with debugging)
    if ($this->debug && !empty($productData['entity_type'])) {
      $lines[] = "Type: " . $productData['entity_type'];
    }

    // Fallback if no useful information
    if (empty($lines)) {
      return "Item information available but fields not recognized.\n" .
             "Available fields: " . implode(', ', $productData['available_fields'] ?? []) . "\n\n";
    }

    return implode("\n", $lines) . "\n\n";
  }

  /**
   * Get the entity data extractor
   *
   * @return EntityDataExtractor
   */
  public function getEntityExtractor(): EntityDataExtractor
  {
    return $this->entityExtractor;
  }
}
