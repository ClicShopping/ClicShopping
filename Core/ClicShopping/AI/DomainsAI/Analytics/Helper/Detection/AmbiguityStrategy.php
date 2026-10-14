<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Helper\Detection;

/**
 * Class AmbiguityStrategy
 *
 * Pure, IO-free description of the ambiguity-detection strategies under evaluation,
 * and the deterministic decisions that depend only on a strategy mode (no LLM calls).
 *
 * Modes:
 *  - c0: regex pre-filter primary, then LLM at default temperature (current production)
 *  - c1: no regex, LLM at temperature 0.0
 *  - c2: LLM at temperature 0.0 primary, regex pre-filter only as fallback
 *  - c3: LLM at temperature 0.0, then an LLM critic validates/regenerates the verdict
 */
class AmbiguityStrategy
{
  public const string C0 = 'c0';
  public const string C1 = 'c1';
  public const string C2 = 'c2';
  public const string C3 = 'c3';

  /** Confidence below which a c2 LLM verdict is considered unreliable and regex takes over. */
  public const float C2_FALLBACK_CONFIDENCE = 0.5;

  /** @return list<string> */
  public static function modes(): array
  {
    return [self::C0, self::C1, self::C2, self::C3];
  }

  public static function isValid(string $mode): bool
  {
    return \in_array($mode, self::modes(), true);
  }

  /** True when the regex pre-filter runs BEFORE the LLM and can short-circuit it. */
  public static function usesPrefilterPrimary(string $mode): bool
  {
    return $mode === self::C0;
  }

  /** True when the LLM call must run at temperature 0.0 through the Gpt facade. */
  public static function usesDeterministicLlm(string $mode): bool
  {
    return \in_array($mode, [self::C1, self::C2, self::C3], true);
  }

  /** True when an LLM critic pass must validate/regenerate the verdict. */
  public static function usesCritic(string $mode): bool
  {
    return $mode === self::C3;
  }

  /**
   * c2 fallback decision: regex takes over only when the LLM verdict is unusable.
   *
   * @param bool $llmParseFailed True if the LLM JSON could not be parsed.
   * @param float $llmConfidence Confidence reported by the LLM verdict (0.0–1.0).
   */
  public static function shouldFallbackToRegex(bool $llmParseFailed, float $llmConfidence): bool
  {
    return $llmParseFailed || $llmConfidence < self::C2_FALLBACK_CONFIDENCE;
  }
}
