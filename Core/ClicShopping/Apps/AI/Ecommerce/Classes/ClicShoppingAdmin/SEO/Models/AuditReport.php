<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Models;

/**
 * AuditReport
 *
 * Data model representing the results of an SEO audit.
 *
 * Properties:
 * - summary: textual overview of audit findings.
 * - improvements: structured list of observed improvements.
 * - recommendations: structured list of suggested actions.
 * - qualityScore: numeric evaluation of overall SEO quality.
 *
 * Methods:
 * - __construct(array $data): initializes properties from input array with defaults.
 * - toArray(): returns a normalized associative array representation of the audit report.
 */
class AuditReport
{
  public string $summary;
  public array $improvements;
  public array $recommendations;
  public int $qualityScore;

  /**
   * Constructor.
   *
   * Maps input array keys to properties with type normalization and defaults.
   *
   * @param array $data associative array with keys 'summary', 'improvements', 'recommendations', 'quality_score'.
   */
  public function __construct(array $data = [])
  {
    $this->summary = (string)($data['summary'] ?? '');
    $this->improvements = $data['improvements'] ?? [];
    $this->recommendations = $data['recommendations'] ?? [];
    $this->qualityScore = (int)($data['quality_score'] ?? 0);
  }

  /**
   * Converts the audit report object into an array.
   *
   * Ensures consistent structure for serialization, logging, or integration with other components.
   *
   * @return array normalized audit report with keys: summary, improvements, recommendations, quality_score.
   */
  public function toArray(): array
  {
    return [
      'summary' => $this->summary,
      'improvements' => $this->improvements,
      'recommendations' => $this->recommendations,
      'quality_score' => $this->qualityScore,
    ];
  }
}
