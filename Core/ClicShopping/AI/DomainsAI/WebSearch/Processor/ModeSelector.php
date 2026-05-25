<?php
/**
 * ModeSelector.php
 *
 * Mode selection component for the unified websearch engine.
 * Determines which search mode(s) to execute based on detected intent.
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Processor
 * @since 2026-05-05
 *
 * Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.1.1, 9.1.2, 9.1.3, 9.1.4, 9.1.5
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Processor;

use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\AI\DomainsAI\WebSearch\Logger\WebSearchLogger;
use ClicShopping\AI\DomainsAI\WebSearch\Patterns\LocationPatterns;
use ClicShopping\AI\InterfacesAI\SiteRouterInterface;
use ClicShopping\AI\RegistryAI\WebSearchEngineRegistry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * ModeSelector Class
 *
 * Implements mode selection logic for the three-mode websearch architecture.
 * Determines whether to use single mode or hybrid mode based on intent analysis.
 *
 * Mode Selection Rules:
 * - Explicit mode_hint → Use specified mode (overrides automatic detection)
 * - price_comparison + no target_site → Mode B (Google Shopping)
 * - price_comparison + target_site not in DB → Mode B with site filter
 * - price_comparison + target_site in DB → Hybrid (Mode B + Mode C)
 * - market_research + no target_site → Mode A (AI Overview)
 * - market_research + target_site in DB → Hybrid (Mode A + Mode C)
 * - market_research + target_site not in DB → Mode A only
 * - product_discovery → Mode A (AI Overview)
 * - Default → Mode A
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Processor
 */
class ModeSelector
{
  /**
   * @var bool Debug mode flag
   */
  private bool $debug;

  /**
   * @var string Database table prefix
   */
  private string $prefixDb;

  /**
   * @var string|null Current query being processed (for analytics)
   */
  private ?string $currentQuery = null;
  private mixed $language;

  /**
   * @var array User notifications (warnings, errors, info messages)
   * 🆕 NEW (2026-05-07): Store notifications for unavailable sites
   */
  private array $userNotifications = [];

  /**
   * Constructor
   */
  public function __construct()
  {
    $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER == 'True';
    $this->prefixDb = CLICSHOPPING::getConfig('db_table_prefix');
    $this->language = Registry::get('Language');
  }

  /**
   * Simple logging helper
   *
   * @param string $message Log message
   * @param array $context Context data
   */
  private function log(string $message, array $context = []): void
  {
    $contextStr = !empty($context) ? ' - ' . json_encode($context) : '';
    error_log("ModeSelector: {$message}{$contextStr}");
  }

  /**
   * Select appropriate search mode(s) based on detected intent
   *
   * Implements mode selection logic according to requirements 9.1-9.6.
   * Returns array of selected modes (single or hybrid).
   *
   * @param array $intent Intent structure with keys: product, intent, location, target_site, mode_hint
   * @param array $options Additional options (reserved for future use)
   * @return array Selected modes array
   */
  public function selectModes(array $intent, array $options = []): array
  {
    if ($this->debug) {
      error_log("ModeSelector::selectModes() - Intent: " . json_encode($intent));
    }

    // Store current query for analytics
    $this->currentQuery = $options['query'] ?? '';

    // Extract intent fields
    $intentType = $intent['intent'] ?? 'product_discovery';
    $targetSite = $intent['target_site'] ?? null;
    $modeHint = $intent['mode_hint'] ?? null;

    // Rule 1: Explicit mode_hint overrides automatic detection
    if ($modeHint !== null) {
      $selectedModes = $this->resolveModeHint($modeHint);
      
      $this->log("Mode selection: Explicit mode_hint used", [
        'mode_hint' => $modeHint,
        'selected_modes' => $selectedModes
      ]);

      return $selectedModes;
    }

    // Rule 2-4: Price comparison intent - MAY RETURN UserInputRequiredResponse
    if ($intentType === 'price_comparison') {
      return $this->selectPriceComparisonModes($targetSite);
    }

    // Rule 5-7: Market research intent
    if ($intentType === 'market_research') {
      return $this->selectMarketResearchModes($targetSite);
    }

    // Rule 8b: Trend analysis intent → Mode E (Google Trends chart)
    if ($intentType === 'trend_analysis') {
      $this->log("Mode selection: Trend analysis → Mode E", [
        'intent_type' => $intentType,
        'target_site' => $targetSite,
      ]);

      return ['mode_e_google_trends'];
    }

    // Rule 8: Product discovery intent
    if ($intentType === 'product_discovery') {
      // If a domain SiteRouter owns the target_site → use the modes it recommends
      $router = $this->findSiteRouter($targetSite);
      if ($router !== null) {
        $modes = $router->getRecommendedModes($intentType);
        if (!empty($modes)) {
          $this->log("Mode selection: Product discovery with domain-routed target_site", [
            'intent_type' => $intentType,
            'target_site' => $targetSite,
            'router_id' => $router->getRouterId(),
            'selected_modes' => $modes,
          ]);

          return $modes;
        }
      }

      $this->log("Mode selection: Product discovery → Mode A", [
        'intent_type' => $intentType
      ]);

      return ['mode_a_ai_overview'];
    }

    // Default: Mode A (AI Overview)
    $this->log("Mode selection: Default → Mode A", [
      'intent_type' => $intentType
    ]);

    return ['mode_a_ai_overview'];
  }

  /**
   * Ask user for mode preference (user-centric approach)
   *
   * Displays 3 options to user:
   * 1. WebSearch (Mode C - RAG WebSearch)
   * 2. Google Shopping (Mode B - recommended)
   * 3. Both (Mode C + Mode B - comprehensive)
   *
   * Implements Requirement 4: User-centric mode selection
   *
   * @deprecated CLI mode removed - automatic mode selection used instead
   * @return string User choice ('1', '2', '3', or empty string if timeout)
   */
  private function askUserForModePreference(): string
  {
    // Present options to user
    echo "\n🔍 How would you like to analyze prices?\n\n";
    
    echo "1️⃣  Analyse via WebSearch\n";
    echo "   → Search specific competitor sites (Amazon, Fnac, etc.)\n";
    echo "   → More targeted results\n\n";
    
    echo "2️⃣  Analyse via Google Shopping (recommended)\n";
    echo "   → Broad competitor search across all merchants\n";
    echo "   → More comprehensive coverage\n\n";
    
    echo "3️⃣  Analyse with WebSearch and Google Shopping\n";
    echo "   → Both targeted and broad search\n";
    echo "   → Most complete analysis\n\n";
    
    echo "Choose (1, 2, or 3): ";
    
    // Get user input with timeout
    $choice = $this->getUserInputWithTimeout(30);
    
    if ($this->debug) {
      error_log("ModeSelector::askUserForModePreference() - User choice: " . ($choice ?: 'timeout'));
    }
    
    return $choice;
  }

  /**
   * Get user input with timeout
   *
   * Uses stream_select() for timeout handling.
   * Returns user input or empty string if timeout.
   *
   * Implements Requirement 4: Timeout handling (30 seconds)
   *
   * @deprecated CLI mode removed - automatic mode selection used instead
   * @param int $timeout Timeout in seconds
   * @return string User input (trimmed) or empty string if timeout
   */
  private function getUserInputWithTimeout(int $timeout): string
  {
    // Check if running in CLI mode
    if (php_sapi_name() !== 'cli') {
      // Not in CLI mode - return default choice
      $this->log("Not in CLI mode, using default choice", [
        'sapi_name' => php_sapi_name()
      ]);
      return '2'; // Default to Google Shopping
    }

    // Check if STDIN is available
    if (!defined('STDIN') || !is_resource(STDIN)) {
      $this->log("STDIN not available, using default choice");
      return '2'; // Default to Google Shopping
    }

    $read = [STDIN];
    $write = null;
    $except = null;
    
    // Wait for input with timeout
    $result = stream_select($read, $write, $except, $timeout);
    
    if ($result === false) {
      // Error occurred
      $this->log("stream_select() error, using default choice");
      return '';
    }
    
    if ($result === 0) {
      // Timeout occurred
      $this->log("User input timeout ({$timeout}s), using default choice");
      return '';
    }
    
    // Input available - read it
    $input = fgets(STDIN);
    
    if ($input === false) {
      $this->log("Failed to read from STDIN, using default choice");
      return '';
    }
    
    return trim($input);
  }

  /**
   * Map user choice to mode identifiers
   *
   * Maps user input to actual mode identifiers:
   * - '1' → Mode C (RAG WebSearch)
   * - '2' → Mode B (Google Shopping)
   * - '3' → Mode C + Mode B (Hybrid)
   * - Default → Mode B (Google Shopping)
   *
   * Implements Requirement 4: User choice mapping
   *
   * @param string $choice User choice ('1', '2', '3', or other)
   * @return array Selected modes
   */
  private function mapUserChoiceToModes(string $choice): array
  {
    switch ($choice) {
      case '1':
      case 'websearch':
        // Option 1: WebSearch (RAG - Specific competitor sites)
        $this->log("User selected: WebSearch (Mode C)", ['choice' => $choice]);
        return ['mode_c_rag_websearch'];
      
      case '2':
      case 'google_shopping':
        // Option 2: Google Shopping (Broad competitor search)
        $this->log("User selected: Google Shopping (Mode B)", ['choice' => $choice]);
        return ['mode_b_google_shopping'];
      
      case '3':
      case 'both':
        // Option 3: Both (Comprehensive search)
        $this->log("User selected: Both (Mode C + Mode B)", ['choice' => $choice]);
        return ['mode_c_rag_websearch', 'mode_b_google_shopping'];
      
      default:
        // Default: Google Shopping (most common use case)
        $this->log("Invalid or timeout choice, using default: Google Shopping (Mode B)", ['choice' => $choice]);
        return ['mode_b_google_shopping'];
    }
  }

  /**
   * Log user choice to analytics table
   *
   * Logs user mode selection choice to clic_rag_mode_selection_analytics table
   * for analytics and debugging purposes.
   *
   * Implements Requirement 4: Analytics logging
   *
   * @param string $choice User choice
   * @param array $selectedModes Selected modes array
   */
  private function logUserChoice(string $choice, array $selectedModes): void
  {
    try {
      // Get user_id from session if available
      $userId = $_SESSION['user_id'] ?? null;
      
      // Prepare data for logging
      $tableName = $this->prefixDb . 'rag_mode_selection_analytics';
      
      $sql = "INSERT INTO {$tableName} 
              (user_id, query, intent, user_choice, selected_modes, date_created) 
              VALUES (:user_id, :query, :intent, :user_choice, :selected_modes, NOW())";
      
      $params = [
        'user_id' => $userId,
        'query' => $this->currentQuery,
        'intent' => 'price_comparison',
        'user_choice' => $choice,
        'selected_modes' => json_encode($selectedModes)
      ];
      
      // Execute via Doctrine ORM (agnostic layer requirement)
      DoctrineOrm::execute($sql, $params);
      
      if ($this->debug) {
        error_log("ModeSelector::logUserChoice() - Logged to analytics: " . json_encode($params));
      }
      
    } catch (\Exception $e) {
      // Handle database errors gracefully - don't fail the request
      $this->log("Error logging user choice to analytics", [
        'error' => $e->getMessage(),
        'choice' => $choice,
        'selected_modes' => $selectedModes
      ]);
    }
  }

  /**
   * Select modes for price comparison intent
   *
   * Implements requirements 9.2, 9.3, 9.4 + Multi-site support (2026-05-07)
   *   + Domain-routed engines (2026-05-24, agnostic refactor):
   * - No target_site → Mode B
   * - target_site owned by a domain SiteRouter → modes returned by the router
   *   (e.g. Ecommerce/Amazon → Mode D + Mode B hybrid)
   * - target_site not in DB and unrouted → Mode B only + User notification
   * - One target_site in DB → Hybrid (Mode B + Mode C)
   * - Multiple target_sites in DB → Hybrid (Mode B + Mode C for all sites)
   *
   * IMPLEMENTATION STATUS:
   * ✓ target_site handling implemented
   * ✓ Multi-site support (e.g. multiple TLDs of the same domain)
   * ✓ Domain SiteRouter integration via WebSearchEngineRegistry (agnostic)
   * ✓ User notification when site not available
   * ✓ Hybrid mode activated when target_site exists
   * ✓ Automatic mode selection based on target_site availability
   * ✗ CLI mode REMOVED (deprecated - unreliable, frontend not ready)
   * ✗ UserInputRequiredResponse disabled (frontend not ready)
   *
   * @param string|null $targetSite Target site domain or null
   * @return array Selected modes
   */
  private function selectPriceComparisonModes(?string $targetSite): array
  {
    // WEB/CHAT MODE: Implement target_site handling with multi-site support
    // CLI MODE DEPRECATED: User prompt removed (frontend not ready, CLI unreliable)
    
    // Case 1: No target_site → Mode B only
    if ($targetSite === null) {
      $selectedModes = ['mode_b_google_shopping'];
      
      $this->log("Mode selection: Price comparison without target_site → Mode B", [
        'intent_type' => 'price_comparison',
        'target_site' => null,
        'selected_modes' => $selectedModes
      ]);
      
      return $selectedModes;
    }
    
    // Domain SiteRouter takes precedence (e.g. Ecommerce → Amazon → hybrid Mode D + Mode B)
    $router = $this->findSiteRouter($targetSite);
    
    if ($router !== null) {
      $selectedModes = $router->getRecommendedModes('price_comparison');
      
      if (!empty($selectedModes)) {
        $this->log("Mode selection: Price comparison with domain-routed target_site", [
          'intent_type' => 'price_comparison',
          'target_site' => $targetSite,
          'router_id' => $router->getRouterId(),
          'selected_modes' => $selectedModes,
        ]);

        return $selectedModes;
      }
    }
    
    // Case 3: Find all available sites matching the target_site (no domain router matched)
    // This handles both exact matches and TLD variants (e.g. "fnac" → ["fnac.fr", "fnac.com"])
    $availableSites = $this->findAvailableSites($targetSite);
    
    if (empty($availableSites)) {
      // Case 4: target_site not in DB → Mode B only + User notification
      $selectedModes = ['mode_b_google_shopping'];
      
      // 🔧 FIX (2026-05-07): Notify user that requested site is not available
      $this->notifyUserSiteUnavailable($targetSite);
      
      // Log for admin to track requested but unavailable sites
      $this->log("Mode selection: Price comparison with target_site not in DB → Mode B with user notification", [
        'intent_type' => 'price_comparison',
        'target_site' => $targetSite,
        'site_in_db' => false,
        'selected_modes' => $selectedModes,
        'user_notified' => true
      ]);
      
      return $selectedModes;
    }
    
    if (count($availableSites) === 1) {
      // Case 5: One target_site in DB → Hybrid (Mode B + Mode C)
      $selectedModes = ['mode_b_google_shopping', 'mode_c_rag_websearch'];
      
      $this->log("Mode selection: Price comparison with one target_site in DB → Hybrid (Mode B + Mode C)", [
        'intent_type' => 'price_comparison',
        'target_site' => $targetSite,
        'available_sites' => $availableSites,
        'site_count' => 1,
        'site_in_db' => true,
        'selected_modes' => $selectedModes
      ]);
      
      return $selectedModes;
    }
    
    // Case 6: Multiple target_sites in DB → Hybrid (Mode B + Mode C for all sites)
    $selectedModes = ['mode_b_google_shopping', 'mode_c_rag_websearch'];
    
    $this->log("Mode selection: Price comparison with multiple target_sites in DB → Hybrid (Mode B + Mode C)", [
      'intent_type' => 'price_comparison',
      'target_site' => $targetSite,
      'available_sites' => $availableSites,
      'site_count' => count($availableSites),
      'site_in_db' => true,
      'selected_modes' => $selectedModes,
      'note' => 'Mode C will search all available sites: ' . implode(', ', $availableSites)
    ]);
    
    return $selectedModes;
  }

  /**
   * Resolve a target site (as extracted upstream by the Pure-LLM IntentRouter)
   * to its owning SiteRouter, if any domain has registered one.
   *
   * Domain-agnostic: Core itself has no knowledge of Amazon, LinkedIn,
   * Salesforce or any other commercial brand. The Ecommerce / HR / CRM apps
   * register the routers they own at boot time via WebSearchEngineRegistry.
   *
   * @param string|null $targetSite Target site detected by the LLM
   * @return SiteRouterInterface|null Matching router or null
   */
  private function findSiteRouter(?string $targetSite): ?SiteRouterInterface
  {
    return WebSearchEngineRegistry::getInstance()->findSiteRouter($targetSite);
  }

  /**
   * Select modes for market research intent
   *
   * Implements requirements 9.5, 9.6:
   * - No target_site → Mode A
   * - target_site in DB → Hybrid (Mode A + Mode C)
   * - target_site not in DB → Mode A only
   *
   * @param string|null $targetSite Target site domain or null
   * @return array Selected modes
   */
  private function selectMarketResearchModes(?string $targetSite): array
  {
    // No target site → Mode A (AI Overview)
    if ($targetSite === null) {
      $this->log("Mode selection: Market research without target_site → Mode A", [
        'intent_type' => 'market_research',
        'target_site' => null
      ]);

      return ['mode_a_ai_overview'];
    }

    // Domain SiteRouter takes precedence (e.g. Ecommerce → Amazon → hybrid Mode A + Mode D)
    $router = $this->findSiteRouter($targetSite);
    if ($router !== null) {
      $modes = $router->getRecommendedModes('market_research');
      
      if (!empty($modes)) {
        $this->log("Mode selection: Market research with domain-routed target_site", [
          'intent_type' => 'market_research',
          'target_site' => $targetSite,
          'router_id' => $router->getRouterId(),
          'selected_modes' => $modes,
        ]);

        return $modes;
      }
    }

    // Check if target_site exists in clic_rag_websearch table
    $siteExistsInDb = $this->checkTargetSiteExists($targetSite);

    if ($siteExistsInDb) {
      // target_site in DB → Hybrid (Mode A + Mode C)
      $this->log("Mode selection: Market research with target_site in DB → Hybrid (Mode A + Mode C)", [
        'intent_type' => 'market_research',
        'target_site' => $targetSite,
        'site_in_db' => true
      ]);

      return ['mode_a_ai_overview', 'mode_c_rag_websearch'];
    } else {
      // target_site not in DB → Mode A only (ignore target_site)
      $this->log("Mode selection: Market research with target_site not in DB → Mode A only", [
        'intent_type' => 'market_research',
        'target_site' => $targetSite,
        'site_in_db' => false
      ]);

      return ['mode_a_ai_overview'];
    }
  }

  /**
   * Resolve mode_hint to mode identifiers
   *
   * Converts mode_hint values to actual mode identifiers.
   *
   * @param string $modeHint Mode hint (mode_a|mode_b|mode_c|hybrid)
   * @return array Mode identifiers
   */
  private function resolveModeHint(string $modeHint): array
  {
    switch ($modeHint) {
      case 'mode_a':
        return ['mode_a_ai_overview'];
      
      case 'mode_b':
        return ['mode_b_google_shopping'];
      
      case 'mode_c':
        return ['mode_c_rag_websearch'];
      
      case 'hybrid':
        // Default hybrid: Mode A + Mode B
        return ['mode_a_ai_overview', 'mode_b_google_shopping'];
      
      default:
        // Unknown mode_hint → fallback to Mode A
        $this->log("Unknown mode_hint: {$modeHint}, falling back to Mode A", [
          'mode_hint' => $modeHint
        ]);
        return ['mode_a_ai_overview'];
    }
  }

  /**
   * Check if target site exists in clic_rag_websearch table
   *
   * Queries the database via Doctrine ORM to check if the target site
   * is configured for RAG websearch (Mode C).
   *
   * @param string $targetSite Target site domain (e.g., "amazon.fr")
   * @return bool True if site exists and is active (status = 1)
   */
  private function checkTargetSiteExists(string $targetSite): bool
  {
    try {
      // Initialize DoctrineOrm if not already done
      if (!class_exists('ClicShopping\\AI\\Infrastructure\\Orm\\DoctrineOrm')) {
        if ($this->debug) {
          error_log("ModeSelector::checkTargetSiteExists() - DoctrineOrm not available, assuming site doesn't exist");
        }
        return false;
      }

      $tableName = $this->prefixDb . 'rag_websearch';
      
      // Query via Doctrine ORM (agnostic layer requirement)
      $sql = "SELECT COUNT(*) as count 
              FROM {$tableName} 
              WHERE site_domain = :site_domain 
              AND status = 1";
      
      $result = DoctrineOrm::selectOne($sql, ['site_domain' => $targetSite]);
      
      $exists = ($result && $result['count'] > 0);

      if ($this->debug) {
        error_log("ModeSelector::checkTargetSiteExists() - Site: {$targetSite}, Exists: " . ($exists ? 'true' : 'false'));
      }

      return $exists;

    } catch (\Exception $e) {
      $this->log("Error checking target_site existence: " . $e->getMessage(), [
        'target_site' => $targetSite,
        'exception' => $e->getMessage()
      ]);

      // On error, assume site doesn't exist (safe fallback)
      return false;
    }
  }

  /**
   * Find all available sites matching the target site
   * 
   * 🆕 NEW (2026-05-07): Multi-site support
   * 
   * Handles both exact matches and domain variants:
   * - "amazon.fr" → ["amazon.fr"] (exact match)
   * - "amazon" → ["amazon.fr", "amazon.com", "amazon.co.uk"] (all amazon.* variants)
   * - "amazon.de" → [] (not in DB - specific TLD requested but not found)
   * 
   * @param string $targetSite Target site domain or partial domain
   * @return array Array of available site domains
   */
  private function findAvailableSites(string $targetSite): array
  {
    try {
      // Initialize DoctrineOrm if not already done
      if (!class_exists('ClicShopping\\AI\\Infrastructure\\Orm\\DoctrineOrm')) {
        if ($this->debug) {
          error_log("ModeSelector::findAvailableSites() - DoctrineOrm not available");
        }
        return [];
      }

      $tableName = $this->prefixDb . 'rag_websearch';
      
      // First, try exact match
      $sql = "SELECT site_domain 
              FROM {$tableName} 
              WHERE site_domain = :site_domain 
              AND status = 1";
      
      $exactMatch = DoctrineOrm::selectOne($sql, ['site_domain' => $targetSite]);
      
      if ($exactMatch) {
        // Exact match found
        if ($this->debug) {
          error_log("ModeSelector::findAvailableSites() - Exact match found: {$targetSite}");
        }
        return [$exactMatch['site_domain']];
      }
      
      // 🔧 FIX (2026-05-07): Check if target site has a TLD
      // If it has a TLD (e.g., "amazon.de"), don't search for variants
      // If it doesn't have a TLD (e.g., "amazon"), search for all variants
      $hasTLD = $this->hasTLD($targetSite);
      
      if ($hasTLD) {
        // Specific TLD requested but not found → return empty
        if ($this->debug) {
          error_log("ModeSelector::findAvailableSites() - Specific TLD requested ({$targetSite}) but not found in DB");
        }
        return [];
      }
      
      // No TLD specified, try to find variants (e.g., "amazon" → "amazon.fr", "amazon.com")
      $baseDomain = $this->extractBaseDomain($targetSite);
      
      $sql = "SELECT site_domain 
              FROM {$tableName} 
              WHERE site_domain LIKE :base_domain 
              AND status = 1
              ORDER BY site_domain ASC";
      
      $results = DoctrineOrm::select($sql, ['base_domain' => $baseDomain . '%']);
      
      $availableSites = [];
      if ($results && is_array($results)) {
        foreach ($results as $row) {
          $availableSites[] = $row['site_domain'];
        }
      }

      if ($this->debug) {
        error_log("ModeSelector::findAvailableSites() - Target: {$targetSite}, Base: {$baseDomain}, Found: " . count($availableSites) . " sites");
      }

      return $availableSites;

    } catch (\Exception $e) {
      $this->log("Error finding available sites: " . $e->getMessage(), [
        'target_site' => $targetSite,
        'exception' => $e->getMessage()
      ]);

      return [];
    }
  }

  /**
   * Check if target site has a TLD (Top-Level Domain)
   * 
   * Examples:
   * - "amazon.fr" → true (has TLD)
   * - "amazon.com" → true (has TLD)
   * - "amazon" → false (no TLD)
   * - "fnac.com" → true (has TLD)
   * - "fnac" → false (no TLD)
   * 
   * @param string $targetSite Target site domain
   * @return bool True if has TLD, false otherwise
   */
  private function hasTLD(string $targetSite): bool
  {
    // Check if the domain contains a dot followed by a known TLD
    $commonTLDs = [
      'com', 'fr', 'de', 'uk', 'es', 'it', 'nl', 'be', 'ch', 'ca', 'au', 'jp', 'cn', 'in', 'br', 'mx', 
      'ru', 'pl', 'se', 'no', 'dk', 'fi', 'at', 'ie', 'pt', 'gr', 'cz', 'hu', 'ro', 'bg', 'hr', 'sk', 
      'si', 'ee', 'lv', 'lt', 'lu', 'mt', 'cy', 'co.uk', 'co.jp', 'co.nz', 'co.za', 'com.au', 'com.br'
    ];
    
    foreach ($commonTLDs as $tld) {
      if (preg_match('/\.' . preg_quote($tld, '/') . '$/i', $targetSite)) {
        return true;
      }
    }
    
    return false;
  }

  /**
   * Extract base domain from target site
   * 
   * Examples:
   * - "amazon.fr" → "amazon"
   * - "amazon.com" → "amazon"
   * - "amazon" → "amazon"
   * - "fnac.com" → "fnac"
   * 
   * @param string $targetSite Target site domain
   * @return string Base domain without TLD
   */
  private function extractBaseDomain(string $targetSite): string
  {
    // Remove common TLDs
    $baseDomain = preg_replace('/\.(com|fr|de|uk|es|it|nl|be|ch|ca|au|jp|cn|in|br|mx|ru|pl|se|no|dk|fi|at|ie|pt|gr|cz|hu|ro|bg|hr|sk|si|ee|lv|lt|lu|mt|cy)$/i', '', $targetSite);
    
    return strtolower(trim($baseDomain));
  }

  /**
   * Notify user that requested site is not available
   * 
   * 🆕 NEW (2026-05-07): User notification for unavailable sites
   * 
   * Adds a warning message to the result that will be displayed to the user.
   * Message format: "Le site [amazon.de] n'est pas encore disponible dans la base de données compétiteur, 
   * veuillez compléter votre base de sites compétiteurs."
   * 
   * @param string $targetSite Requested site domain
   * @return void
   */
  private function notifyUserSiteUnavailable(string $targetSite): void
  {
    // Get list of available sites for user reference
    $availableSites = $this->getAvailableSitesList();

    // Build user-friendly message
    $sitesStr = implode(', ', $availableSites);
    $rawMessage = $this->language->getDef('text_notify_user_site_unavailable');

    if (!empty($rawMessage)) {
      $message = str_replace(['{{targetSite}}', '{{availableSites}}'], [$targetSite, $sitesStr], $rawMessage);
    } else {
      $message = "The site [{$targetSite}] is not yet available in the competitor database. Showing Google Shopping results as alternative. Currently available sites: {$sitesStr}.";
    }

    // Store notification in class property for later retrieval
    // This will be added to the final result by the caller
    if (!isset($this->userNotifications)) {
      $this->userNotifications = [];
    }
    
    $this->userNotifications[] = [
      'type' => 'warning',
      'message' => $message,
      'requested_site' => $targetSite,
      'available_sites' => $availableSites
    ];
    
    if ($this->debug) {
      error_log("ModeSelector::notifyUserSiteUnavailable() - Site: {$targetSite}, Message: {$message}");
    }
  }

  /**
   * Get list of all available sites in the database
   * 
   * @return array Array of available site domains
   */
  private function getAvailableSitesList(): array
  {
    try {
      $tableName = $this->prefixDb . 'rag_websearch';
      
      $sql = "SELECT site_domain 
              FROM {$tableName} 
              WHERE status = 1
              ORDER BY site_domain ASC";
      
      $results = DoctrineOrm::select($sql, []);
      
      $sites = [];
      if ($results && is_array($results)) {
        foreach ($results as $row) {
          $sites[] = $row['site_domain'];
        }
      }

      return !empty($sites) ? $sites : ['(aucun site configuré)'];

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("ModeSelector::getAvailableSitesList() - Error: " . $e->getMessage());
      }
      return ['(erreur de récupération)'];
    }
  }

  /**
   * Get user notifications
   * 
   * Returns any notifications that should be displayed to the user
   * (e.g., site unavailable warnings)
   * 
   * @return array Array of notification objects
   */
  public function getUserNotifications(): array
  {
    return $this->userNotifications ?? [];
  }

  /**
   * Map location to currency and region parameters
   *
   * Implements requirement 9.1.2: Location-to-currency mapping.
   * Returns SerpAPI parameters (gl, hl, currency) based on detected location.
   *
   * Uses LocationPatterns for pattern-based location detection (FALLBACK ONLY).
   * Primary location detection should use LLM via IntentRouter.
   *
   * @param string|null $location Detected location (e.g., "France", "Paris", "Fréjus")
   * @return array Location parameters with keys: gl, hl, currency, country_code
   */
  public function mapLocationToParams(?string $location): array
  {
    $defaultRegion = mb_strtoupper($this->language->getCode() ?? 'FR');
    $defaultParams = LocationPatterns::getLocationParams($defaultRegion);

    if ($location === null || trim($location) === '') {
      if ($this->debug) {
        error_log("ModeSelector::mapLocationToParams() - No location, using default: {$defaultRegion}");
      }
      return $defaultParams;
    }

    // PureLLM: location est déjà un code ISO retourné par IntentRouter (ex: "FR", "US")
    // getLocationParams gère le fallback si le code est inconnu
    $countryCode = mb_strtoupper(trim($location));
    $params = LocationPatterns::getLocationParams($countryCode, $defaultRegion);

    if ($this->debug) {
      error_log("ModeSelector::mapLocationToParams() - Country: {$countryCode}, Params: " . json_encode($params));
    }

    if ($this->debug) {
      $this->log("Location mapped to parameters", [
        'country_code' => $countryCode,
        'currency' => $params['currency'],
        'gl_parameter' => $params['gl'],
        'hl_parameter' => $params['hl']
      ]);
    }

    return $params;
  }

  /**
   * Get stopwords for a language
   *
   * Returns stopwords array for title normalization and deduplication.
   * Delegates to LocationPatterns.
   *
   * @param string $language Language code (en|fr)
   * @return array Stopwords array
   */
  public function getStopwords(string $language = 'en'): array
  {
    return LocationPatterns::getStopwords($language);
  }

  /**
   * Get location currency map
   *
   * Returns the complete location-to-currency mapping.
   * Useful for debugging and testing.
   * Delegates to LocationPatterns.
   *
   * @return array Location currency map
   */
  public function getLocationCurrencyMap(): array
  {
    return LocationPatterns::$locationCurrencyMap;
  }

  /**
   * Process user choice for mode selection
   *
   * This method is called when the user responds to a UserInputRequiredResponse.
   * It maps the user's choice to the appropriate modes and logs the selection.
   *
   * @param string $userChoice User's choice ('1', '2', '3', etc.)
   * @param array $context Context from UserInputRequiredResponse
   * @return array Selected modes
   */
  public function processUserChoice(string $userChoice, array $context = []): array
  {
    // Restore context
    $this->currentQuery = $context['query'] ?? '';
    
    // Map user choice to modes
    $selectedModes = $this->mapUserChoiceToModes($userChoice);
    
    // Log user choice for analytics
    $this->logUserChoice($userChoice, $selectedModes);
    
    $this->log("Mode selection: User choice processed", [
      'user_choice' => $userChoice,
      'selected_modes' => $selectedModes,
      'context' => $context
    ]);
    
    return $selectedModes;
  }
}

