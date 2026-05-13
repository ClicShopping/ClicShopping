<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\DomainsAI\Hybrid\Patterns;

/**
 * QuerySplitterPatterns - Regex patterns for query splitting
 *
 * NOTE: These patterns are for FUTURE USE only.
 * Current implementation uses Pure LLM mode (no pattern matching).
 * Patterns are preserved here for potential future hybrid approaches.
 *
 * @package ClicShopping\AI\Domain\Patterns
 * @since 2025-12-30
 * @moved 2025-12-31 from SubHybridQueryProcessor\Patterns to Domain\Patterns
  *
 * @deprecated Pattern-based logic superseded by Pure LLM Mode
 *             Scheduled for removal in Q3 2026
 *             Use UnifiedQueryAnalyzer for intent classification instead
 *             See Domain/Patterns/DEPRECATED.md for migration guide
 **/
// DEPRECATED: Pattern-based logic superseded by Pure LLM Mode. Scheduled for removal in Q3 2026.

class QuerySplitterPatterns
{
  /**
   * Pattern for detecting report/analysis queries
   * 
   * Matches queries like:
   * - "create a report for iPhone"
   * - "generate analysis of sales"
   * - "make a detailed summary about products"
   *
   * @var string
   */
  public const REPORT_QUERY_PATTERN = '/\b(create|generate|make|build)\s+(?:(?:a|an)\s+)?(?:(?:analysis|detailed|comprehensive)\s+)?(report|analysis|summary)\s+(?:for|of|on|about)\s+(.+)/i';

  /**
   * Delimiter patterns for splitting queries
   *
   * @var array<string, string> Array of delimiter types and their patterns
   */
  public const DELIMITER_PATTERNS = [
    'comma' => ',',
    'and_then' => '/\s+and\s+then\s+/i',
    'period' => '/\.\s+/',  // Period followed by space (sentence boundary)
    'and' => '/\band\b/i',
    'question' => '?',
    'semicolon' => ';'
  ];

  /**
   * Pattern for detecting sequential dependencies
   * 
   * Matches phrases like:
   * - "and then"
   * - "after that"
   * - "next"
   *
   * @var string
   */
  public const SEQUENTIAL_DEPENDENCY_PATTERN = '/\b(and\s+then|after\s+that|next|then)\b/i';

  /**
   * Pattern for simple split on connectors (fallback)
   *
   * @var string
   */
  public const SIMPLE_SPLIT_PATTERN = '/\b(and|then|also)\b/i';

  /**
   * Financial metrics patterns (English only - query is already translated)
   * 
   * Order by specificity (more specific patterns first)
   * Used for extracting base metric from query text.
   *
   * @var array<string, string> Pattern => normalized metric name
   */
  public const FINANCIAL_METRICS = [
    'total revenue' => 'revenue',
    'gross revenue' => 'revenue',
    'net revenue' => 'revenue',
    'total sales' => 'sales',
    'gross sales' => 'sales',
    'net sales' => 'sales',
    'revenue' => 'revenue',
    'sales' => 'sales',
    'turnover' => 'turnover',
    'profit' => 'profit',
    'margin' => 'margin',
    'income' => 'income',
    'earnings' => 'earnings',
    'expenses' => 'expenses',
    'costs' => 'costs',
    'orders' => 'orders',
  ];

  /**
   * Financial metrics regex patterns (for more complex matching)
   * 
   * Used when simple string matching is not sufficient.
   *
   * @var array<string, string> Metric name => regex pattern
   */
  public const FINANCIAL_METRICS_REGEX = [
    'revenue' => '/\b(revenue|turnover|sales\s+revenue)\b/i',
    'sales' => '/\b(sales|total\s+sales)\b/i',
    'orders' => '/\b(orders|order\s+count)\b/i',
    'profit' => '/\b(profit|margin|earnings)\b/i',
    'quantity' => '/\b(quantity|units|items)\b/i',
    'customers' => '/\b(customers|customer\s+count)\b/i',
  ];

  /**
   * Relative time patterns (English only - query is already translated)
   * 
   * Used for extracting time range from query text.
   *
   * @var array<string, string> Pattern => normalized time range
   */
  public const RELATIVE_TIME_PATTERNS = [
    'this year' => 'this year',
    'last year' => 'last year',
    'current year' => 'current year',
    'previous year' => 'last year',
    'next year' => 'next year',
    'this month' => 'this month',
    'last month' => 'last month',
    'this quarter' => 'this quarter',
    'last quarter' => 'last quarter',
  ];

  /**
   * Relative time regex patterns (for more complex matching)
   * 
   * Used when simple string matching is not sufficient.
   *
   * @var array<string, string> Time range => regex pattern
   */
  public const RELATIVE_TIME_PATTERNS_REGEX = [
    'this year' => '/\bthis\s+year\b/i',
    'last year' => '/\blast\s+year\b/i',
    'this month' => '/\bthis\s+month\b/i',
    'last month' => '/\blast\s+month\b/i',
    'this quarter' => '/\bthis\s+quarter\b/i',
    'last quarter' => '/\blast\s+quarter\b/i',
  ];

  /**
   * Year extraction pattern
   * 
   * Matches patterns like "year 2025" or just "2025"
   *
   * @var string
   */
  public const YEAR_PATTERN = '/\b(year\s+)?(\d{4})\b/i';

  /**
   * Price comparison detection patterns (English only - query is already translated)
   * 
   * Used for detecting price comparison queries.
   *
   * @var array<string> Array of regex patterns
   */
  public const PRICE_COMPARISON_PATTERNS = [
    // Pattern 1: "price/cost" + "compare/competitor"
    '/\b(price|cost|prix)s?\b.*\b(compare|compar|competitor|concurrent)/i',
    // Pattern 2: "compare/competitor" + "price/cost"
    '/\b(compare|compar|competitor|concurrent)\b.*\b(price|cost|prix)s?\b/i',
    // Pattern 3: Action verb + "price/cost" + "compare/competitor"
    '/\b(give|show|tell|get|find)\b.*\b(price|cost|prix)s?\b.*\b(compare|compar|competitor)/i',
    // Pattern 4: "compare prices/price for" (flexible word order)
    '/\b(compar[e]?|competitor)\b\s+(price|cost|prix)s?\s+(for|of)\b/i',
    // Pattern 5: "price comparison" phrase
    '/\b(price|cost|prix)s?\s+(comparison|compar[e]?)/i',
    // Pattern 6: "versus/vs" with price context
    '/\b(price|cost|prix)s?\b.*\b(versus|vs\.?)\b/i',
    '/\b(versus|vs\.?)\b.*\b(price|cost|prix)s?\b/i',
    // Pattern 7: "compare" + "prices/price" (even without explicit "for")
    '/\b(compare|compar)\b\s+(price|cost|prix)s?\b/i',
  ];

  /**
   * Product name extraction patterns (English only - query is already translated)
   * 
   * Used for extracting product name from price comparison queries.
   * Organized by extraction phase for sequential application.
   *
   * @var array<string, array<string>> Pattern groups by phase
   */
  public const PRODUCT_NAME_EXTRACTION_PATTERNS = [
    // Phase 1: Remove leading action phrases and "compare" at start
    'leading' => [
      '/^\s*(give|show|tell|get|find)\s+(me\s+)?(the\s+)?/i',
      '/^\s*(what\s+is|what\'s)\s+(the\s+)?/i',
      '/^\s*(compare|compar|competitor)\s+(price|cost|prix)s?\s+(of|for)\s+/i',
      '/^\s*(compare|compar|competitor)\s+(price|cost|prix)s?\s+/i',  // Remove "compare price(s)" at start
      '/^\s*(compare|compar|competitor)\s+/i',  // Remove standalone "compare" at start
    ],
    // Phase 2: Remove "price/cost of/for" phrases
    'price' => [
      '/\b(price|cost|prix)\s+(of|for|de)\s+/i',
      '/\b(the\s+)?(price|cost|prix)\s+/i',
    ],
    // Phase 3: Remove trailing comparison phrases (most aggressive)
    'trailing' => [
      '/\s+(and\s+)?(compare|compar|competitor|concurrent).*$/i',
      '/\s+(with|to)\s+(other\s+)?.*$/i',
      '/\s+(versus|vs\.?)(\s+.*)?$/i',  // Remove "versus" or "vs" with or without following text
      '/\s+(price\s+)?comparison.*$/i',
      '/\s+(on|at|from)\s+[a-z0-9.-]+$/i',  // Remove "on Amazon", "at Amazon", "from Amazon" (English only - query is already translated)
    ],
    // Phase 4: Clean up "the new" constructs
    'cleanup' => [
      '/\bthe\s+new\s+/i' => 'new ',  // Replace "the new" with "new"
      '/^\s*the\s+/i' => '',          // Remove standalone "the" at start
    ],
  ];

  /**
   * Extract base metric from query
   * 
   * @param string $query Query text (already translated to English)
   * @return string|null Base metric or null if not found
   */
  public static function extractBaseMetric(string $query): ?string
  {
    $query = strtolower($query);
    
    foreach (self::FINANCIAL_METRICS as $pattern => $metric) {
      if (strpos($query, $pattern) !== false) {
        return $metric;
      }
    }
    
    return null;
  }

  /**
   * Extract base metric from query using regex patterns
   * 
   * More powerful matching using regex patterns.
   * Returns default value if not found.
   * 
   * @param string $query Query text (already translated to English)
   * @param string $default Default value if not found
   * @return string Base metric or default
   */
  public static function extractBaseMetricWithRegex(string $query, string $default = 'revenue'): string
  {
    $query = strtolower($query);
    
    foreach (self::FINANCIAL_METRICS_REGEX as $metric => $pattern) {
      if (preg_match($pattern, $query)) {
        return $metric;
      }
    }
    
    return $default;
  }

  /**
   * Extract time range from query
   * 
   * @param string $query Query text (already translated to English)
   * @return string|null Time range or null if not found
   */
  public static function extractTimeRange(string $query): ?string
  {
    $query = strtolower($query);
    
    // Check for specific year patterns first
    if (preg_match(self::YEAR_PATTERN, $query, $matches)) {
      return "year {$matches[2]}";
    }
    
    // Check for relative time patterns
    foreach (self::RELATIVE_TIME_PATTERNS as $pattern => $range) {
      if (strpos($query, $pattern) !== false) {
        return $range;
      }
    }
    
    return null;
  }

  /**
   * Extract time range from query using regex patterns
   * 
   * More powerful matching using regex patterns.
   * Returns default value if not found.
   * 
   * @param string $query Query text (already translated to English)
   * @param string $default Default value if not found
   * @return string Time range or default
   */
  public static function extractTimeRangeWithRegex(string $query, string $default = 'this year'): string
  {
    $query = strtolower($query);
    
    // Check for specific year patterns first
    if (preg_match(self::YEAR_PATTERN, $query, $matches)) {
      return 'year ' . $matches[2];
    }
    
    // Check for relative time patterns using regex
    foreach (self::RELATIVE_TIME_PATTERNS_REGEX as $range => $pattern) {
      if (preg_match($pattern, $query)) {
        return $range;
      }
    }
    
    return $default;
  }

  /**
   * Get all patterns as an array
   *
   * @return array<string, mixed> All patterns
   */
  public static function getAllPatterns(): array
  {
    return [
      'report_query' => self::REPORT_QUERY_PATTERN,
      'delimiters' => self::DELIMITER_PATTERNS,
      'sequential_dependency' => self::SEQUENTIAL_DEPENDENCY_PATTERN,
      'simple_split' => self::SIMPLE_SPLIT_PATTERN,
    ];
  }

  /**
   * Check if a pattern matches a query
   *
   * @param string $pattern Pattern to test
   * @param string $query Query to test against
   * @return bool True if pattern matches
   */
  public static function matches(string $pattern, string $query): bool
  {
    return preg_match($pattern, $query) === 1;
  }

  /**
   * Extract matches from a query using a pattern
   *
   * @param string $pattern Pattern to use
   * @param string $query Query to extract from
   * @return array|null Matches array or null if no match
   */
  public static function extract(string $pattern, string $query): ?array
  {
    if (preg_match($pattern, $query, $matches)) {
      return $matches;
    }
    return null;
  }

  /**
   * Check if query is a price comparison query
   * 
   * @param string $query Query text (already translated to English)
   * @return bool True if price comparison query detected
   */
  public static function isPriceComparisonQuery(string $query): bool
  {
    foreach (self::PRICE_COMPARISON_PATTERNS as $pattern) {
      if (preg_match($pattern, $query)) {
        return true;
      }
    }
    return false;
  }

  /**
   * Extract product name from price comparison query
   * 
   * Applies patterns sequentially in phases to clean up the query
   * and extract the product name.
   * 
   * @param string $query Query text (already translated to English)
   * @return string Cleaned product name
   */
  public static function extractProductName(string $query): string
  {
    $productName = $query;
    
    // Phase 1: Remove leading action phrases
    foreach (self::PRODUCT_NAME_EXTRACTION_PATTERNS['leading'] as $pattern) {
      $productName = preg_replace($pattern, '', $productName);
    }
    
    // Phase 2: Remove "price/cost of/for" phrases
    foreach (self::PRODUCT_NAME_EXTRACTION_PATTERNS['price'] as $pattern) {
      $productName = preg_replace($pattern, '', $productName);
    }
    
    // Phase 3: Remove trailing comparison phrases
    foreach (self::PRODUCT_NAME_EXTRACTION_PATTERNS['trailing'] as $pattern) {
      $productName = preg_replace($pattern, '', $productName);
    }
    
    // Phase 4: Clean up "the new" constructs
    foreach (self::PRODUCT_NAME_EXTRACTION_PATTERNS['cleanup'] as $pattern => $replacement) {
      $productName = preg_replace($pattern, $replacement, $productName);
    }
    
    // Phase 5: Clean up extra whitespace
    $productName = preg_replace('/\s+/', ' ', trim($productName));
    
    return $productName;
  }
}
