<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Prompts;

use ClicShopping\OM\Registry;
use ClicShopping\Apps\AI\Ecommerce\Ecommerce;

/**
 * ValidationPrompts
 *
 * Loads SEO validation prompts from language definitions.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Prompts
 * @since 2026-03-03
 */
class ValidationPrompts
{
  private object $app;
  private string $languageCode;
  private bool $loaded = false;

  public function __construct(string $languageCode)
  {
    $this->languageCode = $languageCode;

    if (!Registry::exists('Ecommerce')) {
      Registry::set('Ecommerce', new Ecommerce());
    }

    $this->app = Registry::get('Ecommerce');
  }

  public function getQualityPrompt(array $vars = []): string
  {
    $this->loadDefinitions();
    return $this->app->getDef('seo_validation_quality_prompt', $vars);
  }

  public function getSpamPrompt(array $vars = []): string
  {
    $this->loadDefinitions();
    return $this->app->getDef('seo_validation_spam_prompt', $vars);
  }

  public function getCoherencePrompt(array $vars = []): string
  {
    $this->loadDefinitions();
    return $this->app->getDef('seo_validation_coherence_prompt', $vars);
  }

  /**
   * T3.4 — Schema.org JSON-LD validation prompt.
   *
   * Variables expected: entity_type, schema_json (the raw JSON-LD string to validate).
   */
  public function getSchemaOrgPrompt(array $vars = []): string
  {
    $this->loadDefinitions();
    return $this->app->getDef('seo_validation_schema_org_prompt', $vars);
  }

  private function loadDefinitions(): void
  {
    if ($this->loaded) {
      return;
    }

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/seo_validation_prompts', $this->languageCode);
    $this->loaded = true;
  }
}
