<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Classes\Shared\ReviewSentiment;

/**
 * SentimentAnalysisData — safe accessor over the LLM `analysis_json`.
 *
 * On any decode/shape failure the object degrades to "unstructured": the
 * front and back-office render the plain prose fallback instead of breaking.
 */
class SentimentAnalysisData
{
  private bool $structured = false;
  private string $dominant = 'neutral';
  private array $strengths = [];
  private array $issues = [];
  private array $themes = [];
  private array $quotes = ['positive' => [], 'negative' => []];
  private string $summary = '';

  public static function fromJson(?string $json, string $fallbackProse = ''): self
  {
    $self = new self();
    $self->summary = $fallbackProse;

    if ($json === null || trim($json) === '') {
      return $self;
    }

    $data = json_decode($json, true);
    if (!is_array($data) || !isset($data['summary'])) {
      return $self; // keep fallback prose, unstructured
    }

    $self->structured = true;
    $self->dominant   = (string)($data['dominant_sentiment'] ?? 'neutral');
    $self->strengths  = array_values(array_filter(array_map('strval', (array)($data['strengths'] ?? []))));
    $self->issues     = array_values(array_filter(array_map('strval', (array)($data['issues'] ?? []))));
    $self->summary    = (string)($data['summary'] ?? $fallbackProse);

    foreach ((array)($data['themes'] ?? []) as $t) {
      if (!is_array($t) || !isset($t['label'])) {
        continue;
      }
      $self->themes[] = [
        'label'     => (string)$t['label'],
        'frequency' => (int)($t['frequency'] ?? 0),
        'sentiment' => (string)($t['sentiment'] ?? 'neutral'),
      ];
    }

    $q = (array)($data['quotes'] ?? []);
    $self->quotes = [
      'positive' => array_values(array_map('strval', (array)($q['positive'] ?? []))),
      'negative' => array_values(array_map('strval', (array)($q['negative'] ?? []))),
    ];

    return $self;
  }

  public function isStructured(): bool { return $this->structured; }
  public function getDominantSentiment(): string { return $this->dominant; }
  public function getStrengths(): array { return $this->strengths; }
  public function getIssues(): array { return $this->issues; }
  public function getThemes(): array { return $this->themes; }
  public function getQuotes(): array { return $this->quotes; }
  public function getSummary(): string { return $this->summary; }
}
