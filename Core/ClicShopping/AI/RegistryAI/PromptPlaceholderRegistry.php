<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\AI\RegistryAI;

use ClicShopping\AI\Config\PromptPlaceholderRegistryConfig;
use ClicShopping\AI\InterfacesAI\PromptPlaceholderProviderInterface;

/**
 * PromptPlaceholderRegistry
 *
 * Domain-agnostic, process-scoped registry of DYNAMIC prompt placeholders —
 * tokens whose value is read at runtime and therefore cannot be a constant of
 * {@see \ClicShopping\AI\Infrastructure\Prompt\PromptPlaceholders}.
 *
 * Core holds no built-in provider: a dynamic token is domain knowledge by
 * definition. Each domain App opts in by shipping
 * `Apps/AI/{Domain}/Classes/ClicShoppingAdmin/Prompt/Registration/PromptPlaceholderRegistration.php`
 * exposing `public static register(PromptPlaceholderRegistry $r): void`.
 *
 * Resolution is LAZY: a provider whose token is absent from the message is never
 * called, so a domain that registers ten providers costs nothing on a prompt
 * that uses none of them.
 *
 * @package ClicShopping\AI\RegistryAI
 */
class PromptPlaceholderRegistry
{
  private static ?self $instance = null;

  /** @var array<string, PromptPlaceholderProviderInterface> Keyed by token */
  private array $providers = [];

  private bool $domainsBootstrapped = false;

  /**
   * Shared instance — registration happens once per process.
   *
   * @return self The process-wide registry
   */
  public static function getInstance(): self
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  /**
   * Drop the shared instance. For tests only.
   *
   * @return void
   */
  public static function reset(): void
  {
    self::$instance = null;
  }

  /**
   * Register a provider. Last call wins for a given token, which lets a Custom/
   * override replace a domain provider without touching it.
   *
   * @param PromptPlaceholderProviderInterface $provider Provider to register
   * @return void
   */
  public function register(PromptPlaceholderProviderInterface $provider): void
  {
    $this->providers[$provider->getToken()] = $provider;
  }

  /**
   * Every registered token, braces included.
   *
   * @return array<int, string> Tokens known to the registry
   */
  public function getTokens(): array
  {
    $this->bootstrapDomains();

    return \array_keys($this->providers);
  }

  /**
   * Every table whose content feeds a registered token, deduplicated.
   *
   * Core collects, it never knows what the tables mean: each provider declares its own.
   * A provider that throws is skipped — a broken domain must not disable the freshness rule
   * for the others.
   *
   * @return array<int, string> Unprefixed table names
   */
  public function getSourceTables(): array
  {
    $this->bootstrapDomains();

    $tables = [];

    foreach ($this->providers as $provider) {
      try {
        $tables = \array_merge($tables, $provider->getSourceTables());
      } catch (\Throwable $e) {
        error_log('[PromptPlaceholderRegistry] source tables of ' . $provider->getToken() . ': ' . $e->getMessage());
      }
    }

    return \array_values(\array_unique($tables));
  }

  /**
   * Replace every registered token PRESENT in the message by its rendered value.
   *
   * A provider that throws is skipped and its token left untouched: a broken
   * domain must degrade the prompt, never break the request. The leftover token
   * is then caught by PromptPlaceholders::hasUnresolved().
   *
   * @param string $message Assembled prompt
   * @param int $languageId Language the prompt is being built for
   * @return string Message with the dynamic tokens resolved
   */
  public function resolve(string $message, int $languageId): string
  {
    $this->bootstrapDomains();

    foreach ($this->providers as $token => $provider) {
      if (!\str_contains($message, $token)) {
        continue;
      }

      try {
        $message = \str_replace($token, $provider->render($languageId), $message);
      } catch (\Throwable $e) {
        error_log('[PromptPlaceholderRegistry] provider failed for ' . $token . ': ' . $e->getMessage());
      }
    }

    return $message;
  }

  /**
   * Scan `Apps/AI/*` and invoke each domain's registration class, at most once.
   * A missing or malformed registration is skipped so a broken domain never
   * prevents a prompt from being built.
   *
   * @return void
   */
  private function bootstrapDomains(): void
  {
    if ($this->domainsBootstrapped || !PromptPlaceholderRegistryConfig::isAutoScanEnabled()) {
      return;
    }

    $this->domainsBootstrapped = true;

    if (!\defined('CLICSHOPPING_BASE_DIR')) {
      return;
    }

    $basePath = \CLICSHOPPING_BASE_DIR . PromptPlaceholderRegistryConfig::getDomainBasePath();

    if (!\is_dir($basePath)) {
      return;
    }

    $entries = \scandir($basePath);

    if ($entries === false) {
      return;
    }

    foreach ($entries as $entry) {
      if ($entry === '.' || $entry === '..' || !\is_dir($basePath . $entry)) {
        continue;
      }

      $file = $basePath . $entry . '/' . PromptPlaceholderRegistryConfig::REGISTRATION_CLASS_RELATIVE_PATH;

      if (!\file_exists($file)) {
        continue;
      }

      $fqcn = PromptPlaceholderRegistryConfig::getRegistrationClassFqcn($entry);

      if (!\class_exists($fqcn) || !\method_exists($fqcn, 'register')) {
        error_log('[PromptPlaceholderRegistry] registration class missing for domain ' . $entry . ': ' . $fqcn);
        continue;
      }

      try {
        $fqcn::register($this);
      } catch (\Throwable $e) {
        error_log('[PromptPlaceholderRegistry] registration failed for domain ' . $entry . ': ' . $e->getMessage());
      }
    }
  }
}
