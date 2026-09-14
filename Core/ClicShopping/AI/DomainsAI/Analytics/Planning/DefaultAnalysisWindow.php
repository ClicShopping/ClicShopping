<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\AI\DomainsAI\Analytics\Planning;

use ClicShopping\AI\Config\TechnicalDefaults;

/**
 * DefaultAnalysisWindow
 *
 * The window an analytic question is measured over when it states none of its own.
 *
 * `CLICSHOPPING_APP_CHATGPT_RA_DEFAULT_ANALYSIS_DAYS` is the whole policy: above zero the
 * question is answered over that many days ending today, and the answer NAMES the window it
 * used; at zero the period is asked for instead, which is the behaviour that predates this.
 * Naming the window is not decoration — an unannounced default window is what once compared a
 * complete year against seven months and published the variation as fact.
 *
 * @package ClicShopping\AI\DomainsAI\Analytics\Planning
 */
final class DefaultAnalysisWindow
{
  public const CONSTANT = 'CLICSHOPPING_APP_CHATGPT_RA_DEFAULT_ANALYSIS_DAYS';

  /**
   * Configured span, in days. Zero disables the default entirely.
   *
   * @return float Span in days, never negative
   */
  public static function days(): float
  {
    return max(0.0, TechnicalDefaults::float(self::CONSTANT));
  }

  /**
   * Concrete bounds of the default window, or null when no default is configured.
   *
   * A span below one day has no Y-m-d homologue: it collapses to today alone.
   *
   * @param \DateTimeImmutable|null $observedAt Observation date; defaults to now. Tests only.
   * @return array{from: string, to: string}|null
   */
  public static function window(?\DateTimeImmutable $observedAt = null): ?array
  {
    $days = self::days();

    if ($days <= 0.0) {
      return null;
    }

    $today = ($observedAt ?? new \DateTimeImmutable())->setTime(0, 0);

    return [
      'from' => $today->modify('-' . (int)floor($days) . ' days')->format('Y-m-d'),
      'to' => $today->format('Y-m-d'),
    ];
  }

  /**
   * A missing period stops being an ambiguity once a default window answers it.
   *
   * Applied to the detector's verdict rather than to each gate that reads it: the same verdict
   * feeds the analytics clarification gate and the one that asks before a compound question is
   * cut, and a default honoured by only one of them would still be asked for by the other.
   * Every OTHER ambiguity type is left untouched — this defaults a period, nothing else.
   *
   * @param array $analysis Verdict from AmbiguousQueryDetector::detectAmbiguity()
   * @return array The verdict, its time ambiguity demoted to `proceed` when a default exists
   */
  public static function demoteTimeAmbiguity(array $analysis): array
  {
    if (($analysis['ambiguity_type'] ?? null) !== 'time' || !($analysis['is_ambiguous'] ?? false)) {
      return $analysis;
    }

    if (self::days() <= 0.0) {
      return $analysis;
    }

    return [
      ...$analysis,
      'is_ambiguous' => false,
      'recommendation' => 'proceed',
      'period_defaulted' => true,
      'reasoning' => 'time ambiguity answered by the configured default analysis window',
    ];
  }
}
