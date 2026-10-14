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
 * ContentGenerationPrompts
 *
 * Loads SEO content generation prompts from language definitions.
 * Prompts are stored per language under the AI Ecommerce app.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Prompts
 * @since 2026-03-03
 */
class ContentGenerationPrompts
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
    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/seo_prompts', $this->languageCode);
  }

  public function getMetaTitlePrompt(array $vars = []): string
  {
    return $this->app->getDef('seo_prompt_meta_title', $vars);
  }

  public function getMetaDescriptionPrompt(array $vars = []): string
  {
    return $this->app->getDef('seo_prompt_meta_description', $vars);
  }

  public function getMetaKeywordsPrompt(array $vars = []): string
  {
    return $this->app->getDef('seo_prompt_meta_keywords', $vars);
  }

  public function getFaqPrompt(array $vars = []): string
  {
    return $this->app->getDef('seo_prompt_faq', $vars);
  }

  public function getH2Prompt(array $vars = []): string
  {
    return $this->app->getDef('seo_prompt_h2', $vars);
  }

  public function getEnrichedDescriptionPrompt(array $vars = []): string
  {
    return $this->app->getDef('seo_prompt_enriched_description', $vars);
  }

  /**
   * Agentic (Pure LLM Mode) source-fidelity audit prompt: compares a SOURCE
   * description against the OPTIMIZED rewrite and reports the source facts that
   * were lost. Language-agnostic (the LLM judges semantically, so it works for
   * FR/DE/IT/EN alike) — replaces the brittle keyword/stem coverage heuristic.
   *
   * @param array $vars Expects 'source' and 'optimized'.
   */
  public function getFidelityCheckPrompt(array $vars = []): string
  {
    return $this->app->getDef('seo_prompt_fidelity_check', $vars);
  }

  /**
   * Agentic (Pure LLM Mode) per-answer FAQ fact-audit prompt: given the product
   * data (source of truth) and one FAQ question + answer, reports whether every
   * factual claim in the answer is supported by the product data. Complements
   * the topical cosine grounding — which cannot detect a plausible invented fact
   * (a fabricated warranty or dimension) that still shares product vocabulary.
   *
   * @param array $vars Expects 'source', 'question' and 'answer'.
   */
  public function getFaqFidelityCheckPrompt(array $vars = []): string
  {
    return $this->app->getDef('seo_prompt_faq_fidelity', $vars);
  }

  /**
   * Semantic enhancement keyword-coverage analyst prompt: counts keyword/LSI
   * coverage in SOURCE vs OPTIMIZED text. Returns a JSON object with coverage
   * metrics: primary_source/primary_optimized, secondary_source/secondary_optimized
   * (KEYWORDS count), and lsi_source/lsi_optimized (TOPICS count).
   *
   * @param array $vars Expects 'serp_keywords', 'serp_topics', 'primary_keyword', 'source', 'optimized'.
   */
  public function getSemanticEnhancementPrompt(array $vars = []): string
  {
    return $this->app->getDef('seo_prompt_semantic_enhancement', $vars);
  }

  public function getSummaryPrompt(array $vars = []): string
  {
    return $this->app->getDef('seo_prompt_summary', $vars);
  }

  /**
   * T4.2 — Long-form category body description prompt.
   *
   * Generates 300-500 words of HTML body copy for a category page
   * to fix thin-content penalty. The text is injected into
   * categories_description.categories_description by SeoEntityAdapter.
   *
   * Variables expected: entity_name, entity_type, primary_keyword,
   *   search_intent, topics, top_products (JSON), subcategories (JSON),
   *   product_count, ai_overview_insights, entity_description,
   *   validation_feedback.
   */
  public function getCategoryBodyDescriptionPrompt(array $vars = []): string
  {
    return $this->app->getDef('seo_prompt_category_body_description', $vars);
  }

  /**
   * T3.2 — Schema.org Product JSON-LD prompt.
   *
   * Generates a JSON-LD Product block for product pages.
   * Variables expected: entity_name, entity_type, primary_keyword,
   *   product_brand, product_model, product_price, product_currency,
   *   product_stock, product_status, product_sku, product_url, product_image.
   */
  public function getSchemaProductPrompt(array $vars = []): string
  {
    return $this->app->getDef('seo_prompt_schema_product', $vars);
  }

  /**
   * T3.3 — Schema.org BreadcrumbList + ItemList prompt for category pages.
   *
   * Generates a JSON-LD block combining BreadcrumbList (category hierarchy)
   * and ItemList (top featured products).
   * Variables expected: entity_name, entity_type, category_url, base_url,
   *   breadcrumb_path (JSON array), top_products (JSON array of {name, url, image, price}).
   */
  public function getSchemaCategoryPrompt(array $vars = []): string
  {
    return $this->app->getDef('seo_prompt_schema_category', $vars);
  }
}
