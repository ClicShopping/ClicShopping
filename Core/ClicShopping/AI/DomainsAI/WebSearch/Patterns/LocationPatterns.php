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
     * Region served when neither the request nor the default is mapped.
     * Serving FR/EUR here announced euros to a shop nobody could place.
     */
    public const FALLBACK_REGION = 'US';

    /** Resolved region currencies, per request. */
    private static array $regionCurrency = [];

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
     * Returns SerpAPI parameters (gl, hl, currency) for a given country code. NOTHING here is a
     * list of countries: `gl` is the ISO-2 code itself (measured: `gb` serves what `uk` served) and
     * the currency comes from ICU, which knows every region — a shop in Belgium, Switzerland,
     * Portugal or Morocco is served like any other.
     *
     * `hl` is the language the ANSWER is dressed in, not the market: `gl` alone decides the offers
     * and their currency. It is the reader's language, never inferred from the country — nothing in
     * this platform states that Italy speaks Italian, and inventing it would be a list again.
     *
     * Neither the request nor the default being a real region is a LAST-RESORT fallback: it serves
     * {@see self::FALLBACK_REGION} and says so through `is_fallback`, because a region nobody
     * established must not be announced as if it had been.
     *
     * No country is written here as a default: an unresolved region falls to
     * {@see self::FALLBACK_REGION}, never to whichever country this file happened to name.
     *
     * @param string $countryCode ISO-2 country code
     * @param string $defaultRegion Region to serve when the code is not a real region, '' for none
     * @param string $language Interface language code the answer is served in
     * @return array Location parameters with keys: currency, gl, hl, country_code, is_fallback
     */
    public static function getLocationParams(string $countryCode, string $defaultRegion = '', string $language = 'en'): array
    {
      $countryCode   = mb_strtoupper(trim($countryCode));
      $defaultRegion = mb_strtoupper(trim($defaultRegion));

      // country_code must name the entry actually served, never the raw request:
      // an unreal region ("VAR", "EN") falls back but used to be echoed back as a country.
      $resolved = match (true) {
        self::isRegion($countryCode)   => $countryCode,
        self::isRegion($defaultRegion) => $defaultRegion,
        default                        => self::FALLBACK_REGION
      };

      $language = mb_strtolower(trim($language));

      return [
        'currency' => self::regionCurrency($resolved),
        'gl' => mb_strtolower($resolved),
        'hl' => preg_match('/^[a-z]{2}$/', $language) === 1 ? $language : 'en',
        'country_code' => $resolved,
        'is_fallback' => $resolved !== $countryCode && $resolved !== $defaultRegion
      ];
    }

    /**
     * Is this ISO-2 code a region ICU knows, rather than a language code read as a country?
     *
     * Without ext-intl nothing can refute a well-formed code, so the shape alone decides: an install
     * keeps searching its own market, and the currency simply stays unestablished.
     */
    private static function isRegion(string $countryCode): bool
    {
      if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
        return false;
      }

      if (!\extension_loaded('intl')) {
        return true;
      }

      // Two independent ICU signals: an unknown region echoes its own code back as a name.
      return self::regionCurrency($countryCode) !== ''
          && \Locale::getDisplayRegion('en_' . $countryCode, 'en') !== $countryCode;
    }

    /**
     * The currency a region trades in, from ICU — '' when it is not established.
     *
     * 'XXX' is the ISO code for "no currency" and 'XAD' what ICU serves for an unknown region:
     * both mean unestablished, and an unnamed unit must stay unnamed rather than be guessed.
     */
    private static function regionCurrency(string $countryCode): string
    {
      if (isset(self::$regionCurrency[$countryCode])) {
        return self::$regionCurrency[$countryCode];
      }

      if (!\extension_loaded('intl') || preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
        return self::$regionCurrency[$countryCode] = '';
      }

      try {
        $formatter = \NumberFormatter::create('en_' . $countryCode, \NumberFormatter::CURRENCY);
        $currency = $formatter === null ? '' : (string) $formatter->getTextAttribute(\NumberFormatter::CURRENCY_CODE);
      } catch (\Throwable) {
        $currency = '';
      }

      return self::$regionCurrency[$countryCode] = \in_array($currency, ['', 'XXX', 'XAD'], true) ? '' : $currency;
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
