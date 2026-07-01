<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO;

/**
 * SeoEnhancementScorer — structural SEO before/after comparison (Lot 2a).
 *
 * Decomposes the crawler signals (headings, schema, word-count, meta, internal
 * links) + a deterministic readability score into a table, and flags a CRITICAL
 * structural regression (schema removed / headings zeroed / word-count collapse)
 * which the pipeline rolls back on. All other metrics are informational. No LLM.
 */
class SeoEnhancementScorer
{
  /** Optimized body word count below before × this ratio = critical collapse. */
  public const WORDCOUNT_COLLAPSE_RATIO = 0.6;

  private Readability $readability;

  public function __construct()
  {
    $this->readability = new Readability();
  }

  /**
   * @param array<string,mixed> $seoBefore Crawler report of the page before optimization.
   * @param array<string,mixed> $seoAfter  Crawler report after optimization.
   * @return array{seo_score:array{before:int,after:int},metrics:list<array<string,mixed>>,critical_regression:bool,critical_reasons:list<string>}
   */
  public function score(array $seoBefore, array $seoAfter, string $sourceText, string $optimizedText): array
  {
    $hBefore = $this->headings($seoBefore);
    $hAfter  = $this->headings($seoAfter);
    $schemaBefore = $this->schemaOk($seoBefore);
    $schemaAfter  = $this->schemaOk($seoAfter);
    $wcBefore = (int)($seoBefore['wordcount_body'] ?? 0);
    $wcAfter  = (int)($seoAfter['wordcount_body']  ?? 0);

    $reasons = [];
    if ($schemaBefore && !$schemaAfter) {
      $reasons[] = 'schema_removed';
    }
    if ($hBefore > 0 && $hAfter === 0) {
      $reasons[] = 'headings_zeroed';
    }
    if ($wcBefore > 0 && $wcAfter < $wcBefore * self::WORDCOUNT_COLLAPSE_RATIO) {
      $reasons[] = 'wordcount_collapse';
    }

    $metrics = [
      $this->metric('headings',       'Headings (H1-H3)',     $hBefore, $hAfter, true,  in_array('headings_zeroed', $reasons, true)),
      $this->metric('schema',         'Schema.org',           $schemaBefore ? 1 : 0, $schemaAfter ? 1 : 0, true, in_array('schema_removed', $reasons, true)),
      $this->metric('word_count',     'Word count',           $wcBefore, $wcAfter, true,  in_array('wordcount_collapse', $reasons, true)),
      // meta length is NEUTRAL (higher_is_better = null): a longer meta title/desc
      // is not "better" (it can exceed the SERP truncation limit) — show Δ muted.
      $this->metric('meta_title',     'Meta title (chars)',   mb_strlen((string)($seoBefore['title'] ?? '')),       mb_strlen((string)($seoAfter['title'] ?? '')),       null, false),
      $this->metric('meta_desc',      'Meta description (chars)', mb_strlen((string)($seoBefore['description'] ?? '')), mb_strlen((string)($seoAfter['description'] ?? '')), null, false),
      $this->metric('internal_links', 'Internal links',       (int)($seoBefore['internal_links'] ?? 0), (int)($seoAfter['internal_links'] ?? 0), true, false),
      $this->metric('faq',            'FAQ questions',        (int)($seoBefore['faq']['questions'] ?? 0), (int)($seoAfter['faq']['questions'] ?? 0), true, false),
      $this->metric('readability',    'Readability (Kandel-Moles)', $this->readability->score($sourceText), $this->readability->score($optimizedText), true, false),
    ];

    return [
      'seo_score' => [
        'before' => (int)($seoBefore['seo_score'] ?? 0),
        'after'  => (int)($seoAfter['seo_score']  ?? 0),
      ],
      'metrics'             => $metrics,
      'critical_regression' => !empty($reasons),
      'critical_reasons'    => $reasons,
    ];
  }

  private function headings(array $report): int
  {
    return count((array)($report['h1'] ?? []))
         + count((array)($report['h2'] ?? []))
         + count((array)($report['h3'] ?? []));
  }

  private function schemaOk(array $report): bool
  {
    return (bool)($report['schema_org']['detected'] ?? false)
        && (bool)($report['schema_org']['valid'] ?? true);
  }

  /**
   * @return array<string,mixed>
   */
  private function metric(string $key, string $label, float|int $before, float|int $after, ?bool $higherIsBetter, bool $critical): array
  {
    return [
      'key'              => $key,
      'label'            => $label,
      'before'           => $before,
      'after'            => $after,
      'higher_is_better' => $higherIsBetter,
      'critical'         => $critical,
    ];
  }
}
