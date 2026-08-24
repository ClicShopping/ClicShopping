<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\AI\Config;

/**
 * PromptPlaceholderRegistryConfig
 *
 * Scan configuration for the agnostic dynamic-placeholder registry, mirroring
 * {@see WebSearchRegistryConfig}: same `Apps/AI/{Domain}/` opt-in convention,
 * a different registration file.
 */
class PromptPlaceholderRegistryConfig
{
  /**
   * Default sub-path (inside CLICSHOPPING_BASE_DIR) where domain Apps live.
   */
  public const DEFAULT_DOMAIN_BASE_PATH = 'Apps/AI/';

  /**
   * Relative path (under each Apps/AI/{Domain}/) of the registration class file.
   */
  public const REGISTRATION_CLASS_RELATIVE_PATH =
    'Classes/ClicShoppingAdmin/Prompt/Registration/PromptPlaceholderRegistration.php';

  /**
   * FQCN template for the per-domain registration class. `{Domain}` is replaced
   * with the domain directory name (e.g. 'Ecommerce'). The class MUST expose a
   * public static `register(PromptPlaceholderRegistry $r): void`.
   */
  public const REGISTRATION_CLASS_FQCN_TEMPLATE =
    'ClicShopping\\Apps\\AI\\{Domain}\\Classes\\ClicShoppingAdmin\\Prompt\\Registration\\PromptPlaceholderRegistration';

  /**
   * Resolved base path for the domain scan.
   *
   * @return string Path ending with a trailing slash
   */
  public static function getDomainBasePath(): string
  {
    if (\defined('CLICSHOPPING_APP_API_AI_PROMPT_PLACEHOLDER_BASE_PATH')) {
      $override = \trim((string)\constant('CLICSHOPPING_APP_API_AI_PROMPT_PLACEHOLDER_BASE_PATH'));

      if ($override !== '') {
        return \rtrim($override, '/') . '/';
      }
    }

    return self::DEFAULT_DOMAIN_BASE_PATH;
  }

  /**
   * Whether the auto-scan bootstrap should run. Defaults to enabled.
   *
   * @return bool True if the registry should scan Apps/AI/* on bootstrap
   */
  public static function isAutoScanEnabled(): bool
  {
    if (\defined('CLICSHOPPING_APP_API_AI_PROMPT_PLACEHOLDER_AUTOSCAN')) {
      return \constant('CLICSHOPPING_APP_API_AI_PROMPT_PLACEHOLDER_AUTOSCAN') === 'True';
    }

    return true;
  }

  /**
   * Resolve the registration class name for a given domain.
   *
   * @param string $domain Domain directory name (the {Domain} segment under Apps/AI/)
   * @return string Fully qualified class name
   */
  public static function getRegistrationClassFqcn(string $domain): string
  {
    return \str_replace('{Domain}', $domain, self::REGISTRATION_CLASS_FQCN_TEMPLATE);
  }
}
