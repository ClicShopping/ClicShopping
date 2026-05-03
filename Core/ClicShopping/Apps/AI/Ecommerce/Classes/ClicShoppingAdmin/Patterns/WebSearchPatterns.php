<?php
/**
 * WebSearchPatterns - Ecommerce-specific web search patterns
 *
 * This file is part of the ClicShopping AI Framework.
 * It provides price comparison and extraction logic for the Ecommerce domain.
 * This keeps the framework domain-agnostic while providing domain-specific
 * functionality in the Ecommerce app.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Patterns
 * @since 1.0.0
 * @author ClicShopping Team
 * @copyright 2026 ClicShopping
 * @license MIT
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Patterns;

/**
 * WebSearchPatterns Class
 *
 * Provides Ecommerce-specific web search patterns for price comparison and extraction.
 * This class contains all price-related logic that was previously in WebSearchTool,
 * making the framework truly domain-agnostic.
 *
 * Key Features:
 * - Price comparison with competitor prices
 * - Price extraction from web search results
 * - Multi-currency support ($, €, £, ¥, CHF)
 * - Competitive status analysis
 * - Price recommendation generation
 *
 * @since 1.0.0
 */
class WebSearchPatterns
{
  private static bool $debug;

  public function __construct()
  {
    static::$debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
  }

  /**
   * Compare product price with competitor prices from web search
   *
   * Analyzes internal product price against competitor prices found in web search results.
   * Provides detailed comparison data, competitive status, and pricing recommendations.
   *
   * Algorithm:
   * 1. Extract prices from web search results
   * 2. Calculate price differences (absolute and percentage)
   * 3. Find cheapest and most expensive competitors
   * 4. Calculate average competitor price
   * 5. Determine competitive status (not_competitive, competitive, very_competitive)
   * 6. Generate pricing recommendation
   *
   * Competitive Status Rules:
   * - not_competitive: Internal price > 10% higher than average
   * - very_competitive: Internal price > 10% lower than average
   * - competitive: Internal price within ±10% of average
   *
   * @param array $product Internal product data with keys:
   *                       - 'name' (string): Product name
   *                       - 'price' (float): Internal product price
   * @param array $webResults Web search results from WebSearchTool::search()
   *                          Expected structure: ['items' => [['title' => ..., 'snippet' => ..., ...]]]
   * @return array Comparison data with structure:
   *               - 'success' (bool): Whether comparison succeeded
   *               - 'product_name' (string): Product name
   *               - 'internal_price' (float): Internal product price
   *               - 'competitor_prices' (array): List of competitor prices with source, url, price
   *               - 'comparison' (array): Detailed comparison data
   *                 - 'cheapest' (array|null): Cheapest competitor data
   *                 - 'most_expensive' (array|null): Most expensive competitor data
   *                 - 'average_competitor_price' (float): Average of all competitor prices
   *                 - 'price_differences' (array): Per-competitor price differences
   *                 - 'average_percentage_difference' (float): Average % difference
   *               - 'recommendation' (string): Pricing recommendation text
   *               - 'competitive_status' (string): 'not_competitive', 'competitive', or 'very_competitive'
   *               - 'total_competitors_found' (int): Number of competitors with prices
   *
   * @throws \Exception If price comparison fails
   *
   * @example
   * ```php
   * $product = ['name' => 'Product X', 'price' => 99.99];
   * $webResults = ['items' => [
   *   ['title' => 'Product X - $89.99', 'snippet' => '...'],
   *   ['title' => 'Product X - $109.99', 'snippet' => '...']
   * ]];
   * $comparison = WebSearchPatterns::comparePrice($product, $webResults);
   * // Returns: ['success' => true, 'competitive_status' => 'competitive', ...]
   * ```
   *
   * @since 1.0.0
   */
  public static function comparePrice(array $product, array $webResults): array
  {
    try {
      $internalPrice = (float)$product['price'];
      $productName = $product['name'];
      $competitorPrices = [];

      // Extract prices from web search results
      if (isset($webResults['items']) && is_array($webResults['items'])) {
        foreach ($webResults['items'] as $item) {
          $extractedPrice = self::extractPriceFromResult($item);
          
          if ($extractedPrice !== null) {
            $competitorPrices[] = [
              'source' => $item['source'] ?? 'Unknown',
              'url' => $item['link'] ?? '',
              'price' => $extractedPrice,
              'title' => $item['title'] ?? '',
              'snippet' => $item['snippet'] ?? '',
            ];
          }
        }
      }

      // No competitor prices found
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

      // Calculate average competitor price
      $avgCompetitorPrice = count($competitorPriceValues) > 0 
        ? array_sum($competitorPriceValues) / count($competitorPriceValues) 
        : 0;

      // Determine competitive status and recommendation
      $competitiveStatus = 'competitive';
      $recommendation = '';

      $avgDifference = $internalPrice - $avgCompetitorPrice;
      $avgPercentageDiff = $avgCompetitorPrice > 0 ? ($avgDifference / $avgCompetitorPrice) * 100 : 0;

      if ($avgPercentageDiff > 10) {
        // Our price is more than 10% higher than average
        $competitiveStatus = 'not_competitive';
        $recommendation = sprintf(
          "Your price (%.2f) is %.1f%% higher than the average competitor price (%.2f). Consider reducing the price to remain competitive.",
          $internalPrice,
          abs($avgPercentageDiff),
          $avgCompetitorPrice
        );
      } elseif ($avgPercentageDiff < -10) {
        // Our price is more than 10% lower than average
        $competitiveStatus = 'very_competitive';
        $recommendation = sprintf(
          "Your price (%.2f) is %.1f%% lower than the average competitor price (%.2f). You have a strong competitive advantage.",
          $internalPrice,
          abs($avgPercentageDiff),
          $avgCompetitorPrice
        );
      } else {
        // Our price is within 10% of average
        $competitiveStatus = 'competitive';
        $recommendation = sprintf(
          "Your price (%.2f) is competitive, within %.1f%% of the average competitor price (%.2f).",
          $internalPrice,
          abs($avgPercentageDiff),
          $avgCompetitorPrice
        );
      }

      // Add specific comparison to cheapest competitor
      if ($cheapest !== null) {
        $diffToCheapest = $internalPrice - $cheapest['competitor_price'];
        $percentDiffToCheapest = $cheapest['competitor_price'] > 0 
          ? ($diffToCheapest / $cheapest['competitor_price']) * 100 
          : 0;

        if ($percentDiffToCheapest > 5) {
          $recommendation .= sprintf(
            " The cheapest competitor (%s) offers this product at %.2f, which is %.1f%% lower than your price.",
            $cheapest['source'],
            $cheapest['competitor_price'],
            abs($percentDiffToCheapest)
          );
        }
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
      ];

    } catch (\Exception $e) {
      return [
        'success' => false,
        'error' => 'Unable to compare prices: ' . $e->getMessage(),
        'product_name' => $product['name'] ?? 'Unknown',
        'internal_price' => $product['price'] ?? 0,
      ];
    }
  }

  /**
   * Extract price from web search result
   *
   * Attempts to extract a price value from the title, snippet, or other fields
   * of a web search result. Supports multiple currency formats and patterns.
   *
   * Supported Formats:
   * - US/UK format: $99.99, €99.99, £99.99
   * - US/UK with thousand separators: $1,049.99, €1,234.56
   * - European format: 99,99€, 79,99€
   * - Currency suffix: 99.99€, 1,049.99$
   * - With label: "price: $99.99", "price: 1,049.99"
   * - Currency codes: 99.99 USD, 1,049.99 EUR, 99.99 GBP
   *
   * Supported Currencies:
   * - $ (US Dollar)
   * - € (Euro)
   * - £ (British Pound)
   * - USD, EUR, GBP (currency codes)
   *
   * Price Range:
   * - Minimum: 0.01
   * - Maximum: 999,999.99
   *
   * @param array $result Web search result item with keys:
   *                      - 'title' (string): Result title
   *                      - 'snippet' (string): Result snippet/description
   * @return float|null Extracted price as float, or null if no valid price found
   *
   * @example
   * ```php
   * $result = ['title' => 'Product X - $99.99', 'snippet' => 'Great deal!'];
   * $price = WebSearchPatterns::extractPriceFromResult($result);
   * // Returns: 99.99
   *
   * $result = ['title' => 'Product Y', 'snippet' => 'Price: €1,234.56'];
   * $price = WebSearchPatterns::extractPriceFromResult($result);
   * // Returns: 1234.56
   * ```
   *
   * @since 1.0.0
   */
  public static function extractPriceFromResult(array $result): ?float
  {
    $text = '';
    
    // Combine title and snippet for price extraction
    if (isset($result['title'])) {
      $text .= ' ' . $result['title'];
    }
    if (isset($result['snippet'])) {
      $text .= ' ' . $result['snippet'];
    }

    if (empty($text)) {
      return null;
    }

    // Price patterns for different formats
    $patterns = self::getPricePatterns();

    foreach ($patterns as $patternConfig) {
      if (preg_match($patternConfig['pattern'], $text, $matches)) {
        // Extract the numeric part
        $priceStr = $matches[1];
        
        // Remove thousand separators if present
        if (!empty($patternConfig['thousand_sep'])) {
          $priceStr = str_replace($patternConfig['thousand_sep'], '', $priceStr);
        }
        
        // Normalize decimal separator to dot
        if ($patternConfig['decimal_sep'] === ',') {
          $priceStr = str_replace(',', '.', $priceStr);
        }
        
        // Convert to float
        $price = (float)$priceStr;
        
        // Sanity check: price should be between 0.01 and 999999
        if ($price > 0 && $price < 1000000) {
          return $price;
        }
      }
    }

    return null;
  }

  /**
   * Get price extraction patterns
   *
   * Returns an array of regex patterns for extracting prices from text.
   * Each pattern includes configuration for thousand and decimal separators.
   *
   * Pattern Categories:
   * 1. Currency prefix with thousand separators: $1,049.99, €1,234.56
   * 2. Currency prefix without thousand separators: $99.99, €99.99
   * 3. European format with comma decimal: 99,99€, 79,99€
   * 4. Currency suffix with thousand separators: 1,049.99€, 999.99$
   * 5. Currency suffix without thousand separators: 99.99€, 99.99$
   * 6. Price with label and thousand separators: price: $1,049.99
   * 7. Price with label simple: price: 99.99
   * 8. Currency code suffix with thousand separators: 1,049.99 USD
   * 9. Currency code suffix simple: 99.99 EUR
   *
   * @return array Array of pattern configurations, each with:
   *               - 'pattern' (string): Regex pattern
   *               - 'thousand_sep' (string): Thousand separator character
   *               - 'decimal_sep' (string): Decimal separator character
   *
   * @since 1.0.0
   */
  private static function getPricePatterns(): array
  {
    return [
      // US/UK format with thousand separators: $1,049.99, €1,234.56
      [
        'pattern' => '/[\$€£]\s*(\d{1,3}(?:,\d{3})+\.\d{2})/',
        'thousand_sep' => ',',
        'decimal_sep' => '.',
      ],
      // US/UK format without thousand separators: $99.99, €99.99
      [
        'pattern' => '/[\$€£]\s*(\d{1,6}\.\d{2})/',
        'thousand_sep' => '',
        'decimal_sep' => '.',
      ],
      // European format with comma as decimal: 99,99€, 79,99€
      [
        'pattern' => '/(\d{1,6},\d{2})\s*[€£\$]/',
        'thousand_sep' => '',
        'decimal_sep' => ',',
      ],
      // US/UK format suffix: 1,049.99€, 999.99$
      [
        'pattern' => '/(\d{1,3}(?:,\d{3})+\.\d{2})\s*[\$€£]/',
        'thousand_sep' => ',',
        'decimal_sep' => '.',
      ],
      // Simple format suffix: 99.99€, 99.99$
      [
        'pattern' => '/(\d{1,6}\.\d{2})\s*[\$€£]/',
        'thousand_sep' => '',
        'decimal_sep' => '.',
      ],
      // Price with label: price: $1,049.99
      [
        'pattern' => '/price[:\s]+[\$€£]?\s*(\d{1,3}(?:,\d{3})+\.\d{2})/i',
        'thousand_sep' => ',',
        'decimal_sep' => '.',
      ],
      // Price with label simple: price: 99.99
      [
        'pattern' => '/price[:\s]+[\$€£]?\s*(\d{1,6}\.\d{2})/i',
        'thousand_sep' => '',
        'decimal_sep' => '.',
      ],
      // Currency code suffix: 1,049.99 USD, 99.99 EUR
      [
        'pattern' => '/(\d{1,3}(?:,\d{3})+\.\d{2})\s*(?:USD|EUR|GBP)/i',
        'thousand_sep' => ',',
        'decimal_sep' => '.',
      ],
      [
        'pattern' => '/(\d{1,6}\.\d{2})\s*(?:USD|EUR|GBP)/i',
        'thousand_sep' => '',
        'decimal_sep' => '.',
      ],
    ];
  }

  /**
   * Get supported currency symbols
   *
   * Returns an array of currency symbols supported by the price extraction logic.
   *
   * @return array List of currency symbols: ['$', '€', '£', '¥', 'CHF']
   *
   * @since 1.0.0
   */
  public static function getSupportedCurrencies(): array
  {
    return ['$', '€', '£', '¥', 'CHF'];
  }

  /**
   * Format price for display
   *
   * Formats a numeric price value with currency symbol for display.
   * Uses number_format() to ensure 2 decimal places.
   *
   * @param float $price Price value to format
   * @param string $currency Currency symbol (default: '$')
   * @return string Formatted price string (e.g., "$99.99")
   *
   * @example
   * ```php
   * $formatted = WebSearchPatterns::formatPrice(99.99, '$');
   * // Returns: "$99.99"
   *
   * $formatted = WebSearchPatterns::formatPrice(1234.56, '€');
   * // Returns: "€1,234.56"
   * ```
   *
   * @since 1.0.0
   */
  public static function formatPrice(float $price, string $currency = '$'): string
  {
    return $currency . number_format($price, 2);
  }

  /**
   * Validate price value
   *
   * Checks if a price value is valid (positive and within reasonable range).
   *
   * Valid Range:
   * - Minimum: 0.01
   * - Maximum: 999,999.99
   *
   * @param float $price Price value to validate
   * @return bool True if price is valid, false otherwise
   *
   * @example
   * ```php
   * WebSearchPatterns::isValidPrice(99.99);    // Returns: true
   * WebSearchPatterns::isValidPrice(0);        // Returns: false
   * WebSearchPatterns::isValidPrice(-10);      // Returns: false
   * WebSearchPatterns::isValidPrice(1000000);  // Returns: false
   * ```
   *
   * @since 1.0.0
   */
  public static function isValidPrice(float $price): bool
  {
    return $price > 0 && $price < 1000000;
  }

  /**
   * Enrich web search query with last_entity context
   *
   * Enriches web search queries with context from previous queries
   * This allows follow-up queries like "compare with competitors" to include the product name
   *
   * IMPORTANT: This method receives queries in ENGLISH (already translated)
   * All processing is done in English in a multilingual environment
   *
   * @param string $query Original web search query (in English)
   * @param string $entityName Name of the last entity discussed
   * @param string $entityType Type of the last entity (product, category, etc.)
   * @return string Enriched query
   */

  public static function  enrichWebSearchQuery(string $query, string $entityName, string $entityType): string
  {
    // Detect if query contains contextual keywords that need entity context
    // IMPORTANT: Only English keywords - queries are translated to English before processing
    $contextualKeywords = [
      // English comparison and alternative keywords
      'compare', 'comparison', 'competitor', 'competitors',
      'alternative', 'alternatives', 'similar', 'equivalent', 'equivalents',
      'versus', 'vs',
      // English contextual words
      'price', 'cost', 'review', 'reviews', 'rating', 'ratings',
      'features', 'specifications', 'specs',
    ];

    $lowerQuery = strtolower($query);
    $needsContext = false;

    foreach ($contextualKeywords as $keyword) {
      if (strpos($lowerQuery, $keyword) !== false) {
        $needsContext = true;
        break;
      }
    }

    if (!$needsContext) {
      // Query doesn't need context enrichment
      return $query;
    }

    // Enrich query by prepending entity name
    // Example: "compare with competitors" → "iPhone 17 Pro compare with competitors"
    $enrichedQuery = $entityName . ' ' . $query;

    /*
        if (static::$debug) {
          $this->securityLogger->logSecurityEvent(
            "Enriched web search query: '{$query}' → '{$enrichedQuery}'",
            'info'
          );
        }
    */
    return $enrichedQuery;
  }
}
