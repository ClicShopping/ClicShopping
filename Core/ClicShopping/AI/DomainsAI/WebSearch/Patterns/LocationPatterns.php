<?php
  /**
   * LocationPatterns.php
   *
   * DOMAIN-AGNOSTIC location detection patterns for geographic location extraction.
   * Provides country and city patterns for location-to-currency mapping.
   *
   * MULTI-DOMAIN ARCHITECTURE:
   * - This class is DOMAIN-AGNOSTIC and works across all domains
   * - Located in Core/ClicShopping/AI/DomainsAI/WebSearch/Patterns/ (agnostic layer)
   * - No domain-specific logic or dependencies
   * - Reusable across different business contexts
   *
   * @package ClicShopping\AI\DomainsAI\WebSearch\Patterns
   * @since 2026-05-05
   *
   * @deprecated Pattern-based logic superseded by Pure LLM Mode
   *             This is a FALLBACK ONLY mechanism for when LLM fails or is unavailable
   *             Primary location detection MUST use LLM via IntentRouter
   *             Scheduled for removal in Q3 2026
   *
   * Requirements: 9.1.1, 9.1.2
   */

  namespace ClicShopping\AI\DomainsAI\WebSearch\Patterns;

  /**
   * LocationPatterns Class
   *
   * Provides geographic location patterns for country and city detection.
   * Used for location-to-currency mapping in websearch queries.
   *
   * NOTE: City/country names are international proper nouns, not language keywords.
   * Internal processing logic uses English keywords only (per AGENTS.md).
   *
   * @package ClicShopping\AI\DomainsAI\WebSearch\Patterns
   */
  class LocationPatterns
  {
    /**
     * Country name patterns (English + native names for international support)
     *
     * Maps country codes to regex patterns matching country names.
     * Supports both English and native language names.
     *
     * @var array<string, string>
     */
    public static array $countryPatterns = [
      'FR' => '/\b(france|french)\b/i',
      'US' => '/\b(usa|united states|us|america|american)\b/i',
      'GB' => '/\b(uk|united kingdom|britain|british|england|english)\b/i',
      'JP' => '/\b(japan|japanese)\b/i',
      'DE' => '/\b(germany|german|deutschland)\b/i',
      'ES' => '/\b(spain|spanish|españa)\b/i',
      'IT' => '/\b(italy|italian|italia)\b/i',
    ];

    /**
     * Major city patterns (international proper nouns)
     *
     * Maps country codes to regex patterns matching major city names.
     * These are geographic names, not language keywords for internal processing.
     *
     * @var array<string, string>
     */
    public static array $cityPatterns = [
      'FR' => '/\b(paris|lyon|marseille|toulouse|nice|nantes|strasbourg|montpellier|bordeaux|lille|rennes|fréjus)\b/i',
      'US' => '/\b(new york|los angeles|chicago|houston|phoenix|philadelphia|san antonio|san diego|dallas|san jose)\b/i',
      'GB' => '/\b(london|manchester|birmingham|leeds|glasgow|liverpool|newcastle|sheffield)\b/i',
      'JP' => '/\b(tokyo|osaka|kyoto|yokohama|nagoya|sapporo|fukuoka|kobe)\b/i',
      'DE' => '/\b(berlin|hamburg|munich|münchen|cologne|köln|frankfurt|stuttgart|düsseldorf)\b/i',
      'ES' => '/\b(madrid|barcelona|valencia|seville|sevilla|zaragoza|málaga|murcia|bilbao)\b/i',
      'IT' => '/\b(rome|roma|milan|milano|naples|napoli|turin|torino|palermo|venice|venezia|florence|firenze)\b/i',
    ];

    /**
     * Location-to-currency mapping
     *
     * Maps country codes to currency, geolocation (gl), and language (hl) parameters
     * for SerpAPI calls. This ensures location-aware searches with proper currency
     * and language settings.
     *
     * TODO v2: Externalize to database for better maintainability and support for
     * additional markets (Belgium, Switzerland, Morocco, etc.)
     *
     * @var array<string, array{currency: string, gl: string, hl: string}>
     */
    public static array $locationCurrencyMap = [
      'FR' => ['currency' => 'EUR', 'gl' => 'fr', 'hl' => 'fr'],
      'US' => ['currency' => 'USD', 'gl' => 'us', 'hl' => 'en'],
      'GB' => ['currency' => 'GBP', 'gl' => 'uk', 'hl' => 'en'],
      'JP' => ['currency' => 'JPY', 'gl' => 'jp', 'hl' => 'ja'],
      'DE' => ['currency' => 'EUR', 'gl' => 'de', 'hl' => 'de'],
      'ES' => ['currency' => 'EUR', 'gl' => 'es', 'hl' => 'es'],
      'IT' => ['currency' => 'EUR', 'gl' => 'it', 'hl' => 'it'],
    ];

    /**
     * Stopwords configuration for title normalization
     *
     * Used for deduplication and fuzzy matching of product titles.
     * Stopwords are removed before comparing titles to improve matching accuracy.
     *
     * TODO v2: Externalize to database for maintainability and support for
     * additional languages and domain-specific stopwords.
     *
     * @var array<string, array<string>>
     */
    public static array $stopwords = [
      'en' => ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'new', 'official', 'original']
    ];

    /**
     * Get location parameters for a country code
     *
     * Returns SerpAPI parameters (gl, hl, currency) for a given country code.
     *
     * @param string $countryCode Country code (e.g., "FR")
     * @param string $defaultRegion Default region if country code not found
     * @return array Location parameters with keys: currency, gl, hl, country_code
     */
    public static function getLocationParams(string $countryCode, string $defaultRegion = 'FR'): array
    {
      $countryCode   = mb_strtoupper(trim($countryCode));
      $defaultRegion = mb_strtoupper(trim($defaultRegion));
      $params = self::$locationCurrencyMap[$countryCode] ?? self::$locationCurrencyMap[$defaultRegion] ?? self::$locationCurrencyMap['FR'];
      $params['country_code'] = $countryCode;
      return $params;
    }

    /**
     * Get stopwords for a language
     *
     * Returns stopwords array for title normalization and deduplication.
     *
     * @param string $language Language code (en|fr)
     * @return array Stopwords array
     */
    public static function getStopwords(string $language = 'en'): array
    {
      $language = mb_strtolower($language);
      return self::$stopwords[$language] ?? self::$stopwords['en'];
    }

  }
