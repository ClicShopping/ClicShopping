<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\Config;

use ClicShopping\AI\Security\SecurityLogger;

/**
 * DomainKeywordsLoader Class
 *
 * Domain-agnostic keyword loader for dynamic keyword loading from business domains.
 * This class enables Core AI to remain domain-agnostic by loading domain-specific
 * keywords dynamically from Apps/AI/{Domain}/ at runtime.
 *
 * CRITICAL ARCHITECTURE RULE:
 * - Core AI (Core/ClicShopping/AI/) MUST be domain-agnostic
 * - NO hardcoded domain-specific keywords in Core AI
 * - Domain-specific keywords go in Apps/AI/{Domain}/
 * - Core AI loads keywords dynamically via this loader
 *
 * Purpose:
 * - Enable multi-domain support (Ecommerce, HR, Finance, Trading, etc.)
 * - Load web search keywords dynamically from domain configuration
 * - Support graceful degradation when domain not found
 * - Provide logging for keyword loading operations
 * - Enable domain extensibility without Core AI modifications
 *
 * Architecture Flow:
 * HybridQueryHandler → DomainKeywordsLoader → Apps/AI/{Domain}/Patterns/HybridPreFilter
 *   → Load Keywords → Return to Handler → Use for Detection
 *
 * Supported Domains:
 * - Ecommerce: Apps/AI/Ecommerce/Classes/ClicShoppingAdmin/Patterns/HybridPreFilter.php
 * - HR: Apps/AI/HR/Classes/ClicShoppingAdmin/Patterns/HybridPreFilter.php (future)
 * - Finance: Apps/AI/Finance/Classes/ClicShoppingAdmin/Patterns/HybridPreFilter.php (future)
 * - Trading: Apps/AI/Trading/Classes/ClicShoppingAdmin/Patterns/HybridPreFilter.php (future)
 *
 * Keyword Types:
 * - Web search platforms: amazon, ebay, google, alibaba, aliexpress, etsy
 * - Web search actions: compare with, search online, find on, check on
 * - Web search indicators: trends, news, latest, recent, competitors
 * - Domain-specific: Defined by each business domain
 *
 * 🔧 CACHE OPTIMIZATION 2026-04-30:
 * - Added in-memory caching for loaded keywords
 * - Cache persists for the lifetime of the DomainKeywordsLoader instance
 * - Eliminates repeated file I/O for the same domain
 * - Expected performance improvement: ~10-20ms per cached lookup
 *
 * @package ClicShopping\AI\Config
 * @version 1.1.0
 * @since 2026-04-29
 * @see unit_test/2026_04_30/CACHE_OPTIMIZATION_ANALYSIS.md
 */
class DomainKeywordsLoader
{
  private SecurityLogger $securityLogger;
  private bool $debug;
  private array $keywordCache = [];

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->debug = $debug;
    $this->securityLogger = new SecurityLogger();
  }

  /**
   * Load web search keywords for a specific domain
   *
   * Dynamically loads web search keywords from the specified business domain.
   * Keywords are loaded from Apps/AI/{Domain}/Classes/ClicShoppingAdmin/Patterns/HybridPreFilter.php
   *
   * Loading strategy:
   * 1. Check cache for previously loaded keywords
   * 2. Build domain class path
   * 3. Check if domain class exists
   * 4. Instantiate domain class and extract keywords
   * 5. Cache keywords for future requests
   * 6. Return keywords array
   *
   * Graceful degradation:
   * - Returns empty array if domain not found
   * - Returns empty array if class doesn't exist
   * - Returns empty array if keywords cannot be extracted
   * - Logs warnings for all failure cases
   *
   * Domain class requirements:
   * - Must be located at Apps/AI/{Domain}/Classes/ClicShoppingAdmin/Patterns/HybridPreFilter.php
   * - Must have a static method to extract keywords (or public properties)
   * - Keywords should be organized by type (platforms, actions, indicators)
   *
   * Examples:
   * - loadWebSearchKeywords('Ecommerce') → ['amazon', 'ebay', 'google', ...]
   * - loadWebSearchKeywords('HR') → [] (not implemented yet)
   * - loadWebSearchKeywords('Finance') → [] (not implemented yet)
   * - loadWebSearchKeywords('Unknown') → [] (domain not found)
   *
   * @param string $domain Domain name (e.g., 'Ecommerce', 'HR', 'Finance', 'Trading')
   * @return array Web search keywords for the domain (empty array if not found)
   */
  public function loadWebSearchKeywords(string $domain): array
  {
    // 🔧 CACHE OPTIMIZATION: Check in-memory cache first
    if (isset($this->keywordCache[$domain])) {
      if ($this->debug) {
        $this->securityLogger->logStructured('info', 'DomainKeywordsLoader', 'keywords_from_memory_cache', [
          'domain' => $domain,
          'keyword_count' => count($this->keywordCache[$domain]),
          'cache_type' => 'in_memory'
        ]);
      }
      return $this->keywordCache[$domain];
    }

    // Cache MISS - load from file system
    
    // Build domain class path
    $domainClass = "ClicShopping\\Apps\\AI\\{$domain}\\Classes\\ClicShoppingAdmin\\Patterns\\HybridPreFilter";

    // Check if domain class exists
    if (!class_exists($domainClass)) {
      if ($this->debug) {
        $this->securityLogger->logStructured('warning', 'DomainKeywordsLoader', 'domain_not_found', [
          'domain' => $domain,
          'expected_class' => $domainClass,
          'note' => 'Domain not implemented or class not found'
        ]);
      }
      
      // 🔧 CACHE OPTIMIZATION: Cache empty result to avoid repeated lookups
      $this->keywordCache[$domain] = [];
      return [];
    }

    try {
      // Extract keywords from domain class
      // We need to read the class source code to extract keywords
      // since HybridPreFilter uses local variables, not class properties
      
      $keywords = $this->extractKeywordsFromClass($domainClass, $domain);
      
      // 🔧 CACHE OPTIMIZATION: Store in memory cache
      $this->keywordCache[$domain] = $keywords;
      
      if ($this->debug) {
        $this->securityLogger->logStructured('info', 'DomainKeywordsLoader', 'keywords_loaded_and_cached', [
          'domain' => $domain,
          'keyword_count' => count($keywords),
          'sample_keywords' => array_slice($keywords, 0, 5),
          'cache_type' => 'in_memory'
        ]);
      }
      
      return $keywords;
      
    } catch (\Exception $e) {
      $this->securityLogger->logStructured('error', 'DomainKeywordsLoader', 'keyword_loading_failed', [
        'domain' => $domain,
        'error' => $e->getMessage()
      ]);
      
      // 🔧 CACHE OPTIMIZATION: Cache empty result
      $this->keywordCache[$domain] = [];
      return [];
    }
  }

  /**
   * Extract keywords from domain class
   *
   * Reads the domain class source code and extracts web search keywords.
   * This is necessary because HybridPreFilter uses local variables, not class properties.
   *
   * Extraction strategy:
   * 1. Get class file path via reflection
   * 2. Read file contents
   * 3. Parse $webSearchKeywords array definition
   * 4. Extract keyword values
   * 5. Return as flat array
   *
   * @param string $domainClass Fully qualified domain class name
   * @param string $domain Domain name for logging
   * @return array Extracted keywords
   * @throws \Exception If extraction fails
   */
  private function extractKeywordsFromClass(string $domainClass, string $domain): array
  {
    // Use reflection to get class file path
    $reflection = new \ReflectionClass($domainClass);
    $classFile = $reflection->getFileName();
    
    if (!$classFile || !file_exists($classFile)) {
      throw new \Exception("Class file not found for domain: {$domain}");
    }
    
    // Read file contents
    $contents = file_get_contents($classFile);
    
    if ($contents === false) {
      throw new \Exception("Failed to read class file for domain: {$domain}");
    }
    
    // Extract $webSearchKeywords array
    // Pattern: $webSearchKeywords = [ ... ];
    $pattern = '/\$webSearchKeywords\s*=\s*\[(.*?)\];/s';
    
    if (!preg_match($pattern, $contents, $matches)) {
      if ($this->debug) {
        $this->securityLogger->logStructured('warning', 'DomainKeywordsLoader', 'no_keywords_found', [
          'domain' => $domain,
          'note' => 'No $webSearchKeywords array found in class'
        ]);
      }
      return [];
    }
    
    // Extract keyword values from array definition
    $arrayContent = $matches[1];
    
    // Pattern: 'keyword' or "keyword" (with optional comments)
    $keywordPattern = '/[\'"]([^\'"]+)[\'"]/';
    
    if (!preg_match_all($keywordPattern, $arrayContent, $keywordMatches)) {
      return [];
    }
    
    // Return extracted keywords
    $keywords = $keywordMatches[1];
    
    // Remove duplicates and empty values
    $keywords = array_filter(array_unique($keywords), function($keyword) {
      return !empty(trim($keyword));
    });
    
    return array_values($keywords);
  }

  /**
   * Get all supported domains
   *
   * Returns list of all domains that have keyword definitions.
   * Used for discovery and monitoring.
   *
   * Discovery strategy:
   * 1. Scan Apps/AI/ directory for domain subdirectories
   * 2. Check each domain for HybridPreFilter class
   * 3. Return list of domains with keyword definitions
   *
   * @return array List of supported domain names
   */
  public function getSupportedDomains(): array
  {
    $supportedDomains = [];
    
    // Base path for domain apps
    // Apps/AI/ is inside Core/ClicShopping/Apps/AI/
    $basePath = CLICSHOPPING_BASE_DIR . 'Apps/AI/';
    
    if (!is_dir($basePath)) {
      return [];
    }
    
    // Scan for domain directories
    $domains = scandir($basePath);
    
    foreach ($domains as $domain) {
      if ($domain === '.' || $domain === '..') {
        continue;
      }
      
      $domainPath = $basePath . $domain;
      
      if (!is_dir($domainPath)) {
        continue;
      }
      
      // Check if HybridPreFilter exists
      $filterPath = $domainPath . '/Classes/ClicShoppingAdmin/Patterns/HybridPreFilter.php';
      
      if (file_exists($filterPath)) {
        $supportedDomains[] = $domain;
      }
    }
    
    return $supportedDomains;
  }

  /**
   * Clear keyword cache
   *
   * Clears the internal keyword cache.
   * Useful for testing and when domain keywords are updated.
   *
   * @return void
   */
  public function clearCache(): void
  {
    $this->keywordCache = [];
    
    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'DomainKeywordsLoader', 'cache_cleared', [
        'note' => 'Keyword cache cleared'
      ]);
    }
  }

  /**
   * Get cache statistics
   *
   * Returns statistics about the keyword cache.
   * Used for monitoring and debugging.
   *
   * @return array Cache statistics
   */
  public function getCacheStats(): array
  {
    $stats = [
      'cached_domains' => array_keys($this->keywordCache),
      'total_cached_keywords' => 0
    ];
    
    foreach ($this->keywordCache as $domain => $keywords) {
      $stats['total_cached_keywords'] += count($keywords);
      $stats['domains'][$domain] = count($keywords);
    }
    
    return $stats;
  }
}
