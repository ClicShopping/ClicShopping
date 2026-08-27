<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI;

use ClicShopping\AI\Security\SecurityLogger;
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
  private bool $discoveryAttempted = false;
  private bool $absenceLogged = false;
  private array $absentReaders = [];
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
    $this->discoverOnce();

    $app = $this->activeDomainId === null ? null : ($this->domains[$this->activeDomainId] ?? null);

    if ($app === null) {
      $this->logAbsence();
    }

    return $app;
  }

  /**
   * Log the absence of an active App — ONCE per request, naming the caller.
   *
   * Every reader of getActiveApp() falls back silently, and the fallback looks exactly like a
   * normal run: that is why a dead namespace guard survived until 2026-08-18 with no App ever
   * registered. Logging the absence is what makes the next mute fallback visible.
   */
  private function logAbsence(): void
  {
    // [0] logAbsence, [1] getActiveApp, [2] the reader we want to name.
    $frame = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2] ?? [];
    $caller = ($frame['class'] ?? 'top-level') . '::' . ($frame['function'] ?? 'unknown');

    $this->absentReaders[$caller] = ($this->absentReaders[$caller] ?? 0) + 1;

    if ($this->absenceLogged) {
      return;
    }

    $this->absenceLogged = true;

    try {
      (new SecurityLogger())->logStructured(
        'warning',
        'DomainRegistry',
        'no_active_domain',
        [
          'first_caller' => $caller,
          'registered_domains' => count($this->domains),
          'discovery_attempted' => $this->discoveryAttempted,
          'note' => 'every reader is silently on its fallback path for this request'
        ]
      );
    } catch (\Throwable) {
      // Never let observability break a request.
    }
  }

  /**
   * Readers served a null App during this request, with their hit count.
   *
   * @return array<string, int>
   */
  public function getAbsentReaders(): array
  {
    return $this->absentReaders;
  }

  /**
   * Discover on FIRST READ, once per request.
   *
   * No App instantiates itself on a chat request - only its hooks do - and
   * discoverDomainApps() had no caller anywhere: the registry stayed empty for every reader,
   * each of which silently fell back. The flag is raised BEFORE discovery so an App whose
   * constructor reads the registry gets null instead of recursing.
   *
   * @return void
   */
  private function discoverOnce(): void
  {
    if ($this->discoveryAttempted || $this->domains !== []) {
      return;
    }

    $this->discoveryAttempted = true;
    $this->discoverDomainApps();
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
   * Clear all registered domains, and keep them cleared.
   *
   * The discovery flag is RAISED, not reset: emptying $domains alone puts the registry back in
   * its start-of-request state, and the very next read would call discoverOnce(), which
   * re-registers every installed domain App. "No domain active" is a real configuration — an
   * install carrying no Apps/AI domain — and it is what the callers of this method ask for.
   *
   * Used for testing purposes only: no production code clears the registry.
   */
  public function clearAll(): void
  {
    $this->domains = [];
    $this->activeDomainId = null;
    $this->discoveryAttempted = true;
    unset($_SESSION[self::SESSION_KEY]);
  }
}
