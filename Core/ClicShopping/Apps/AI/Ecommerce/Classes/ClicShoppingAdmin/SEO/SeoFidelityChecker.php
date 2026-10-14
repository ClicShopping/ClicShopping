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
 * SeoFidelityChecker
 *
 * Agentic (Pure LLM Mode) assessment of whether an OPTIMIZED description still
 * preserves every factual attribute of the SOURCE.
 *
 * This is the SOLE fidelity gate for SEO optimization. It replaced an earlier
 * language-coupled keyword/stem heuristic (a bilingual stopword list + 70%-prefix
 * stem match) that violated the project's Pure-LLM-Mode / English-only-agnostic
 * rules (AGENTS.md), broke across models and languages, and was semantically
 * blind — it flagged a synonym/paraphrase as "missing" even when the meaning was
 * preserved, and could not work for languages outside its hardcoded list (DE, IT, …).
 *
 * The LLM judges the two texts semantically in their own language, so it is
 * naturally multilingual and model-robust. When the LLM call is unavailable the
 * gate is skipped (no language-coupled fallback). Its `coverage_estimate` also
 * feeds the language-agnostic {@see SeoObservability} metrics surfaced in the UI.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO
 */
class SeoFidelityChecker
{
  /** Minimum fraction of source business facts that must survive optimization. */
  public const MIN_PRESERVATION = 0.95;

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
   * Assess source-fact fidelity of the optimized text.
   *
   * @param string $source    Original (anchor) description text.
   * @param string $optimized Generated/optimized description text.
   * @return array{available:bool,fidelity_ok:bool,preservation_score:float,total_entities:int,missing_entities:list<string>,coverage_estimate:float,missing_facts:list<string>}
   *         available=false means the LLM call failed and the caller should fall
   *         back to the deterministic benchmark.
   *         preservation_score is the explicit fraction returned by the model; >= MIN_PRESERVATION gates fidelity_ok.
   *         coverage_estimate and missing_facts are backward-compatible aliases of preservation_score and missing_entities.
   */
  public function check(string $source, string $optimized): array
  {
    // De-tag by replacing tags with a SPACE (not strip_tags, which would glue a
    // "</p><p>" boundary into "maison.Leur" — a false concatenation artifact); the
    // LLM judges the de-tagged text. Collapse the resulting whitespace.
    $source    = trim((string)preg_replace('/\s+/u', ' ', (string)preg_replace('/<[^>]+>/', ' ', $source)));
    $optimized = trim((string)preg_replace('/\s+/u', ' ', (string)preg_replace('/<[^>]+>/', ' ', $optimized)));

    if ($source === '' || $optimized === '') {
      // Nothing to compare → treat as faithful (no false regression).
      return ['available' => true, 'fidelity_ok' => true, 'preservation_score' => 1.0, 'total_entities' => 0, 'missing_entities' => [], 'coverage_estimate' => 1.0, 'missing_facts' => []];
    }

    try {
      $prompt = $this->prompts->getFidelityCheckPrompt([
        'source'    => $source,
        'optimized' => $optimized,
      ]);

      $json = $this->llm->generateStructuredResponse($prompt, [
        'maxTokens'   => 500,
        'temperature' => 0.0,
        'cache'       => false,
      ]);

      $missing = [];
      foreach (($json['missing_entities'] ?? $json['missing_facts'] ?? []) as $fact) {
        $fact = trim((string)$fact);
        if ($fact !== '') {
          $missing[] = $fact;
        }
      }

      // preservation_score: prefer the model's explicit fraction, else fall back
      // to the legacy coverage_estimate, else derive from "any missing fact".
      $preservation = null;
      if (isset($json['preservation_score'])) {
        $preservation = (float)$json['preservation_score'];
      } elseif (isset($json['coverage_estimate'])) {
        $preservation = (float)$json['coverage_estimate'];
      }
      if ($preservation === null) {
        $preservation = empty($missing) ? 1.0 : 0.0;
      }
      $preservation = max(0.0, min(1.0, $preservation));

      $totalEntities = (int)($json['total_entities'] ?? count($missing));

      // The gate: a fact-preservation fraction at or above the threshold.
      $fidelityOk = $preservation >= self::MIN_PRESERVATION;

      return [
        'available'          => true,
        'fidelity_ok'        => $fidelityOk,
        'preservation_score' => $preservation,
        'total_entities'     => $totalEntities,
        'missing_entities'   => $missing,
        // Backward-compatible aliases (SeoObservability coverage, persistBenchmarkLog).
        'coverage_estimate'  => $preservation,
        'missing_facts'      => $missing,
      ];
    } catch (\Throwable $e) {
      if ($this->debug) {
        error_log('[SeoFidelityChecker] LLM fidelity check failed, caller should fall back: ' . $e->getMessage());
      }
      return ['available' => false, 'fidelity_ok' => true, 'preservation_score' => 1.0, 'total_entities' => 0, 'missing_entities' => [], 'coverage_estimate' => 1.0, 'missing_facts' => []];
    }
  }
}
