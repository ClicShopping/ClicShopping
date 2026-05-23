<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Models;

/**
 * ValidationResult
 *
 * Data model representing the results of an SEO validation process.
 *
 * Properties:
 * - approved: indicates if the proposed SEO changes are approved.
 * - qualityScore: numeric score representing overall content quality.
 * - issues: list of identified issues with SEO content.
 * - suggestions: list of recommended improvements.
 * - isSpam: flag indicating detection of spam or keyword stuffing.
 * - spamIndicators: details of spam or keyword issues detected.
 * - lengths: metrics related to content length validation (titles, descriptions, etc.).
 * - coherence: results of content coherence checks.
 *
 * Methods:
 * - __construct(array $data): initializes properties from input array with default values.
 * - toArray(): returns a normalized associative array representation of the validation result.
 */
class ValidationResult
{
  public bool $approved;
  public int $qualityScore;
  public array $issues;
  public array $suggestions;
  public bool $isSpam;
  public array $spamIndicators;
  public array $lengths;
  public array $coherence;

  /**
   * Constructor.
   *
   * Maps input array keys to object properties with type normalization and default fallback values.
   *
   * @param array $data associative array with keys matching properties.
   */
  public function __construct(array $data = [])
  {
    $this->approved = (bool)($data['approved'] ?? false);
    $this->qualityScore = (int)($data['quality_score'] ?? 0);
    $this->issues = $data['issues'] ?? [];
    $this->suggestions = $data['suggestions'] ?? [];
    $this->isSpam = (bool)($data['is_spam'] ?? false);
    $this->spamIndicators = $data['spam_indicators'] ?? [];
    $this->lengths = $data['lengths'] ?? [];
    $this->coherence = $data['coherence'] ?? [];
  }

  /**
   * Converts the validation result object into an array.
   *
   * Provides consistent structure for logging, serialization, or further processing.
   *
   * @return array normalized validation result with keys matching properties.
   */
  public function toArray(): array
  {
    return [
      'approved' => $this->approved,
      'quality_score' => $this->qualityScore,
      'issues' => $this->issues,
      'suggestions' => $this->suggestions,
      'is_spam' => $this->isSpam,
      'spam_indicators' => $this->spamIndicators,
      'lengths' => $this->lengths,
      'coherence' => $this->coherence,
    ];
  }
}