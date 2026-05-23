<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Models;

/**
 * SerpInsights
 *
 * Data model for SERP analysis insights.
 * Contains all analysis results from SerpAgent including AI Overview data.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Models
 * @since 2026-03-02
 */
class SerpInsights
{
  public string $keyword;
  public string $intent;
  public float $intentConfidence;
  public string $intentReasoning;
  public array $features;
  public ?array $aiOverview;
  public ?array $aiOverviewInsights;
  public array $topics;
  public array $keywords;
  public array $contentPatterns;
  public array $userQuestions;
  public array $competitorInsights;
  public array $typesOfPages;
  public array $serpStability;
  public array $cannibalization;
  public array $topResults;
  public string $language;

  /**
   * Constructor
   *
   * @param array $data SERP analysis data
   */
  public function __construct(array $data = [])
  {
    $this->keyword = $data['query'] ?? '';
    $this->intent = $data['intent_dominant'] ?? 'unknown';
    $this->intentConfidence = (float)($data['intent_confidence'] ?? 0.0);
    $this->intentReasoning = $data['intent_reasoning'] ?? '';
    $this->features = $data['features_visible'] ?? [];
    $this->aiOverview = $data['ai_overview'] ?? null;
    $this->aiOverviewInsights = $data['ai_overview_insights'] ?? null;
    $this->topics = $data['topics'] ?? [];
    $this->keywords = $data['keywords'] ?? [];
    $this->contentPatterns = $data['content_patterns'] ?? [];
    $this->userQuestions = $data['user_questions'] ?? [];
    $this->competitorInsights = $data['competitor_insights'] ?? [];
    $this->typesOfPages = $data['types_of_pages'] ?? [];
    $this->serpStability = $data['serp_stability'] ?? [];
    $this->cannibalization = $data['cannibalization'] ?? [];
    $this->topResults = $data['top_results'] ?? [];
    $this->language = $data['language'] ?? 'en';
  }

  /**
   * Check if AI Overview is present
   *
   * @return bool True if AI Overview is available
   */
  public function hasAiOverview(): bool
  {
    return $this->aiOverview !== null && !empty($this->aiOverview);
  }

  /**
   * Get AI Overview summary text
   *
   * @return string AI Overview summary or empty string
   */
  public function getAiOverviewSummary(): string
  {
    if (!$this->hasAiOverview()) {
      return '';
    }

    return $this->aiOverview['full_summary'] ?? '';
  }

  /**
   * Get AI Overview key points
   *
   * @return array Key points from AI Overview analysis
   */
  public function getAiOverviewKeyPoints(): array
  {
    if ($this->aiOverviewInsights === null) {
      return [];
    }

    return $this->aiOverviewInsights['key_points'] ?? [];
  }

  /**
   * Get AI Overview relevance score
   *
   * @return float Relevance score (0.0-1.0)
   */
  public function getAiOverviewRelevance(): float
  {
    if ($this->aiOverviewInsights === null) {
      return 0.0;
    }

    return (float)($this->aiOverviewInsights['relevance_score'] ?? 0.0);
  }

  /**
   * Get content gaps identified from AI Overview
   *
   * @return array Content gaps
   */
  public function getContentGaps(): array
  {
    if ($this->aiOverviewInsights === null) {
      return [];
    }

    return $this->aiOverviewInsights['content_gaps'] ?? [];
  }

  /**
   * Get SEO opportunities from AI Overview
   *
   * @return array Opportunities
   */
  public function getOpportunities(): array
  {
    if ($this->aiOverviewInsights === null) {
      return [];
    }

    return $this->aiOverviewInsights['opportunities'] ?? [];
  }

  /**
   * Convert to array
   *
   * @return array All data as array
   */
  public function toArray(): array
  {
    return [
      'query' => $this->keyword,
      'intent_dominant' => $this->intent,
      'intent_confidence' => $this->intentConfidence,
      'intent_reasoning' => $this->intentReasoning,
      'features_visible' => $this->features,
      'ai_overview' => $this->aiOverview,
      'ai_overview_insights' => $this->aiOverviewInsights,
      'topics' => $this->topics,
      'keywords' => $this->keywords,
      'content_patterns' => $this->contentPatterns,
      'user_questions' => $this->userQuestions,
      'competitor_insights' => $this->competitorInsights,
      'types_of_pages' => $this->typesOfPages,
      'serp_stability' => $this->serpStability,
      'cannibalization' => $this->cannibalization,
      'top_results' => $this->topResults,
      'language' => $this->language,
    ];
  }

  /**
   * Create from array
   *
   * @param array $data Data array
   * @return self New instance
   */
  public static function fromArray(array $data): self
  {
    return new self($data);
  }
}
