<?php
/**
 * LLM Provider Registry
 *
 * Centralized registry for managing LLM provider instances.
 * Implements singleton pattern to ensure single registry instance.
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Classes\Common
 * @since 4.11
 */

declare(strict_types=1);

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\Common;

/**
 * Class LLMProviderRegistry
 *
 * Singleton registry for LLM providers.
 * Provides methods to register, retrieve, and manage provider instances.
 *
 * Usage:
 * <code>
 * $registry = LLMProviderRegistry::getInstance();
 * $registry->registerProvider('openai', $openaiProvider);
 * $provider = $registry->getProvider('openai');
 * </code>
 */
class LLMProviderRegistry
{
  /**
   * @var LLMProviderRegistry|null Singleton instance
   */
  private static ?LLMProviderRegistry $instance = null;

  /**
   * @var array<string, LLMProviderInterface> Registered providers
   */
  private array $providers = [];

  /**
   * Private constructor for singleton pattern
   *
   * Prevents direct instantiation. Use getInstance() instead.
   */
  private function __construct()
  {
    // Private constructor for singleton
  }

  /**
   * Get singleton instance
   *
   * Returns the single instance of the registry.
   * Creates the instance if it doesn't exist yet.
   *
   * @return LLMProviderRegistry Singleton instance
   */
  public static function getInstance(): LLMProviderRegistry
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  /**
   * Register a provider
   *
   * Adds a provider to the registry with the given name.
   * If a provider with the same name exists, it will be replaced.
   *
   * @param string $name Provider name (e.g., 'openai', 'anthropic')
   * @param LLMProviderInterface $provider Provider instance
   * @return void
   */
  public function registerProvider(string $name, LLMProviderInterface $provider): void
  {
    $this->providers[$name] = $provider;
  }

  /**
   * Get a provider by name
   *
   * Retrieves a registered provider by its name.
   * Throws an exception if the provider is not found.
   *
   * @param string $name Provider name
   * @return LLMProviderInterface Provider instance
   * @throws ProviderNotFoundException If provider is not registered
   */
  public function getProvider(string $name): LLMProviderInterface
  {
    if (!$this->hasProvider($name)) {
      throw new ProviderNotFoundException("Provider '{$name}' not found in registry");
    }

    return $this->providers[$name];
  }

  /**
   * Check if provider exists
   *
   * Checks whether a provider with the given name is registered.
   *
   * @param string $name Provider name
   * @return bool True if provider exists, false otherwise
   */
  public function hasProvider(string $name): bool
  {
    return isset($this->providers[$name]);
  }

  /**
   * Get all registered providers
   *
   * Returns an associative array of all registered providers.
   * Keys are provider names, values are provider instances.
   *
   * @return array<string, LLMProviderInterface> All registered providers
   */
  public function getAllProviders(): array
  {
    return $this->providers;
  }

  /**
   * Get provider names
   *
   * Returns an array of all registered provider names.
   *
   * @return array<string> Provider names
   */
  public function getProviderNames(): array
  {
    return array_keys($this->providers);
  }

  /**
   * Unregister a provider
   *
   * Removes a provider from the registry.
   * Does nothing if the provider doesn't exist.
   *
   * @param string $name Provider name
   * @return void
   */
  public function unregisterProvider(string $name): void
  {
    unset($this->providers[$name]);
  }

  /**
   * Clear all providers
   *
   * Removes all registered providers from the registry.
   * Useful for testing or resetting the registry state.
   *
   * @return void
   */
  public function clearAll(): void
  {
    $this->providers = [];
  }

  /**
   * Get provider count
   *
   * Returns the number of registered providers.
   *
   * @return int Number of providers
   */
  public function count(): int
  {
    return count($this->providers);
  }

  /**
   * Prevent cloning of singleton
   */
  private function __clone()
  {
    // Prevent cloning
  }

  /**
   * Prevent unserialization of singleton
   */
  public function __wakeup()
  {
    throw new \Exception("Cannot unserialize singleton");
  }
}
