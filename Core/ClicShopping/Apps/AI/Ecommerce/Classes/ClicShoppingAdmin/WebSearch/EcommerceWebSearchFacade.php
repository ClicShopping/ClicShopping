<?php
/**
 * EcommerceWebSearchFacade - Domain-specific extension for Ecommerce operations
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch
 * @since 2026-05-12
 *
 * Architecture:
 * - Extends WebSearchFacade (agnostic layer)
 * - Adds Ecommerce-specific functionality (price comparison, product search)
 * - Maintains clean separation between agnostic and domain-specific logic
 * - Supports EntityHelperInterface for domain abstraction
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch;

use ClicShopping\AI\DomainsAI\WebSearch\WebSearchFacade;
use ClicShopping\AI\DomainsAI\WebSearch\Helper\PriceBoundFilter;
use ClicShopping\AI\InterfacesAI\EntityHelperInterface;
use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\IntentAnalyzer;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\OM\Registry;

/**
 * EcommerceWebSearchFacade Class
 *
 * Domain-specific extension of WebSearchFacade for Ecommerce operations.
 * Adds price comparison and product search capabilities while inheriting
 * all agnostic web search functionality from the parent class.
 *
 * Key Features:
 * - Price comparison with web results
 * - Product search using embeddings and SQL
 * - Price extraction from web results
 * - Product retrieval by ID or name
 * - EntityHelper support for domain abstraction
 *
 * Usage:
 * ```php
 * $facade = new EcommerceWebSearchFacade();
 *
 * // Inherited from WebSearchFacade
 * $result = $facade->search('iPhone 15 price');
 *
 * // Ecommerce-specific methods
 * $product = $facade->findProductInDatabase('iPhone 15');
 * $comparison = $facade->comparePrice($product, $result);
 * ```
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch
 */
class EcommerceWebSearchFacade extends WebSearchFacade
{
  /**
   * @var EntityHelperInterface|null Optional entity helper for domain-specific operations
   */
  private ?EntityHelperInterface $entityHelper = null;

  /**
   * @var SecurityLogger Security and audit logger
   */
  protected SecurityLogger $logger;

  /**
   * @var mixed Database connection
   */
  private mixed $db;

  /**
   * @var mixed Language registry
   */
  private mixed $language;

  /**
   * @var bool Debug mode flag
   */
  protected bool $debug;

  /**
   * Constructor
   *
   * Initializes the Ecommerce-specific facade with optional EntityHelper support.
   *
   * @param EntityHelperInterface|null $entityHelper Optional entity helper for domain abstraction
   */
  public function __construct(?EntityHelperInterface $entityHelper = null)
  {
    // Initialize parent WebSearchFacade
    parent::__construct();

    // Initialize Ecommerce-specific properties
    $this->entityHelper = $entityHelper;
    $this->logger = new SecurityLogger();
    $this->db = Registry::get('Db');

    try {
      $this->language = Registry::get('Language');
    } catch (\Exception $e) {
      $this->language = null;
    }

    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        'EcommerceWebSearchFacade initialized' .
        ($this->entityHelper !== null ? ' with EntityHelper' : ' without EntityHelper'),
        'info'
      );
    }
  }

  /**
   * Compare internal product price with web search results
   *
   * Analyzes competitor prices from web search results and provides
   * competitive analysis with recommendations.
   *
   * @param array $product Product data with 'name' and 'price' keys
   * @param array $webResults Web search results from search() method
   * @return array Comparison result with structure:
   *               - success: bool - Operation success status
   *               - product_name: string - Product name
   *               - internal_price: float - Internal product price
   *               - competitor_prices: array - List of competitor prices
   *               - comparison: array - Price comparison analysis
   *               - recommendation: string - Pricing recommendation
   *               - competitive_status: string - Competitive status (competitive|not_competitive|very_competitive)
   *               - total_competitors_found: int - Number of competitors found
   */
  public function comparePrice(array $product, array $webResults): array
  {
    try {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Comparing price for product: {$product['name']} (Internal price: {$product['price']})",
          'info'
        );
      }

      $internalPrice = (float)$product['price'];
      $productName = $product['name'];
      $competitorPrices = [];

      // Extract prices from web search results
      // Note: WebSearchFacade returns 'organic_results' not 'items'
      $items = $webResults['organic_results'] ?? $webResults['items'] ?? [];

      if (isset($webResults['shopping_results']) && is_array($webResults['shopping_results'])) {
        // Also check shopping_results for structured product data
        $items = array_merge($items, $webResults['shopping_results']);
      }

      foreach ($items as $item) {
        // Skip non-array items (defensive check)
        if (!is_array($item)) {
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Skipping non-array item in web results: " . gettype($item),
              'warning'
            );
          }
          continue;
        }

        $extractedPrice = $this->extractPriceFromResult($item);

        if ($extractedPrice !== null) {
          $competitorPrices[] = [
            'source' => $item['source'] ?? 'Unknown',
            'url' => $item['link'] ?? $item['product_link'] ?? '',
            'price' => $extractedPrice,
            'title' => $item['title'] ?? '',
            'snippet' => $item['snippet'] ?? '',
          ];
        }
      }

      // Bound competitor listings to ±PriceBoundFilter::BOUND_PERCENT of the internal (catalog)
      // price so accessories (cases, chargers…) do not skew avg/min/max/cheapest. This is the
      // single source of truth, so the bounded set also flows to display and LLM synthesis.
      $priceBound = PriceBoundFilter::bound($internalPrice, $competitorPrices);
      $competitorPricesExcluded = (int)$priceBound['excluded'];
      $competitorPrices = $priceBound['kept'];

      if (empty($competitorPrices)) {
        return [
          'success' => true,
          'product_name' => $productName,
          'internal_price' => $internalPrice,
          'competitor_prices' => [],
          'comparison' => [
            'cheapest' => null,
            'most_expensive' => null,
            'average_competitor_price' => null,
            'price_differences' => [],
          ],
          'recommendation' => 'No competitor prices found for comparison.',
          'competitive_status' => 'unknown',
        ];
      }

      // Calculate price differences
      $priceDifferences = [];
      $competitorPriceValues = [];

      foreach ($competitorPrices as $competitor) {
        $competitorPrice = $competitor['price'];
        $competitorPriceValues[] = $competitorPrice;

        $difference = $internalPrice - $competitorPrice;
        $percentageDiff = $competitorPrice > 0 ? ($difference / $competitorPrice) * 100 : 0;

        $priceDifferences[] = [
          'source' => $competitor['source'],
          'url' => $competitor['url'],
          'competitor_price' => $competitorPrice,
          'difference' => round($difference, 2),
          'percentage_difference' => round($percentageDiff, 2),
          'status' => $difference < 0 ? 'cheaper' : ($difference > 0 ? 'more_expensive' : 'same'),
        ];
      }

      // Find cheapest and most expensive
      $cheapest = null;
      $mostExpensive = null;
      $minPrice = PHP_FLOAT_MAX;
      $maxPrice = 0;

      foreach ($priceDifferences as $diff) {
        if ($diff['competitor_price'] < $minPrice) {
          $minPrice = $diff['competitor_price'];
          $cheapest = $diff;
        }
        if ($diff['competitor_price'] > $maxPrice) {
          $maxPrice = $diff['competitor_price'];
          $mostExpensive = $diff;
        }
      }

      // Calculate average
      $avgCompetitorPrice = count($competitorPriceValues) > 0
        ? array_sum($competitorPriceValues) / count($competitorPriceValues)
        : 0;

      // Determine competitive status
      $competitiveStatus = 'competitive';
      $recommendation = '';

      $avgDifference = $internalPrice - $avgCompetitorPrice;
      $avgPercentageDiff = $avgCompetitorPrice > 0 ? ($avgDifference / $avgCompetitorPrice) * 100 : 0;

      if ($avgPercentageDiff > 10) {
        $competitiveStatus = 'not_competitive';
        $recommendation = sprintf(
          "Your price (%.2f) is %.1f%% higher than the average competitor price (%.2f). Consider reducing the price to remain competitive.",
          $internalPrice,
          abs($avgPercentageDiff),
          $avgCompetitorPrice
        );
      } elseif ($avgPercentageDiff < -10) {
        $competitiveStatus = 'very_competitive';
        $recommendation = sprintf(
          "Your price (%.2f) is %.1f%% lower than the average competitor price (%.2f). You have a strong competitive advantage.",
          $internalPrice,
          abs($avgPercentageDiff),
          $avgCompetitorPrice
        );
      } else {
        $competitiveStatus = 'competitive';
        $recommendation = sprintf(
          "Your price (%.2f) is competitive, within %.1f%% of the average competitor price (%.2f).",
          $internalPrice,
          abs($avgPercentageDiff),
          $avgCompetitorPrice
        );
      }

      return [
        'success' => true,
        'product_name' => $productName,
        'internal_price' => $internalPrice,
        'competitor_prices' => $competitorPrices,
        'comparison' => [
          'cheapest' => $cheapest,
          'most_expensive' => $mostExpensive,
          'average_competitor_price' => round($avgCompetitorPrice, 2),
          'price_differences' => $priceDifferences,
          'average_percentage_difference' => round($avgPercentageDiff, 2),
        ],
        'recommendation' => $recommendation,
        'competitive_status' => $competitiveStatus,
        'total_competitors_found' => count($competitorPrices),
        'price_bound' => [
          'excluded' => $competitorPricesExcluded,
          'bound_percent' => $priceBound['bound_percent'],
        ],
      ];

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error comparing prices: " . $e->getMessage(),
        'error'
      );

      return [
        'success' => false,
        'error' => 'Unable to compare prices: ' . $e->getMessage(),
        'product_name' => $product['name'] ?? 'Unknown',
        'internal_price' => $product['price'] ?? 0,
      ];
    }
  }

  /**
   * Find product in database before web search
   *
   * Searches for a product using embeddings (preferred) or SQL LIKE (fallback).
   * Supports EntityHelper for domain abstraction.
   *
   * @param string $query Search query (product name or description)
   * @param int|null $languageId Language ID (defaults to current language)
   * @return array|null Product data with structure:
   *                    - product_id: int - Product ID
   *                    - name: string - Product name
   *                    - price: float - Product price
   *                    - model: string - Product model
   *                    - detection_method: string - Detection method (embedding|sql_like)
   *                    - confidence: float - Detection confidence (0.0-1.0)
   *                    - entity_id: int - Entity ID (same as product_id)
   *                    - entity_type: string - Entity type (product)
   */
  public function findProductInDatabase(string $query, ?int $languageId = null): ?array
  {
    try {
      if ($languageId === null) {
        $languageId = $this->language !== null ? $this->language->getId() : 1;
      }

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Searching for product in database: {$query} (language_id: {$languageId})",
          'info'
        );
      }

      // Try embedding search using IntentAnalyzer
      $intentAnalyzer = new IntentAnalyzer(null, $this->debug);
      $entityResult = $intentAnalyzer->detectEntityFromEmbeddings($query, 'product');

      if ($entityResult !== null && isset($entityResult['entity_id'])) {
        $product = null;
        if ($this->entityHelper !== null) {
          $product = $this->entityHelper::getEntityById($entityResult['entity_id'], $languageId);
        } else {
          $product = $this->getProductById($entityResult['entity_id'], $languageId);
        }

        if ($product !== null) {
          $product['detection_method'] = 'embedding';
          $product['confidence'] = $entityResult['confidence'];
          $product['entity_id'] = $product['product_id'];
          $product['entity_type'] = 'product';
          return $product;
        }
      }

      // Fallback to SQL LIKE search
      $product = null;

      if ($this->entityHelper !== null && method_exists($this->entityHelper, 'searchProductByName')) {
        $product = $this->entityHelper->searchProductByName($query, $languageId);
      } else {
        $product = $this->searchProductByName($query, $languageId);
      }

      if ($product !== null) {
        $product['detection_method'] = 'sql_like';
        $product['confidence'] = 0.6;
        $product['entity_id'] = $product['product_id'];
        $product['entity_type'] = 'product';
        return $product;
      }

      return null;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error finding product in database: " . $e->getMessage(),
        'error'
      );
      return null;
    }
  }

  /**
   * Get product details by ID
   *
   * Retrieves product information from database by product ID.
   *
   * @param int $productId Product ID
   * @param int|null $languageId Language ID (defaults to current language)
   * @return array|null Product data with structure:
   *                    - product_id: int - Product ID
   *                    - name: string - Product name
   *                    - price: float - Product price
   *                    - model: string - Product model
   */
  private function getProductById(int $productId, ?int $languageId = null): ?array
  {
    try {
      if ($languageId === null) {
        $languageId = $this->language !== null ? $this->language->getId() : 1;
      }

      $Qproduct = $this->db->prepare('SELECT p.products_id as product_id,
                                             pd.products_name as name,
                                             p.products_price as price,
                                             p.products_model as model
                                      FROM :table_products p
                                      INNER JOIN :table_products_description pd ON p.products_id = pd.products_id
                                      WHERE p.products_id = :product_id
                                        AND pd.language_id = :language_id
                                      LIMIT 1
                                    ');

      $Qproduct->bindInt(':product_id', $productId);
      $Qproduct->bindInt(':language_id', $languageId);
      $Qproduct->execute();

      if ($Qproduct->fetch()) {
        return [
          'product_id' => $Qproduct->valueInt('product_id'),
          'name' => $Qproduct->value('name'),
          'price' => $Qproduct->valueDecimal('price'),
          'model' => $Qproduct->value('model'),
        ];
      }

      return null;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error getting product by ID: " . $e->getMessage(),
        'error'
      );
      return null;
    }
  }

  /**
   * Search product by name using SQL LIKE
   *
   * Searches for products using SQL LIKE query with intelligent ranking.
   * Cleans query by removing common words and prioritizes exact matches.
   *
   * @param string $query Search query
   * @param int|null $languageId Language ID (defaults to current language)
   * @return array|null Product data with structure:
   *                    - product_id: int - Product ID
   *                    - name: string - Product name
   *                    - price: float - Product price
   *                    - model: string - Product model
   */
  private function searchProductByName(string $query, ?int $languageId = null): ?array
  {
    try {
      if ($languageId === null) {
        $languageId = $this->language !== null ? $this->language->getId() : 1;
      }

      // Clean query by removing common words
      $cleanQuery = preg_replace('/\b(stock|price|compare|competitors?|show|give|display|of|the|a|an)\b/i', '', $query);
      $cleanQuery = trim($cleanQuery);

      if (empty($cleanQuery)) {
        return null;
      }

      $Qproduct = $this->db->prepare(' SELECT p.products_id as product_id,
                                               pd.products_name as name,
                                               p.products_price as price,
                                               p.products_model as model
                                        FROM :table_products p
                                        INNER JOIN :table_products_description pd ON p.products_id = pd.products_id
                                        WHERE (pd.products_name LIKE :search_term
                                           OR p.products_model LIKE :search_term)
                                          AND pd.language_id = :language_id
                                          AND p.products_status = 1
                                        ORDER BY 
                                          CASE 
                                            WHEN pd.products_name = :exact_term THEN 1
                                            WHEN pd.products_name LIKE :starts_with THEN 2
                                            ELSE 3
                                          END
                                        LIMIT 1
                                      ');

      $searchTerm = '%' . $cleanQuery . '%';
      $startsWith = $cleanQuery . '%';

      $Qproduct->bindValue(':search_term', $searchTerm);
      $Qproduct->bindValue(':exact_term', $cleanQuery);
      $Qproduct->bindValue(':starts_with', $startsWith);
      $Qproduct->bindInt(':language_id', $languageId);
      $Qproduct->execute();

      if ($Qproduct->fetch()) {
        return [
          'product_id' => $Qproduct->valueInt('product_id'),
          'name' => $Qproduct->value('name'),
          'price' => $Qproduct->valueDecimal('price'),
          'model' => $Qproduct->value('model'),
        ];
      }

      return null;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error searching product by name: " . $e->getMessage(),
        'error'
      );
      return null;
    }
  }

  /**
   * Extract price from web search result
   *
   * Extracts price from result title and snippet using multiple regex patterns.
   * Supports various price formats and currencies ($, €, £).
   *
   * @param array $result Web search result item
   * @return float|null Extracted price or null if not found
   */
  private function extractPriceFromResult(array $result): ?float
  {
    $text = '';

    if (isset($result['title'])) {
      $text .= ' ' . $result['title'];
    }
    if (isset($result['snippet'])) {
      $text .= ' ' . $result['snippet'];
    }

    // Also check for structured price data (from shopping results)
    if (isset($result['price'])) {
      // If price is already extracted, use it
      if (is_numeric($result['price'])) {
        return (float)$result['price'];
      }
      // If price is a string, add to text for pattern matching
      $text .= ' ' . $result['price'];
    }

    if (isset($result['extracted_price']) && is_numeric($result['extracted_price'])) {
      return (float)$result['extracted_price'];
    }

    if (empty($text)) {
      return null;
    }

    // Price extraction patterns
    $patterns = [
      ['pattern' => '/[\$€£]\s*(\d{1,3}(?:,\d{3})+\.\d{2})/', 'thousand_sep' => ',', 'decimal_sep' => '.'],
      ['pattern' => '/[\$€£]\s*(\d{1,6}\.\d{2})/', 'thousand_sep' => '', 'decimal_sep' => '.'],
      ['pattern' => '/(\d{1,6},\d{2})\s*[€£\$]/', 'thousand_sep' => '', 'decimal_sep' => ','],
      ['pattern' => '/(\d{1,3}(?:,\d{3})+\.\d{2})\s*[\$€£]/', 'thousand_sep' => ',', 'decimal_sep' => '.'],
      ['pattern' => '/(\d{1,6}\.\d{2})\s*[\$€£]/', 'thousand_sep' => '', 'decimal_sep' => '.'],
    ];

    foreach ($patterns as $patternConfig) {
      if (preg_match($patternConfig['pattern'], $text, $matches)) {
        $priceStr = $matches[1];

        if (!empty($patternConfig['thousand_sep'])) {
          $priceStr = str_replace($patternConfig['thousand_sep'], '', $priceStr);
        }

        if ($patternConfig['decimal_sep'] === ',') {
          $priceStr = str_replace(',', '.', $priceStr);
        }

        $price = (float)$priceStr;

        if ($price > 0 && $price < 1000000) {
          return $price;
        }
      }
    }

    return null;
  }
}
