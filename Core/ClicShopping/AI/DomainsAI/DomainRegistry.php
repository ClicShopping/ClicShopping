<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI;

use ClicShopping\OM\Apps;
use ClicShopping\OM\Interfaces\DomainAppInterface;
use ClicShopping\OM\Cache;

/**
 * Domain Registry - Central registry for managing domain-specific AI applications
 * 
 * Singleton class that manages domain apps in the multi-domain RAG system.
 * Handles automatic discovery, registration, and active domain management.
 */
class DomainRegistry
{
  private static ?DomainRegistry $instance = null;
  private array $domains = [];
  private ?string $activeDomainId = null;
  private const SESSION_KEY = 'active_domain_id';
  private const CACHE_KEY_PREFIX = 'domain_';

  private function __construct()
  {
    if (isset($_SESSION[self::SESSION_KEY])) {
      $this->activeDomainId = $_SESSION[self::SESSION_KEY];
    }
  }

  /**
   * Get the singleton instance of DomainRegistry
   */
  public static function getInstance(): DomainRegistry
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Discover and register all domain apps from Apps/AI/ directory
   * Scans for apps implementing DomainAppInterface and registers them
   */
  public function discoverDomainApps(): void
  {
    $allApps = Apps::getAll();

    foreach ($allApps as $appInfo) {
      if (isset($appInfo['vendor']) && $appInfo['vendor'] === 'AI') {
        $vendor = $appInfo['vendor'];
        $appName = $appInfo['app'];
        $className = 'ClicShopping\\Apps\\' . $vendor . '\\' . $appName . '\\' . $appName;

        if (class_exists($className)) {
          $interfaces = class_implements($className);
          if (isset($interfaces['ClicShopping\\OM\\Interfaces\\DomainAppInterface'])) {
            new $className();
          }
        }
      }
    }
  }

  /**
   * Register a domain app
   * Adds domain app to registry and sets as active if first domain
   */
  public function registerApp(DomainAppInterface $app): void
  {
    $domainId = $app->getDomainId();
    $this->domains[$domainId] = $app;

    if ($this->activeDomainId === null && count($this->domains) === 1) {
      $this->setActiveDomain($domainId);
    }
  }

  /**
   * Set the active domain
   * Changes active domain and invalidates previous domain caches
   * 
   * @return bool True if successful, false if domain not found
   */
  public function setActiveDomain(string $domainId): bool
  {
    if (!isset($this->domains[$domainId])) {
      return false;
    }

    $previousDomainId = $this->activeDomainId;
    $this->activeDomainId = $domainId;
    $_SESSION[self::SESSION_KEY] = $domainId;

    if ($previousDomainId !== null && $previousDomainId !== $domainId) {
      $this->invalidateDomainCaches($previousDomainId);
    }

    return true;
  }

  /**
   * Get the active domain app
   * 
   * @return DomainAppInterface|null Active domain app or null if none active
   */
  public function getActiveApp(): ?DomainAppInterface
  {
    if ($this->activeDomainId === null) {
      return null;
    }
    return $this->domains[$this->activeDomainId] ?? null;
  }

  /**
   * Get a specific domain app by ID
   * 
   * @return DomainAppInterface|null Domain app or null if not found
   */
  public function getApp(string $domainId): ?DomainAppInterface
  {
    return $this->domains[$domainId] ?? null;
  }

  /**
   * Get all registered domains
   * 
   * @return array<string, DomainAppInterface> Associative array of domain apps
   */
  public function getRegisteredDomains(): array
  {
    return $this->domains;
  }

  /**
   * Invalidate domain-specific caches
   * Clears all caches related to specified domain
   */
  public function invalidateDomainCaches(string $domainId): void
  {
    if (!class_exists('ClicShopping\\OM\\Cache')) {
      return;
    }

    $cacheKeys = [
      self::CACHE_KEY_PREFIX . $domainId . '_entity_config',
      self::CACHE_KEY_PREFIX . $domainId . '_guardrails_config',
      self::CACHE_KEY_PREFIX . $domainId . '_llm_prompts',
      self::CACHE_KEY_PREFIX . $domainId . '_translation',
      self::CACHE_KEY_PREFIX . $domainId . '_query_classification',
      self::CACHE_KEY_PREFIX . $domainId . '_entity_detection',
    ];

    foreach ($cacheKeys as $key) {
      Cache::clear($key);
    }
  }

  /**
   * Get the active domain ID
   * 
   * @return string|null Active domain ID or null if none active
   */
  public function getActiveDomainId(): ?string
  {
    return $this->activeDomainId;
  }

  /**
   * Check if a domain is registered
   */
  public function hasDomain(string $domainId): bool
  {
    return isset($this->domains[$domainId]);
  }

  /**
   * Get the count of registered domains
   */
  public function getDomainCount(): int
  {
    return count($this->domains);
  }

  /**
   * Clear all registered domains
   * Used for testing purposes only
   */
  public function clearAll(): void
  {
    $this->domains = [];
    $this->activeDomainId = null;
    unset($_SESSION[self::SESSION_KEY]);
  }
}
