<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Services;

use ClicShopping\OM\Registry;
use ClicShopping\OM\Cache;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Services\LLMServiceWrapper;
use ClicShopping\Sites\Common\HTMLOverrideCommon;

/**
 * TranslationServiceWrapper
 *
 * Wrapper around SemanticAgent translation for SEO-specific needs.
 * Provides caching, batch translation, and language detection via OM/Language.
 *
 * Key Features:
 * - Translation caching (7-day TTL)
 * - Batch translation support
 * - Language detection via OM/Language::getLanguageCodeById()
 * - Fallback to English on translation failure
 * - Pure LLM mode (no pattern matching)
 *
 * Architecture:
 * - Uses SemanticAgent::translateToEnglish() for EN translation
 * - Uses SemanticAgent for translation from EN to other languages
 * - Caches all translations to reduce API costs
 * - Integrates with OM/Language for dynamic language support
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Services
 * @since 2026-03-02
 */
class TranslationServiceWrapper
{
  private const CACHE_TTL = 604800; // 7 days in seconds
  private const CACHE_PREFIX = 'seo_translation_';
  
  private bool $debug;
  private LLMServiceWrapper $llm;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->debug = $debug;
    $this->llm = new LLMServiceWrapper($debug);
  }

  /**
   * Translate multiple texts in batch
   *
   * @param array $texts Array of texts to translate
   * @param string $fromLang Source language code
   * @param string $toLang Target language code
   * @return array Array of translated texts (same order as input)
   */
  public function translateBatch(array $texts, string $fromLang, string $toLang): array
  {
    $translated = [];

    foreach ($texts as $text) {
      $translated[] = $this->translate($text, $fromLang, $toLang);
    }

    return $translated;
  }

  /**
   * Translate text from one language to another
   *
   * @param string $text Text to translate
   * @param string $fromLang Source language code (e.g., 'fr', 'en')
   * @param string $toLang Target language code (e.g., 'en', 'fr')
   * @return string Translated text
   */
  public function translate(string $text, string $fromLang, string $toLang): string
  {
    // If source and target are the same, return original
    if ($fromLang === $toLang) {
      return $text;
    }

    // Check cache first
    $cacheKey = $this->getCacheKey($text, $fromLang, $toLang);
    $cached = $this->getFromCache($cacheKey);

    if ($cached !== null) {
      if ($this->debug) {
        error_log("[TranslationServiceWrapper] Cache HIT: {$fromLang} -> {$toLang}");
      }
      return $cached;
    }

    if ($this->debug) {
      error_log("[TranslationServiceWrapper] Cache MISS: {$fromLang} -> {$toLang}");
      error_log("[TranslationServiceWrapper] Translating: " . substr($text, 0, 100) . "...");
    }

    try {
      // Translate to English first if not already English
      if ($fromLang !== 'en') {
        $text = $this->translateToEnglish($text);
      }

      // If target is English, we're done
      if ($toLang === 'en') {
        $translated = $text;
      } else {
        // Translate from English to target language
        $translated = $this->translateFromEnglish($text, $toLang);
      }

      // Clean translation
      $translated = $this->cleanTranslation($translated);

      // Cache the result
      $this->saveToCache($cacheKey, $translated);

      if ($this->debug) {
        error_log("[TranslationServiceWrapper] Translation SUCCESS");
      }

      return $translated;

    } catch (\Exception $e) {
      // Log error and fallback to original text
      error_log("[TranslationServiceWrapper] Translation FAILED: " . $e->getMessage());
      error_log("[TranslationServiceWrapper] Falling back to original text");

      return $text; // Fallback to original
    }
  }

  /**
   * Generate cache key for translation
   *
   * @param string $text Text to translate
   * @param string $fromLang Source language
   * @param string $toLang Target language
   * @return string Cache key
   */
  private function getCacheKey(string $text, string $fromLang, string $toLang): string
  {
    $hash = md5($text);
    return self::CACHE_PREFIX . "{$fromLang}_{$toLang}_{$hash}";
  }

  /**
   * Get translation from cache
   *
   * @param string $key Cache key
   * @return string|null Cached translation or null if not found
   */
  private function getFromCache(string $key): ?string
  {
    $cache = new Cache($key, 'SEO');

    $expireMinutes = (int)ceil(self::CACHE_TTL / 60);
    if ($cache->exists((string)$expireMinutes)) {
      return $cache->get();
    }

    return null;
  }

  /**
   * Translate text to English
   *
   * @param string $text Text to translate
   * @return string Translated text in English
   */
  private function translateToEnglish(string $text): string
  {
    // Use SemanticAgent for translation to English
    $translated = SemanticAgent::translateToEnglish($text, 80);

    // If translation is empty, return original
    if (empty(trim($translated))) {
      error_log("[TranslationServiceWrapper] Translation to English returned empty, using original");
      return $text;
    }

    return $translated;
  }

  /**
   * Translate text from English to target language
   *
   * @param string $text Text in English
   * @param string $toLang Target language code
   * @return string Translated text
   */
  private function translateFromEnglish(string $text, string $toLang): string
  {
    // Get language name for prompt
    $languageName = $this->getLanguageName($toLang);

    // Build translation prompt
    $prompt = "Translate the following text from English to {$languageName}. " .
              "Return ONLY the translated text, without any explanations or prefixes.\n\n" .
              "Text to translate:\n{$text}";

    // Use LLMServiceWrapper for translation (selected model)
    $translated = $this->llm->generateResponse($prompt, [
      'maxTokens' => 120,
      'temperature' => 0.3,
    ]);

    // If translation is empty, return original
    if (empty(trim($translated))) {
      error_log("[TranslationServiceWrapper] Translation from English to {$toLang} returned empty, using original");
      return $text;
    }

    return $translated;
  }

  /**
   * Get language name from language code using OM/Language
   *
   * @param string $code Language code (e.g., 'fr', 'es')
   * @return string Language name (e.g., 'French', 'Spanish')
   */
  public function getLanguageName(string $code): string
  {
    try {
      $language = Registry::get('Language');

      // Get language name from OM/Language
      // The get() method returns language data by code
      $languageName = $language->get('name', $code);

      return $languageName ?? 'English';

    } catch (\Exception $e) {
      error_log("[TranslationServiceWrapper] Failed to get language name for code '{$code}': " . $e->getMessage());
      return 'English'; // Fallback
    }
  }

  /**
   * Clean translation by removing GPT prefixes and extra whitespace
   *
   * @param string $text Raw translation
   * @return string Clean translation
   */
  private function cleanTranslation(string $text): string
  {
    // Remove common GPT prefixes
    $patterns = [
      '/^(Translation:|Translated:|English:|Result:|Answer:|Response:)\s*/i',
      '/^(The translation is|This translates to|In English):\s*/i',
      '/^(Here is the translation|The English version is):\s*/i',
    ];

    $clean = $text;
    foreach ($patterns as $pattern) {
      $clean = preg_replace($pattern, '', $clean);
    }

    // Trim whitespace
    $clean = trim($clean);

    // Clean HTML
    $clean = HTMLOverrideCommon::cleanHtmlForEmbedding($clean);

    return $clean;
  }

  /**
   * Save translation to cache
   *
   * @param string $key Cache key
   * @param string $value Translation to cache
   * @return void
   */
  private function saveToCache(string $key, string $value): void
  {
    $cache = new Cache($key, 'SEO');
    $cache->save($value, ['ttl_seconds' => self::CACHE_TTL]);
  }

  /**
   * Get language code from language_id using OM/Language
   *
   * @param int $languageId Language ID from database
   * @return string Language code (e.g., 'fr', 'en', 'es')
   */
  public function getLanguageCode(int $languageId): string
  {
    try {
      $language = Registry::get('Language');
      return $language->getLanguageCodeById($languageId);
    } catch (\Exception $e) {
      error_log("[TranslationServiceWrapper] Failed to get language code for ID {$languageId}: " . $e->getMessage());
      return 'en'; // Fallback to English
    }
  }
}
