<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Config;

/**
 * SystemConfig - Base + override loader for the agnostic AI system configuration.
 *
 * Resolution order for a section (later layers win):
 *   1. Hard-coded defaults supplied by the caller.
 *   2. Agnostic base file shipped with the engine: AI/Config/chat_system_config.json
 *   3. Optional per-deployment override: Custom/Conf/chat_system_config.json
 *
 * The base file stays pristine (never written to). Runtime writes target the
 * Custom override only, so the engine config survives upgrades without a fork and
 * Core/ClicShopping/AI/ remains untouched. This relies on the same Custom/Conf/
 * convention already used by the bootstrap (CLICSHOPPING::loadConfig), so no change
 * to the autoloader is required.
 *
 * Note: configuration constants (CLICSHOPPING_APP_CHATGPT_*) remain the highest
 * priority channel and are applied by each consumer after this merge, where relevant.
 */
final class SystemConfig
{
  /** Agnostic defaults shipped with the engine. */
  private const BASE_FILE = __DIR__ . '/chat_system_config.json';

  /** Optional per-deployment override (project Custom/ layer). */
  private const OVERRIDE_FILE = __DIR__ . '/../../Custom/Conf/chat_system_config.json';

  /** @var array<string, array<string, mixed>> Per-section merge cache. */
  private static array $cache = [];

  /**
   * Returns a configuration section merged across defaults, base and override.
   *
   * @param string $section Top-level section name (e.g. 'Semantics').
   * @param array<string, mixed> $defaults Hard-coded fallback values.
   * @return array<string, mixed>
   */
  public static function getSection(string $section, array $defaults = []): array
  {
    if (!isset(self::$cache[$section])) {
      $merged = $defaults;

      foreach ([self::BASE_FILE, self::OVERRIDE_FILE] as $file) {
        $data = self::readFile($file);

        if (isset($data[$section]) && \is_array($data[$section])) {
          $merged = array_merge($merged, $data[$section]);
        }
      }

      self::$cache[$section] = $merged;
    }

    return self::$cache[$section];
  }

  /**
   * Persists a section to the Custom override file, leaving the base untouched.
   *
   * @param string $section Top-level section name.
   * @param array<string, mixed> $values Values to store for that section.
   * @return bool True on success.
   */
  public static function saveSection(string $section, array $values): bool
  {
    $config = self::readFile(self::OVERRIDE_FILE);
    $config[$section] = $values;

    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
      return false;
    }

    $result = @file_put_contents(self::OVERRIDE_FILE, $json, LOCK_EX);

    if ($result !== false) {
      unset(self::$cache[$section]);
    }

    return $result !== false;
  }

  /**
   * Clears the in-memory merge cache (mainly for tests).
   */
  public static function reset(): void
  {
    self::$cache = [];
  }

  /**
   * Safely decodes a JSON config file, returning [] when missing or invalid.
   *
   * @return array<string, mixed>
   */
  private static function readFile(string $file): array
  {
    if (!is_file($file)) {
      return [];
    }

    $data = json_decode((string) file_get_contents($file), true);

    return \is_array($data) ? $data : [];
  }
}
