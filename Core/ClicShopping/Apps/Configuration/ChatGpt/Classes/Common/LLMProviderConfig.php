<?php
/**
 * LLM Provider Configuration Manager
 *
 * Manages configuration for LLM providers.
 * Loads configuration from database with fallback to PHP constants.
 * Implements caching for performance.
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Classes\Common
 * @since 4.11
 */

declare(strict_types=1);

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\Common;

use ClicShopping\OM\Registry;

/**
 * Class LLMProviderConfig
 *
 * Singleton configuration manager for LLM providers.
 * Provides methods to get, set, and manage configuration values.
 *
 * Configuration priority:
 * 1. In-memory cache
 * 2. Database (configuration_chatgpt table)
 * 3. PHP constants (CLICSHOPPING_APP_CHATGPT_*)
 * 4. Default values
 *
 * Usage:
 * <code>
 * $config = LLMProviderConfig::getInstance();
 * $apiKey = $config->get('openai_api_key');
 * $config->set('openai_model', 'gpt-4-turbo');
 * </code>
 */
class LLMProviderConfig
{
  /**
   * @var LLMProviderConfig|null Singleton instance
   */
  private static ?LLMProviderConfig $instance = null;

  /**
   * @var array<string, mixed> Configuration cache
   */
  private array $cache = [];

  /**
   * @var bool Whether cache has been loaded from database
   */
  private bool $cacheLoaded = false;

  /**
   * Private constructor for singleton pattern
   */
  private function __construct()
  {
    // Private constructor for singleton
  }

  /**
   * Get singleton instance
   *
   * Returns the single instance of the configuration manager.
   * Creates the instance if it doesn't exist yet.
   *
   * @return LLMProviderConfig Singleton instance
   */
  public static function getInstance(): LLMProviderConfig
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  /**
   * Set configuration value
   *
   * Sets a configuration value in memory and optionally persists to database.
   *
   * @param string $key Configuration key
   * @param mixed $value Configuration value
   * @return void
   */
  public function set(string $key, $value): void
  {
    $this->cache[$key] = $value;

    // Optionally persist to database
    $this->saveToDatabase($key, $value);
  }

  /**
   * Save configuration to database
   *
   * Persists a configuration value to the database.
   * Silently fails if database is unavailable.
   *
   * @param string $key Configuration key
   * @param mixed $value Configuration value
   * @return void
   */
  private function saveToDatabase(string $key, $value): void
  {
    try {
      $db = Registry::get('Db');

      // Convert cache key to database key
      // openai_api_key -> CLICSHOPPING_APP_CHATGPT_OPENAI_API_KEY
      $configKey = 'CLICSHOPPING_APP_CHATGPT_' . strtoupper($key);

      $query = $db->prepare('
        UPDATE :table_configuration_chatgpt
        SET configuration_value = :value
        WHERE configuration_key = :key
      ');

      $query->bindValue(':value', $value);
      $query->bindValue(':key', $configKey);
      $query->execute();
    } catch (\Exception $e) {
      // Ignore database errors
      // Configuration will remain in memory cache only
    }
  }

  /**
   * Get configuration value
   *
   * Retrieves a configuration value with the following priority:
   * 1. In-memory cache
   * 2. Database
   * 3. PHP constants
   * 4. Default value
   *
   * @param string $key Configuration key (e.g., 'openai_api_key')
   * @param mixed $default Default value if key not found
   * @return mixed Configuration value or default
   */
  public function get(string $key, $default = null)
  {
    // Load cache if not already loaded
    if (!$this->cacheLoaded) {
      $this->loadCache();
    }

    // Check cache first
    if (isset($this->cache[$key])) {
      return $this->cache[$key];
    }

    // Fallback to PHP constants
    $constantName = 'CLICSHOPPING_APP_CHATGPT_' . strtoupper($key);
    if (defined($constantName)) {
      $value = constant($constantName);
      // Cache the constant value
      $this->cache[$key] = $value;
      return $value;
    }

    return $default;
  }

  /**
   * Load configuration from database
   *
   * Loads all ChatGPT configuration from database into cache.
   * Silently fails if database is unavailable.
   *
   * @return void
   */
  private function loadCache(): void
  {
    try {
      $db = Registry::get('Db');

      $query = $db->prepare('
        SELECT configuration_key, configuration_value
        FROM :table_configuration
        WHERE configuration_key LIKE :prefix
      ');

      $query->bindValue(':prefix', 'CLICSHOPPING_APP_CHATGPT_%');
      $query->execute();

      while ($row = $query->fetch()) {
        // Convert database key to cache key
        // CLICSHOPPING_APP_CHATGPT_OPENAI_API_KEY -> openai_api_key
        $key = str_replace('CLICSHOPPING_APP_CHATGPT_', '', $row['configuration_key']);
        $key = strtolower($key);
        $this->cache[$key] = $row['configuration_value'];
      }

      $this->cacheLoaded = true;
    } catch (\Exception $e) {
      // Database not available, use constants only
      // This is expected during installation or when database is down
      $this->cacheLoaded = true;
    }
  }

  /**
   * Check if configuration key exists
   *
   * Checks if a configuration key exists in cache, database, or constants.
   *
   * @param string $key Configuration key
   * @return bool True if key exists, false otherwise
   */
  public function has(string $key): bool
  {
    // Load cache if not already loaded
    if (!$this->cacheLoaded) {
      $this->loadCache();
    }

    // Check cache
    if (isset($this->cache[$key])) {
      return true;
    }

    // Check PHP constants
    $constantName = 'CLICSHOPPING_APP_CHATGPT_' . strtoupper($key);
    return defined($constantName);
  }

  /**
   * Get all cached configuration
   *
   * Returns all configuration values currently in cache.
   * Useful for debugging or exporting configuration.
   *
   * @return array<string, mixed> All cached configuration
   */
  public function getAll(): array
  {
    if (!$this->cacheLoaded) {
      $this->loadCache();
    }

    return $this->cache;
  }

  /**
   * Reload configuration from database
   *
   * Forces a reload of configuration from database.
   * Clears cache and reloads fresh data.
   *
   * @return void
   */
  public function reload(): void
  {
    $this->clearCache();
    $this->loadCache();
  }

  /**
   * Clear cache
   *
   * Clears the in-memory configuration cache.
   * Next get() call will reload from database.
   *
   * @return void
   */
  public function clearCache(): void
  {
    $this->cache = [];
    $this->cacheLoaded = false;
  }

  /**
   * Prevent unserialization of singleton
   */
  public function __wakeup()
  {
    throw new \Exception("Cannot unserialize singleton");
  }

  /**
   * Prevent cloning of singleton
   */
  private function __clone()
  {
    // Prevent cloning
  }
}
