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
 * AuditPrompts
 *
 * Provides access to language-specific prompt templates used in SEO audits.
 *
 * Properties:
 * - app: reference to the Ecommerce application object for loading definitions.
 * - languageCode: ISO code of the target language for prompts.
 * - loaded: flag indicating whether definitions have been loaded.
 *
 * Methods:
 * - __construct(string $languageCode): initializes with a target language and ensures the Ecommerce app is registered.
 * - getSummaryPrompt(array $vars): returns the prompt template for summarizing audit results, with optional variable substitution.
 * - getImprovementsPrompt(array $vars): returns the prompt template for listing detected improvements.
 * - getRecommendationsPrompt(array $vars): returns the prompt template for generating recommendations.
 * - loadDefinitions(): loads the language definitions for prompts from the CMS if not already loaded.
 *
 * Ensures that prompt templates are loaded once per instance and localized according to the specified language.
 */
class AuditPrompts
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

  public function getSummaryPrompt(array $vars = []): string
  {
    $this->loadDefinitions();
    return $this->app->getDef('seo_audit_summary_prompt', $vars);
  }

  private function loadDefinitions(): void
  {
    if ($this->loaded) {
      return;
    }

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/seo_audit_prompts', $this->languageCode);
    $this->loaded = true;
  }

  public function getImprovementsPrompt(array $vars = []): string
  {
    $this->loadDefinitions();
    return $this->app->getDef('seo_audit_improvements_prompt', $vars);
  }

  public function getRecommendationsPrompt(array $vars = []): string
  {
    $this->loadDefinitions();
    return $this->app->getDef('seo_audit_recommendations_prompt', $vars);
  }
}
