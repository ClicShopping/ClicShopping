<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO;

use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Prompts\ContentGenerationPrompts;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Services\LLMServiceWrapper;

/**
 * SeoSemanticEnhancementScorer
 *
 * Agentic (Pure LLM Mode) count of how much of the SERP keyword/topic universe is
 * covered in the source vs the optimized text — primary keyword, secondary keyword
 * count, LSI (topic) count. Informational only: it produces table rows + a soft
 * `regressed` flag; it never blocks or rolls back. Fail-open on any LLM error.
 */
class SeoSemanticEnhancementScorer
{
  private LLMServiceWrapper $llm;
  private ContentGenerationPrompts $prompts;
  private bool $debug;

  public function __construct(string $languageCode, bool $debug = false)
  {
    $this->debug = $debug;
    $this->llm = new LLMServiceWrapper($debug);
    $this->prompts = new ContentGenerationPrompts($languageCode);
  }

  /**
   * @param list<string>|array<string,mixed> $serpKeywords
   * @param list<string>|array<string,mixed> $serpTopics
   * @return array{available:bool,primary_keyword:array{source:bool,optimized:bool},secondary_keywords:array{source:int,optimized:int},lsi_coverage:array{source:int,optimized:int},regressed:bool,metrics:list<array<string,mixed>>}
   */
  public function score(array $serpKeywords, array $serpTopics, string $primaryKeyword, string $sourceText, string $optimizedText): array
  {
    $keywords = $this->flatten($serpKeywords);
    $topics   = $this->flatten($serpTopics);
    $source    = trim((string)preg_replace('/\s+/u', ' ', (string)preg_replace('/<[^>]+>/', ' ', $sourceText)));
    $optimized = trim((string)preg_replace('/\s+/u', ' ', (string)preg_replace('/<[^>]+>/', ' ', $optimizedText)));

    if (($keywords === [] && $topics === []) || $source === '' || $optimized === '') {
      return $this->unavailable();
    }

    try {
      $prompt = $this->prompts->getSemanticEnhancementPrompt([
        'serp_keywords'   => implode(', ', $keywords),
        'serp_topics'     => implode(', ', $topics),
        'primary_keyword' => $primaryKeyword,
        'source'          => $source,
        'optimized'       => $optimized,
      ]);

      $json = $this->llm->generateStructuredResponse($prompt, [
        'maxTokens'   => 400,
        'temperature' => 0.0,
        'cache'       => false,
      ]);

      $pkSrc = (bool)($json['primary_source'] ?? false);
      $pkOpt = (bool)($json['primary_optimized'] ?? false);
      $secSrc = max(0, (int)($json['secondary_source'] ?? 0));
      $secOpt = max(0, (int)($json['secondary_optimized'] ?? 0));
      $lsiSrc = max(0, (int)($json['lsi_source'] ?? 0));
      $lsiOpt = max(0, (int)($json['lsi_optimized'] ?? 0));

      return [
        'available'          => true,
        'primary_keyword'    => ['source' => $pkSrc, 'optimized' => $pkOpt],
        'secondary_keywords' => ['source' => $secSrc, 'optimized' => $secOpt],
        'lsi_coverage'       => ['source' => $lsiSrc, 'optimized' => $lsiOpt],
        // A lost primary keyword (covered in source, not in optimized) is the most
        // damaging semantic regression, so it also raises the advisory.
        'regressed'          => ($secOpt < $secSrc) || ($lsiOpt < $lsiSrc) || ($pkSrc && !$pkOpt),
        'metrics'            => [
          ['key' => 'keyword_primary',    'label' => 'Primary keyword covered', 'before' => $pkSrc ? 1 : 0, 'after' => $pkOpt ? 1 : 0, 'higher_is_better' => true, 'critical' => false],
          ['key' => 'secondary_keywords', 'label' => 'Secondary keywords',      'before' => $secSrc,        'after' => $secOpt,        'higher_is_better' => true, 'critical' => false],
          ['key' => 'lsi_coverage',       'label' => 'LSI coverage',            'before' => $lsiSrc,        'after' => $lsiOpt,        'higher_is_better' => true, 'critical' => false],
        ],
      ];
    } catch (\Throwable $e) {
      if ($this->debug) {
        error_log('[SeoSemanticEnhancementScorer] LLM call failed (fail-open): ' . $e->getMessage());
      }
      return $this->unavailable();
    }
  }

  /** Fail-open / nothing-to-measure result: no rows, no regression. */
  private function unavailable(): array
  {
    return [
      'available'          => false,
      'primary_keyword'    => ['source' => false, 'optimized' => false],
      'secondary_keywords' => ['source' => 0, 'optimized' => 0],
      'lsi_coverage'       => ['source' => 0, 'optimized' => 0],
      'regressed'          => false,
      'metrics'            => [],
    ];
  }

  /**
   * Normalise a SERP list to a flat list of non-empty strings (handles both a
   * list of strings and a list of {term:...}/{keyword:...} objects).
   *
   * @param array<int|string,mixed> $items
   * @return list<string>
   */
  private function flatten(array $items): array
  {
    $out = [];
    foreach ($items as $it) {
      if (is_string($it)) {
        $v = trim($it);
      } elseif (is_array($it)) {
        $v = trim((string)($it['term'] ?? $it['keyword'] ?? $it['name'] ?? ''));
      } else {
        $v = '';
      }
      if ($v !== '') {
        $out[] = $v;
      }
    }
    return array_values(array_unique($out));
  }
}
