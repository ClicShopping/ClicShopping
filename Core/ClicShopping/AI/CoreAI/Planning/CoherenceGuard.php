<?php
/**
 * CoherenceGuard - reject an analytics figure that is calculable but not trustworthy.
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Planning;

/**
 * The missing last stage of the analytics graph (fusion → comparison → VALIDATION → output).
 * Inspects a computed analytics pane — its result rows, its generated SQL and its analysis plan —
 * and returns a rejection verdict when a coherence check fails, so the figure is withheld rather
 * than rendered as if it were reliable (per-indicator rejection, arbitrated 2026-09-07). Pure,
 * agnostic, no LLM: the verdict is a boolean, stable and testable offline. The caller withholds the
 * pane and journals the reason (governance).
 *
 * Checks are HIGH-PRECISION by design — each fires only on a named column, table or plan field, so
 * an unrecognised shape is missed rather than wrongly rejected (fail-safe under a rejection regime).
 */
class CoherenceGuard
{
    /** Column-name tokens that mark a percentage-expressed margin/profit metric. */
    private const MARGIN_TOKENS = ['margin', 'profit'];
    private const PERCENT_TOKENS = ['percent', 'pct', 'rate', 'ratio'];

    /** A transaction count: revenue > 0 implies at least one order, so this at 0 is contradictory. */
    private const ORDER_TOKENS = ['order', 'transaction', 'sale'];
    private const COUNT_TOKENS = ['count', 'nb', 'number', 'qty', 'quantity', 'units'];

    /** Monetary metric tokens. */
    private const MONEY_TOKENS = ['revenue', 'amount', 'total', 'sales', 'turnover', 'value', 'sum'];

    /** Joining this explodes rows per line item; SUM over an order-level total then double-counts. */
    private const EXPLODING_JOIN = 'orders_products';
    /** Its `.value` is one row per order-total class — order-level, so MAX is right after the join. */
    private const ORDER_LEVEL_TOTAL = 'orders_total';

    /**
     * Inspect an analytics pane. Returns null when reliable, or a verdict
     * ['reason_key' => string, 'column' => ?string] naming the failed check.
     *
     * @param array $pane One analytics_response step result (carries 'results' rows).
     * @return array|null Rejection verdict, or null if the figure passes.
     */
    public static function inspectAnalyticsPane(array $pane): ?array
    {
        $rows = $pane['results'] ?? [];

        // Empty results are a HANDLED "no data" outcome elsewhere — not this guard's concern.
        if (!is_array($rows) || $rows === []) {
            return null;
        }

        // #3 Missing cost basis: a percentage margin at an impossible bound. margin% == 100 ⟺
        // cost == 0; > 100 is impossible. Absorbs 4duovicies (margin shown at 100% on zero cost).
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach ($row as $col => $val) {
                if (!is_numeric($val)) {
                    continue;
                }

                $name = strtolower((string)$col);

                if (self::hasAny($name, self::MARGIN_TOKENS)
                    && self::hasAny($name, self::PERCENT_TOKENS)
                    && (float)$val >= 100.0) {
                    return ['reason_key' => 'text_coherence_missing_cost_basis', 'column' => (string)$col];
                }
            }
        }

        // #4 Silently-empty filter: an order count at 0 in the same row as positive revenue. Revenue
        // means orders existed, so a zero order-count is a filter that matched nothing (SQL-2).
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $zeroOrderCount = false;
            $positiveMoney = false;

            foreach ($row as $col => $val) {
                if (!is_numeric($val)) {
                    continue;
                }

                $name = strtolower((string)$col);
                $f = (float)$val;

                if (self::hasAny($name, self::ORDER_TOKENS) && self::hasAny($name, self::COUNT_TOKENS) && $f === 0.0) {
                    $zeroOrderCount = true;
                }

                if (self::hasAny($name, self::MONEY_TOKENS) && $f > 0.0) {
                    $positiveMoney = true;
                }
            }

            if ($zeroOrderCount && $positiveMoney) {
                return ['reason_key' => 'text_coherence_zero_count_with_activity', 'column' => null];
            }
        }

        // Plan-side checks read the generated SQL and the analysis plan, when carried by the pane.
        $grain = self::inspectGrain((string)($pane['sql_query'] ?? ''));
        if ($grain !== null) {
            return $grain;
        }

        $plan = $pane['analysis_plan'] ?? null;
        if (is_array($plan)) {
            $periods = self::inspectPeriods($plan);
            if ($periods !== null) {
                return $periods;
            }
        }

        return null;
    }

    /**
     * #1 Grain — a SUM over an order-level total inside a join that explodes rows per line item
     * (SQL-1). One row per order-total class is repeated once per product, so SUM double-counts
     * where MAX is right. Fires only on `SUM(<orders_total alias>.value)`; anything else is missed.
     */
    private static function inspectGrain(string $sql): ?array
    {
        if ($sql === '') {
            return null;
        }

        $s = strtolower($sql);

        if (!str_contains($s, self::EXPLODING_JOIN) || !str_contains($s, self::ORDER_LEVEL_TOTAL)) {
            return null;
        }

        // Bind the alias of orders_total (prefix-agnostic), then look for SUM(<alias>.value).
        if (!preg_match('/orders_total\s+(?:as\s+)?([a-z0-9_]+)/', $s, $m)) {
            return null;
        }

        if (preg_match('/sum\s*\(\s*' . preg_quote($m[1], '/') . '\.value\b/', $s)) {
            return ['reason_key' => 'text_coherence_grain_explosion', 'column' => null];
        }

        return null;
    }

    /**
     * #2 Periods — two windows of different lengths compared as a rate of change (SQL-3). A set
     * `compare` convention (calendar YoY, comparable days) means the planner paired them on purpose;
     * only an ad-hoc pairing with no convention and unequal spans is rejected.
     */
    private static function inspectPeriods(array $plan): ?array
    {
        $periods = $plan['periods'] ?? null;

        if (!is_array($periods) || ($periods['compare'] ?? '') !== '') {
            return null;
        }

        $current = self::spanDays($periods['current'] ?? null);
        $previous = self::spanDays($periods['previous'] ?? null);

        if ($current !== null && $previous !== null && $current !== $previous) {
            return ['reason_key' => 'text_coherence_periods_mismatch', 'column' => null];
        }

        return null;
    }

    /** Inclusive day span of a `{from, to}` window (Y-m-d), or null when bounds are unusable. */
    private static function spanDays(mixed $window): ?int
    {
        if (!is_array($window) || !is_string($window['from'] ?? null) || !is_string($window['to'] ?? null)) {
            return null;
        }

        try {
            $from = new \DateTimeImmutable($window['from']);
            $to = new \DateTimeImmutable($window['to']);
        } catch (\Throwable) {
            return null;
        }

        return (int)$from->diff($to)->days + 1;
    }

    private static function hasAny(string $haystack, array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (str_contains($haystack, $token)) {
                return true;
            }
        }

        return false;
    }
}
