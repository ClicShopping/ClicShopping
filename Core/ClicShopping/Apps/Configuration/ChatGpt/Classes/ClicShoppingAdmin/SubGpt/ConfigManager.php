<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Security\SecurityLogger;
use function defined;

/**
 * ConfigManager
 *
 * Manages GPT configuration and status checks.
 * Extracted from Gpt.php as part of code refactoring (Task 9).
 *
 * Responsibilities:
 * - Check GPT integration status
 * - Manage API key environment
 * - Generate AJAX URLs
 * - Manage SerpApi configuration
 */
class ConfigManager
{
  /**
   * Checks the status of the GPT integration by verifying application constants and API key configuration.
   *
   * @return bool Returns true if the GPT integration is enabled and properly configured, otherwise false.
   */
  public static function checkGptStatus(): bool
  {
    if (!defined('CLICSHOPPING_APP_CHATGPT_CH_STATUS') || CLICSHOPPING_APP_CHATGPT_CH_STATUS == 'False' || empty(CLICSHOPPING_APP_CHATGPT_CH_API_KEY)) {
      return false;
    }

    return true;
  }

  /**
   * Securely retrieves the OpenAI API key for use in API calls.
   * Instead of setting an environment variable with putenv(), which is insecure,
   * this method simply returns the API key from the application configuration.
   *
   * @return string|null The API key or null if not configured
   */
  public static function getEnvironment(): string|null
  {
    // Initialiser les constantes nécessaires
    static::initializeConstants();

    if (!defined('CLICSHOPPING_APP_CHATGPT_CH_API_KEY') || empty(CLICSHOPPING_APP_CHATGPT_CH_API_KEY)) {
      error_log("WARNING: ChatGpt API not defined or empty");
      return null;
    }

    $env = putenv('OPENAI_API_KEY=' . CLICSHOPPING_APP_CHATGPT_CH_API_KEY);

    return $env;
  }

  /**
   * Initialise les constantes nécessaires si elles n'existent pas
   */
  private static function initializeConstants(): void
  {
  }

  /**
   * Generates the AJAX URL for the requested script.
   *
   * @param bool $chatGpt Determines whether to return the URL for the chatGpt script (true)
   *                       or the chatGptSEO script (false).
   * @return string Returns the appropriate AJAX URL based on the parameter.
   */
  public static function getAjaxUrl(bool $chatGpt = true): string
  {
    if ($chatGpt === false) {
      $url = CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin') . 'ajax/ChatGpt/chatGptSEO.php';
    } else {
      $url = CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin') . 'ajax/ChatGpt/chatGpt.php';
    }

    return $url;
  }

  /**
   * Generates the URL for the AJAX SEO multilanguage functionality.
   *
   * @return string The fully constructed URL for the AJAX SEO multilanguage script.
   */
  public static function getAjaxSeoMultilanguageUrl(): string
  {
    $url = CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin') . 'ajax/ChatGpt/chatGptMultiLanguage.php';

    return $url;
  }

  /**
   * Retrieves the SerpApi key from configuration
   *
   * @return string La clé API SerpApi ou chaîne vide si non configurée
   */
  public static function getSerpApiKey(): string
  {
    if (!defined('CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI') || empty(CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI)) {
      self::logSerpApiWarning('SerpApi key not configured (CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI is undefined or empty)');
      return '';
    }

    $key = trim(CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI);

    if (!self::isValidSerpApiKey($key)) {
      self::logSerpApiWarning('SerpApi key invalid format', [
        'key_masked' => self::maskKey($key)
      ]);
      return '';
    }

    return $key;
  }

  /**
   * Checks if SerpApi is available and configured
   *
   * @return bool True si une clé SerpApi valide est disponible
   */
  public static function isSerpApiAvailable(): bool
  {
    return !empty(self::getSerpApiKey());
  }

  /**
   * Returns a masked SerpApi key for safe logging/display.
   *
   * @return string Masked key or empty string if not configured
   */
  public static function getSerpApiKeyMasked(): string
  {
    if (!defined('CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI') || empty(CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI)) {
      return '';
    }

    return self::maskKey(trim(CLICSHOPPING_APP_CHATGPT_CH_API_KEY_SERPAPI));
  }

  /**
   * Validate SerpApi key format.
   *
   * @param string $key
   * @return bool
   */
  private static function isValidSerpApiKey(string $key): bool
  {
    $len = strlen($key);
    if ($len < 20 || $len > 128) {
      return false;
    }

    return (bool)preg_match('/^[A-Za-z0-9_-]+$/', $key);
  }

  /**
   * Mask a key for safe logging.
   *
   * @param string $key
   * @return string
   */
  private static function maskKey(string $key): string
  {
    $len = strlen($key);
    if ($len <= 8) {
      return str_repeat('*', $len);
    }

    return substr($key, 0, 4) . str_repeat('*', $len - 8) . substr($key, -4);
  }

  /**
   * Log SerpApi warnings with security logger.
   *
   * @param string $message
   * @param array $context
   */
  private static function logSerpApiWarning(string $message, array $context = []): void
  {
    try {
      $logger = new SecurityLogger();
      $logger->logSecurityEvent($message, 'warning', $context);
    } catch (\Throwable $e) {
      error_log('SerpApi key warning: ' . $message);
    }
  }
}
