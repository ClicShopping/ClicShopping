<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Semantic\Processor;


use ClicShopping\OM\Registry;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\AI\Infrastructure\Cache\TranslationCache;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;

/**
 * TranslationHandler
 * 
 * - Removed: protectTechnicalTerms, restoreTechnicalTerms (no longer needed)
 * - Removed: getLanguageName (use Registry::get('Language') instead)
 * - Removed: translateFromEnglish (not needed for core functionality)
 * - Removed: isEnglish (simplified detection)
 * 
 * Handles query translation to English with caching and retry logic.
 * Provides clean translation extraction and prompt validation.
 */

class TranslationHandler
{
  private static ?SecurityLogger $logger = null;
  
  /**
   * Initialize components
   */
  private static function init(): void
  {
    if (self::$logger === null) {
      self::$logger = new SecurityLogger();
    }
  }
  
  /**
   * Translates text to English with caching
   *
   * Integrates CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER (True/False)
   * 
   * - Returns original query if translation fails
   * - Logs translation failures for monitoring
   * - Prevents empty string returns
   * 
   * @param string $text Text to translate
   * @param int $cacheTTL Cache TTL in seconds (default: 3600)
   * @param string|null $originalQuery Original query for fallback (optional)
   * @return string Translated text or original query if translation fails
   */
  public static function translateToEnglish(string $prompt, int $cacheTTL = 3600, ?string $originalQuery = null): string
  {
    self::init();

    // Extract original query from prompt if not provided
    // The prompt format is: "[translation instructions] [original query]"
    if ($originalQuery === null) {
      // Try to extract the query from the prompt
      // Look for common patterns like "Translate the following query to English..."
      if (preg_match('/(?:Translate|Translation).*?:\s*(.+)$/is', $prompt, $matches)) {
        $originalQuery = trim($matches[1]);
      } else {
        // If we can't extract it, use the last 200 characters as fallback
        $originalQuery = substr($prompt, -200);
      }
    }

    // Get language ID from Registry
    $languageId = 1; // Default to English
    if (Registry::exists('Language')) {
      $languageId = Registry::get('Language')->getId();
    }

    $useCache = defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER')  && CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER === 'True';

    if ($useCache) {
      // Initialize TranslationCache in Registry if not exists
      if (!Registry::exists('TranslationCache')) {
        Registry::set('TranslationCache', new TranslationCache());
      }

      $translationCache = Registry::get('TranslationCache');

      // Check cache first
      $cached = $translationCache->getCachedTranslation($prompt, $languageId);

      if ($cached !== null) {
        if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
          error_log("Translation cache HIT for: " . substr($prompt, 0, 50));
        }
        return $cached;
      }
    }

    try {
      // 🔍 DIAGNOSTIC: Log avant appel GPT
      error_log("=== TRANSLATION START ===");
      error_log("Prompt length: " . strlen($prompt));
      error_log("Prompt preview: " . substr($prompt, 0, 100));
      error_log("Calling Gpt::getGptResponse()...");
      
      $translation = Gpt::getGptResponse($prompt, 80);

      // 🔍 DIAGNOSTIC: Log résultat GPT
      if ($translation === false) {
        error_log("[error] CRITICAL: Gpt::getGptResponse() returned FALSE");
        error_log("   This means GPT is disabled or API call failed");
        error_log("   Check: CLICSHOPPING_APP_CHATGPT_CH_STATUS and API_KEY");
        throw new \Exception("GPT returned FALSE - check configuration");
      }
      
      if (empty($translation)) {
        error_log("[error] CRITICAL: Gpt::getGptResponse() returned EMPTY STRING");
        error_log("   This means API returned no content");
        throw new \Exception("GPT returned empty string");
      }
      
      error_log("[INFO] Gpt::getGptResponse() returned: " . substr($translation, 0, 100));

      // Clean the translation
      error_log("Calling extractCleanTranslation()...");
      $cleanTranslation = self::extractCleanTranslation($translation);

      // 🔍 DIAGNOSTIC: Log après nettoyage
      if (empty($cleanTranslation)) {
        error_log("[error] CRITICAL: extractCleanTranslation() returned EMPTY");
        error_log("   Original translation was: " . substr($translation, 0, 200));
        error_log("   This means the cleaning removed everything");
        throw new \Exception("Clean translation is empty");
      }
      
      error_log("[INFO] Clean translation: " . substr($cleanTranslation, 0, 100));

      if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
        error_log("Translation result: '{$prompt}' → '{$cleanTranslation}'");
      }

      // Cache the result if caching is enabled and translation is not empty
      if ($useCache && !empty($cleanTranslation)) {
        $translationCache->cacheTranslation($prompt, $cleanTranslation, $languageId);

        if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
          error_log("Translation cached for: " . substr($prompt, 0, 50));
        }
      }

      error_log("=== TRANSLATION END (SUCCESS) ===");

      return $cleanTranslation;
    } catch (\Exception $e) {
      error_log("[error] TRANSLATION EXCEPTION: " . $e->getMessage());
      error_log("   Stack trace: " . $e->getTraceAsString());
      error_log("   FALLBACK: Using original query instead");
      error_log("   Original query: " . substr($originalQuery, 0, 200));
      error_log("=== TRANSLATION END (FAILED - USING FALLBACK) ===");
      
      if (self::$logger) {
        self::$logger->logSecurityEvent(
          "Translation failed, using original query as fallback: " . $e->getMessage() . " | Original: " . substr($originalQuery, 0, 100),
          'warning'
        );
      }

      // This allows the system to continue processing with the original query
      return $originalQuery;
    }
  }
  
  /**
   * Extracts clean translation from GPT response
   * 
   * Removes common GPT prefixes, formatting artifacts, and <think> tags
   * 
   * ENHANCED: Now handles <think>...</think> reasoning blocks from models like qwen
   * 
   * @param string $gptResponse Raw GPT response
   * @return string Clean translation
   */
  public static function extractCleanTranslation(string $gptResponse): string
  {
    $cleaned = trim($gptResponse);
    
    // STEP 1: Remove <think>...</think> blocks (for models like qwen that show reasoning)
    // This allows the model to "think" but we extract only the final answer
    if (preg_match('/<think>.*?<\/think>\s*(.*)/is', $cleaned, $matches)) {
      $cleaned = trim($matches[1]);
      error_log("🧹 Removed <think> block, extracted: " . substr($cleaned, 0, 100));
    }
    
    // STEP 2: Remove common GPT prefixes
    $prefixes = [
      'Translation:',
      'Translated:',
      'English:',
      'Result:',
      'Answer:',
      'Response:',
      'Here is the translation:',
      'The translation is:',
    ];
    
    foreach ($prefixes as $prefix) {
      if (stripos($cleaned, $prefix) === 0) {
        $cleaned = trim(substr($cleaned, strlen($prefix)));
      }
    }
    
    // STEP 3: Extract text between quotes if present after "is:"
    if (preg_match('/is:\s*["\'](.+?)["\']$/i', $cleaned, $matches)) {
      $cleaned = $matches[1];
    }
    
    // STEP 4: Remove leading/trailing quotes if they wrap the entire string
    if ((str_starts_with($cleaned, '"') && str_ends_with($cleaned, '"')) ||
        (str_starts_with($cleaned, "'") && str_ends_with($cleaned, "'"))) {
      $cleaned = substr($cleaned, 1, -1);
    }
    
    return trim($cleaned);
  }

  /**
   * Simplifies prompt for retry attempts
   * 
   * @param string $originalPrompt Original prompt
   * @param int $level Simplification level (1-3)
   * @param string $text Original text
   * @return string Simplified prompt
   */
  public static function simplifyPrompt(string $originalPrompt, int $level, string $text): string
  {
    switch ($level) {
      case 1:
        // Level 1: Simplified classification
        return "Classify this query as 'analytics' or 'semantic': " . $text;
        
      case 2:
        // Level 2: Very simple question
        return "Is this about data/analytics or general information? " . $text;
        
      case 3:
        // Level 3: Minimal prompt
        return "Analytics or semantic? " . $text;
        
      default:
        return $originalPrompt;
    }
  }


  //**********************
  //not used
  //**********************


  /**
   * Validates prompt with retry mechanism
   *
   * @param string $prompt Prompt to validate
   * @param string $originalText Original text for fallback
   * @param int $maxRetries Maximum number of retries
   * @return string Validated response or empty string
   */
  public static function validatePromptWithRetry(string $prompt, string $originalText, int $maxRetries = 3): string
  {
    self::init();

    // Validate prompt is not empty and has minimum length
    if (empty($prompt) || strlen($prompt) < 10) {
      if (self::$logger) {
        self::$logger->logSecurityEvent(
          "Invalid prompt: too short or empty",
          'warning'
        );
      }
      return '';
    }

    $attempt = 0;
    $lastError = null;

    while ($attempt < $maxRetries) {
      try {

        $response = Gpt::getGptResponse($prompt, 100);

        // Validate response
        if (!empty($response) && strlen($response) > 5) {
          if (self::$logger) {
            self::$logger->logSecurityEvent(
              "Prompt validated successfully on attempt " . ($attempt + 1),
              'info'
            );
          }
          return $response;
        }

        // Response too short, try with simplified prompt
        $attempt++;
        if ($attempt < $maxRetries) {
          $prompt = self::simplifyPrompt($prompt, $attempt, $originalText);
        }

      } catch (\Exception $e) {
        $lastError = $e->getMessage();
        $attempt++;

        if (self::$logger) {
          self::$logger->logSecurityEvent(
            "Prompt validation attempt $attempt failed: " . $e->getMessage(),
            'warning'
          );
        }

        if ($attempt < $maxRetries) {
          $prompt = self::simplifyPrompt($prompt, $attempt, $originalText);
        }
      }
    }

    // All retries failed
    if (self::$logger) {
      self::$logger->logSecurityEvent(
        "All prompt validation attempts failed. Last error: " . ($lastError ?? 'unknown'),
        'error'
      );
    }

    return ''; // Return empty to trigger fallback
  }
}
