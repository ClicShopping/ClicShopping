<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Prompts;

use ClicShopping\OM\Registry;
use ClicShopping\Apps\AI\Ecommerce\Ecommerce;

/**
 * SerpAnalysisPrompts
 *
 * LLM prompt templates for SERP analysis in SEO optimization.
 * Prompts are loaded from language definitions for maintainability.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Prompts
 * @since 2026-03-02
 */
class SerpAnalysisPrompts
{
  private static ?object $app = null;

  private static function sanitizeExternalInput(string $input): string
  {
    $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $input);
    $input = str_replace(['```', '---', '==='], '', $input);
    return $input;
  }

  private static function getApp(): object
  {
    if (self::$app === null) {
      if (!Registry::exists('Ecommerce')) {
        Registry::set('Ecommerce', new Ecommerce());
      }
      self::$app = Registry::get('Ecommerce');
      self::$app->loadDefinitions('Sites/ClicShoppingAdmin/seo_serp_analysis_prompts');
    }
    return self::$app;
  }

  public static function getIntentAnalysisPrompt(
    string $keyword,
    string $entityType,
    string $entityName,
    string $serpResults
  ): string
  {
    $serpResults = self::sanitizeExternalInput($serpResults);

    return self::getApp()->getDef('seo_serp_intent_analysis_prompt', [
      'keyword' => $keyword,
      'entity_type' => $entityType,
      'entity_name' => $entityName,
      'serp_results' => $serpResults,
    ]);
  }

  public static function getFeatureDetectionPrompt(string $serpData): string
  {
    $serpData = self::sanitizeExternalInput($serpData);

    return self::getApp()->getDef('seo_serp_feature_detection_prompt', [
      'serp_data' => $serpData,
    ]);
  }

  public static function getTopicExtractionPrompt(string $serpResults): string
  {
    $serpResults = self::sanitizeExternalInput($serpResults);

    return self::getApp()->getDef('seo_serp_topic_extraction_prompt', [
      'serp_results' => $serpResults,
    ]);
  }

  public static function getCompetitorAnalysisPrompt(
    string $serpResults,
    string $keyword
  ): string
  {
    $serpResults = self::sanitizeExternalInput($serpResults);

    return self::getApp()->getDef('seo_serp_competitor_analysis_prompt', [
      'serp_results' => $serpResults,
      'keyword' => $keyword,
    ]);
  }

  public static function getPageTypePrompt(
    string $url,
    string $title,
    string $snippet
  ): string
  {
    $title = self::sanitizeExternalInput($title);
    $snippet = self::sanitizeExternalInput($snippet);

    return self::getApp()->getDef('seo_serp_page_type_prompt', [
      'url' => $url,
      'title' => $title,
      'snippet' => $snippet,
    ]);
  }

  public static function getBatchPageTypePrompt(array $items): string
  {
    $lines = '';
    foreach ($items as $i => $item) {
      $position = $i + 1;
      $url      = (string)($item['link']    ?? '');
      $title    = self::sanitizeExternalInput((string)($item['title']   ?? ''));
      $snippet  = self::sanitizeExternalInput((string)($item['snippet'] ?? ''));
      $lines   .= "Result {$position}:\n  URL: {$url}\n  Title: {$title}\n  Snippet: {$snippet}\n\n";
    }

    return self::getApp()->getDef('seo_serp_batch_page_type_prompt', [
      'pages_list' => $lines,
    ]);
  }

  public static function getStabilityAnalysisPrompt(array $domains): string
  {
    $domainList = implode("\n", $domains);

    return self::getApp()->getDef('seo_serp_stability_analysis_prompt', [
      'domain_list' => $domainList,
    ]);
  }

  public static function getCannibalizationPrompt(
    array $results,
    string $baseDomain
  ): string
  {
    $resultsList = '';
    foreach ($results as $i => $result) {
      $title = self::sanitizeExternalInput((string)($result['title'] ?? ''));
      $resultsList .= ($i + 1) . ". " . $title . " - " . ($result['link'] ?? '') . "\n";
    }

    return self::getApp()->getDef('seo_serp_cannibalization_prompt', [
      'base_domain' => $baseDomain,
      'results_list' => $resultsList,
    ]);
  }

  public static function getAiOverviewAnalysisPrompt(
    string $summary,
    string $query,
    string $entityType
  ): string
  {
    $summary = self::sanitizeExternalInput($summary);

    return self::getApp()->getDef('seo_serp_ai_overview_analysis_prompt', [
      'summary' => $summary,
      'query' => $query,
      'entity_type' => $entityType,
    ]);
  }
}
