<?php
/**
 * Domain-specific field resolver for metadata extraction
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Config;

class DomainFields
{
  /**
   * Resolve domain-specific fields for metadata extraction.
   *
   * @param string|null $domain Domain identifier (e.g., Ecommerce, Hr, Finance). Defaults to active domain.
   * @return array
   */
  public static function getPossibleFields(?string $domain = null): array
  {
    $domain = $domain ?? DomainConfig::getActivities();
    $domain = trim((string)$domain);

    if ($domain === '') {
      return [];
    }

    $className = self::resolveDomainClass($domain);
    if ($className !== null && class_exists($className) && method_exists($className, 'getPossibleFields')) {
      return (array)$className::getPossibleFields();
    }

    return [];
  }

  /**
   * Build the fully-qualified domain class name.
   *
   * @param string $domain
   * @return string|null
   */
  private static function resolveDomainClass(string $domain): ?string
  {
    $module = self::resolveModuleName($domain);
    if ($module === null || $module === '') {
      return null;
    }
    return 'ClicShopping\\Apps\\AI\\' . $module . '\\Classes\\ClicShoppingAdmin\\Domain';
  }

  /**
   * Resolve module name from a domain string.
   *
   * @param string $domain
   * @return string|null
   */
  private static function resolveModuleName(string $domain): ?string
  {
    // to customize
    $map = [
      'ecommerce' => 'Ecommerce',
      'hr' => 'Hr',
      'rh' => 'Hr',
      'finance' => 'Finance',
      'trading' => 'Trading',
    ];

    $raw = trim($domain);
    $key = strtolower($raw);
    if ($key === '') {
      return null;
    }

    if (isset($map[$key])) {
      return $map[$key];
    }

    if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $raw)) {
      return $raw;
    }

    return self::deriveModuleName($raw);
  }

  /**
   * Derive module name from a domain string.
   * Preserves case when already specified; otherwise falls back to StudlyCase.
   *
   * @param string $value
   * @return string
   */
  private static function deriveModuleName(string $value): string
  {
    $value = trim($value);
    if ($value === '') {
      return $value;
    }

    // If it contains separators, normalize to StudlyCase.
    if (preg_match('/[^a-z0-9]/i', $value)) {
      return self::toStudlyCase($value);
    }

    // Preserve provided casing (assumes App name is provided).
    return $value;
  }

  /**
   * Convert a string to StudlyCase using non-alphanumeric separators.
   *
   * @param string $value
   * @return string
   */
  private static function toStudlyCase(string $value): string
  {
    $parts = preg_split('/[^a-z0-9]+/i', $value, -1, PREG_SPLIT_NO_EMPTY);
    $parts = array_map('ucfirst', array_map('strtolower', $parts));
    return implode('', $parts);
  }

  /**
   * Resolve an App class for a domain with fallback to Ecommerce.
   *
   * @param string|null $domain
   * @param string $classBase Class name without namespace (e.g., 'SchemaConfig')
   * @param string|null $fallbackModule
   * @return string|null
   */
  public static function resolveAppClass(?string $domain, string $classBase, ?string $fallbackModule = 'Ecommerce'): ?string
  {
    $module = self::getModuleName($domain);
    if ($module !== null && $module !== '') {
      $className = 'ClicShopping\\Apps\\AI\\' . $module . '\\Classes\\ClicShoppingAdmin\\' . $classBase;
      if (class_exists($className)) {
        return $className;
      }
    }

    if ($fallbackModule !== null && $fallbackModule !== '') {
      $fallbackClass = 'ClicShopping\\Apps\\AI\\' . $fallbackModule . '\\Classes\\ClicShoppingAdmin\\' . $classBase;
      if (class_exists($fallbackClass)) {
        return $fallbackClass;
      }
    }

    return null;
  }

  /**
   * Get module name from a domain string.
   *
   * @param string|null $domain
   * @return string|null
   */
  public static function getModuleName(?string $domain): ?string
  {
    if ($domain === null) {
      return null;
    }
    return self::resolveModuleName($domain);
  }
}
