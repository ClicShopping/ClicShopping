<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Faq;

use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Prompts\ContentGenerationPrompts;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Services\LLMServiceWrapper;

/**
 * SeoFaqFactChecker
 *
 * Agentic (Pure LLM Mode) precision gate for a single generated FAQ Q/A pair.
 *
 * The cosine {@see \ClicShopping\AI\Security\Validation\AnswerGroundingVerifier}
 * used by {@see SeoFaqPipeline} measures TOPICAL similarity: it rejects an answer
 * that drifts off-subject, but it CANNOT tell a fabricated-yet-plausible fact
 * (an invented warranty duration, dimension, weight or variant) from a real one,
 * because such an answer still shares the product's vocabulary and scores high.
 * That is precisely the failure mode reported on real catalogues (warranty and
 * dimensions "invented").
 *
 * This checker closes that gap the way {@see \ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoFidelityChecker}
 * closes it for descriptions: it asks the LLM to judge, in its own language,
 * whether every concrete claim in the answer is entailed by the product data
 * (source of truth). It is a PRECISION gate (are the stated facts true?), the
 * mirror of the description RECALL gate (are the source facts preserved?).
 *
 * Fail-open by design: when the LLM call is unavailable the answer is treated as
 * supported (no language-coupled fallback, consistent with SeoFidelityChecker) —
 * the cosine grounding still applies, so a fabricated off-topic answer is caught,
 * and we never fail the whole FAQ action just because the auditor was offline.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Faq
 */
class SeoFaqFactChecker
{
  /**
   * Minimum fraction of an answer's factual claims that must be supported by the
   * product data for the Q/A pair to be kept. The auditor also returns an
   * explicit `supported` boolean; a pair is kept only when BOTH agree, so a
   * single unsupported hard fact (a fabricated warranty) drops the pair.
   */
  public const MIN_SUPPORT = 1.0;

  private LLMServiceWrapper $llm;
  private ContentGenerationPrompts $prompts;
  private bool $debug;

  public function __construct(string $languageCode, bool $debug = false)
  {
    $this->debug   = $debug;
    $this->llm     = new LLMServiceWrapper($debug);
    $this->prompts = new ContentGenerationPrompts($languageCode);
  }

  /**
   * Audit one FAQ answer against the product source of truth.
   *
   * @param string $source   Factual product data (name, brand, model, description…).
   * @param string $question The FAQ question.
   * @param string $answer   The FAQ answer to audit.
   * @return array{available:bool, supported:bool, support_score:float, unsupported_claims:list<string>}
   *         available=false means the LLM call failed and the caller should keep
   *         the pair (fail-open) — the cosine grounding remains the safety net.
   */
  public function verify(string $source, string $question, string $answer): array
  {
    $source   = trim($source);
    $question = trim($question);
    $answer   = trim($answer);

    if ($source === '' || $answer === '') {
      // Nothing to audit against, or nothing to audit → do not fabricate a rejection.
      return ['available' => true, 'supported' => true, 'support_score' => 1.0, 'unsupported_claims' => []];
    }

    try {
      $prompt = $this->prompts->getFaqFidelityCheckPrompt([
        'source'   => $source,
        'question' => $question,
        'answer'   => $answer,
      ]);

      $json = $this->llm->generateStructuredResponse($prompt, [
        'maxTokens'   => 400,
        'temperature' => 0.0,
        'cache'       => false,
      ]);

      $unsupported = [];
      foreach (($json['unsupported_claims'] ?? []) as $claim) {
        $claim = trim((string)$claim);
        if ($claim !== '') {
          $unsupported[] = $claim;
        }
      }

      // support_score: prefer the model's explicit fraction, else derive it from
      // the presence of unsupported claims.
      $score = isset($json['support_score'])
        ? (float)$json['support_score']
        : (empty($unsupported) ? 1.0 : 0.0);
      $score = max(0.0, min(1.0, $score));

      // The model's explicit boolean is authoritative; when absent, derive it.
      $supportedFlag = array_key_exists('supported', $json)
        ? (bool)$json['supported']
        : ($score >= self::MIN_SUPPORT && empty($unsupported));

      // Keep only when the model says supported AND lists no unsupported claim AND
      // the fraction clears the floor — any single fabricated hard fact drops it.
      $supported = $supportedFlag && empty($unsupported) && $score >= self::MIN_SUPPORT;

      return [
        'available'          => true,
        'supported'          => $supported,
        'support_score'      => $score,
        'unsupported_claims' => $unsupported,
      ];
    } catch (\Throwable $e) {
      if ($this->debug) {
        error_log('[SeoFaqFactChecker] LLM fact audit unavailable, keeping pair (fail-open): ' . $e->getMessage());
      }
      return ['available' => false, 'supported' => true, 'support_score' => 1.0, 'unsupported_claims' => []];
    }
  }
}
