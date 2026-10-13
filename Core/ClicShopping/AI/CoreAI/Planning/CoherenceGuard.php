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
    /** A change between two figures: a margin that grows by 150% is not a margin rate of 150%. */
    private const VARIATION_TOKENS = ['variation', 'change', 'growth', 'delta', 'diff', 'evolution', 'yoy', 'increase', 'decrease'];

    /** A count of the domain's facts (its axis fact grain): money > 0 with it at 0 is contradictory. */
    private const COUNT_TOKENS = ['count', 'nb', 'number', 'qty', 'quantity', 'units'];

    /** Monetary metric tokens. */
    private const MONEY_TOKENS = ['revenue', 'amount', 'total', 'sales', 'turnover', 'value', 'sum'];

    /** Dimension values kept in a dropped row's label: enough to name a cell, not to retype the row. */
    private const LABEL_MAX_PARTS = 2;

    /** Column-name tokens of a numeric period key, used to name a row that carries no text. */
    private const PERIOD_TOKENS = ['year', 'quarter', 'month', 'week', 'day'];

    /** Label of a row with no dimension value: the caller renders it, the guard knows no wording. */
    public const UNLABELLED_ROW = '';
    
    /**
     * Blank the margin of the rows that have no cost basis, and NAME them.
     *
     * A breakdown mixes what the catalogue prices and what it does not: one category with no cost
     * recorded puts a 100% line next to lines that are true. The unit of rejection is the margin
     * CELL of that row: its revenue and other figures are measured and stay. The caller says
     * which margins went, and why.
     *
     * Runs BEFORE the results are interpreted. Filtering them afterwards leaves the prose quoting
     * the very figure the guard just withheld.
     *
     * Every row at the bound means there is nothing to keep: the rows come back untouched and
     * inspectAnalyticsPane() withholds the pane, which is the right verdict then.
     *
     * @param array $rows Result rows of one analytics pane
     * @return array{rows: array, withheld: array<int, string>, column: ?string, share: ?int} Rows
     *         with the offending margins set to null, the label of each such row (empty when none),
     *         and the percentage of the pane's revenue those rows carry (null when no revenue column)
     */
    public static function withholdMissingCostBasisRows(array $rows): array
    {
        $kept = [];
        $blanked = [];
        $withheld = [];
        $column = null;
        $revenueColumn = self::revenueColumn($rows);
        $withheldRevenue = 0.0;
        $totalRevenue = 0.0;

        foreach ($rows as $key => $row) {
            $offending = is_array($row) ? self::marginWithoutCostBasis($row) : null;
            $revenue = $revenueColumn !== null && is_numeric($row[$revenueColumn] ?? null) ? (float)$row[$revenueColumn] : 0.0;
            $totalRevenue += $revenue;

            if ($offending === null) {
                $kept[$key] = $row;
                continue;
            }

            $column ??= $offending;
            $withheld[] = self::rowLabel($row);
            $withheldRevenue += $revenue;
            // array_replace, never +: the union would move the blanked columns to the front of the table.
            $blanked[$key] = array_replace($row, array_map(static fn($val) => null, array_intersect_key($row, array_flip(self::marginColumns($row)))));
        }

        if ($withheld === [] || $kept === []) {
            return ['rows' => $rows, 'withheld' => [], 'column' => null, 'share' => null];
        }

        // Row order is the query's: keep it, only the offending margins change.
        $kept = array_replace($rows, $blanked);

        // The weight of what went is the fact a margin reader needs most: named rows alone hide it.
        // Whole percent: no decimal separator to localise.
        $share = $totalRevenue > 0.0 ? (int)round($withheldRevenue / $totalRevenue * 100) : null;

        return ['rows' => $kept, 'withheld' => $withheld, 'column' => $column, 'share' => $share];
    }

    /**
     * Blank the margin of the rows with NO net sale, and NAME them: sold then refunded, a row nets
     * to 0 revenue and its 0 margin is not a weak margin, it is no margin at all.
     *
     * ponytail: exactly 0 only - a negative net (refunds over sales in the row) keeps its margin.
     *
     * @param array $rows Result rows of one analytics pane
     * @return array{rows: array, withheld: array<int, string>} Rows with those margins set to null,
     *         and the label of each such row (empty when none)
     */
    public static function withholdNoNetSaleRows(array $rows): array
    {
        $revenueColumn = self::revenueColumn($rows);
        $withheld = [];

        if ($revenueColumn === null) {
            return ['rows' => $rows, 'withheld' => []];
        }

        foreach ($rows as $key => $row) {
            $margins = is_array($row) ? self::marginColumns($row) : [];

            if ($margins === [] || !is_numeric($row[$revenueColumn] ?? null) || abs((float)$row[$revenueColumn]) >= 0.005
                || array_all($margins, static fn($col): bool => $row[$col] === null)) {
                continue;
            }

            $withheld[] = self::rowLabel($row);
            $rows[$key] = array_replace($row, array_map(static fn($val) => null, array_intersect_key($row, array_flip($margins))));
        }

        return ['rows' => $rows, 'withheld' => $withheld];
    }

    /**
     * A margin expressed as a rate of sales - bounded by 100 - and not the variation of a margin.
     *
     * @param string $name Lower-cased column name
     * @return bool
     */
    private static function isMarginRate(string $name): bool
    {
        return self::hasAny($name, self::MARGIN_TOKENS)
            && self::hasAny($name, self::PERCENT_TOKENS)
            && !self::hasAny($name, self::VARIATION_TOKENS);
    }

    /**
     * The pane's revenue column, or null: a sales figure, never a margin, a cost or an uncovered part.
     *
     * @param array $rows Result rows
     * @return string|null Column name
     */
    private static function revenueColumn(array $rows): ?string
    {
        $first = reset($rows);

        if (!is_array($first)) {
            return null;
        }

        foreach (array_keys($first) as $col) {
            $name = strtolower((string)$col);

            if (self::hasAny($name, ['revenue', 'sales', 'turnover'])
                && !self::hasAny($name, [...self::MARGIN_TOKENS, 'cost', 'without', 'uncovered'])) {
                return (string)$col;
            }
        }

        return null;
    }

    /**
     * Name of the margin column that has no cost basis, or null: a percentage at the impossible
     * bound, or every margin column NULL (the lines without a cost were left out of the sum).
     *
     * @param array $row One result row
     * @return string|null Offending column name
     */
    private static function marginWithoutCostBasis(array $row): ?string
    {
        $marginColumns = self::marginColumns($row);

        // A refund-only row keeps its amount and loses only its rate: it is not caught here.
        if ($marginColumns !== [] && array_all($marginColumns, static fn($col): bool => $row[$col] === null)) {
            return (string)reset($marginColumns);
        }

        foreach ($row as $col => $val) {
            if (!is_numeric($val)) {
                continue;
            }

            $name = strtolower((string)$col);

            if (self::isMarginRate($name) && (float)$val >= 100.0) {
                return (string)$col;
            }
        }

        return null;
    }

    /**
     * The row's margin columns: amount, rate and their variations, current and previous.
     *
     * @param array $row One result row
     * @return array<int, int|string> Column names
     */
    private static function marginColumns(array $row): array
    {
        return array_values(array_filter(
            array_keys($row),
            static fn($col): bool => self::hasAny(strtolower((string)$col), self::MARGIN_TOKENS)
        ));
    }

    /**
     * What to call a dropped row: its non-numeric values, which are the dimensions it is broken
     * down by. Naming only the FIRST one reads as if the whole dimension went - "no margin for
     * Table linens" while four of its quarters are shown right below. A row carrying no text at
     * all is still named, never dropped unnamed.
     *
     * @param mixed $row One result row
     * @return string Label
     */
    private static function rowLabel(mixed $row): string
    {
        $parts = [];

        if (is_array($row)) {
            foreach ($row as $val) {
                if (is_string($val) && trim($val) !== '' && !is_numeric($val)) {
                    $parts[] = trim($val);
                }

                if (count($parts) === self::LABEL_MAX_PARTS) {
                    break;
                }
            }
        }

        // A row keyed by an identifier alone (an order) is named by it; a figure never names a row.
        foreach ($parts === [] && is_array($row) ? $row : [] as $col => $val) {
            if (str_ends_with(strtolower((string)$col), 'id') && is_scalar($val) && (string)$val !== '') {
                $parts[] = '#' . $val;
                break;
            }
        }

        // A separator, never a space: "Bar Wine cooler" reads as one name, "Bar / Wine cooler" as two.
        if ($parts !== []) {
            return implode(' / ', $parts);
        }

        // A row keyed by numeric period columns alone (year, month) is named by them: 2026-01.
        foreach (is_array($row) ? $row : [] as $col => $val) {
            if (is_numeric($val) && self::hasAny(strtolower((string)$col), self::PERIOD_TOKENS)) {
                $parts[] = $parts === [] ? (string)$val : str_pad((string)$val, 2, '0', STR_PAD_LEFT);
            }
        }

        return $parts === [] ? self::UNLABELLED_ROW : implode('-', $parts);
    }

    /**
     * Inspect an analytics pane. Returns null when reliable, or a verdict
     * ['reason_key' => string, 'column' => ?string] naming the failed check.
     *
     * @param array $pane One analytics_response step result (carries 'results' rows).
     * @param array $axis The active domain's event axis (getEventAxis()['axis']); without it the
     *        fact-count and grain checks stay silent.
     * @return array|null Rejection verdict, or null if the figure passes.
     */
    public static function inspectAnalyticsPane(array $pane, array $axis = []): ?array
    {
        $rows = $pane['results'] ?? [];

        // Empty results are a HANDLED "no data" outcome elsewhere — not this guard's concern.
        if (!is_array($rows) || $rows === []) {
            return null;
        }

        // #3 Missing cost basis: a percentage margin at an impossible bound. margin% == 100 ⟺
        // cost == 0; > 100 is impossible. Reached only when EVERY row is at that bound — a pane
        // still holding one computable line is pruned row by row upstream, never withheld whole.
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach ($row as $col => $val) {
                if (!is_numeric($val)) {
                    continue;
                }

                $name = strtolower((string)$col);

                if (self::isMarginRate($name) && (float)$val >= 100.0) {
                    return ['reason_key' => 'text_coherence_missing_cost_basis', 'column' => (string)$col];
                }
            }
        }

        $factTokens = array_values(array_filter([(string)($axis['fact']['grain'] ?? ''), (string)($axis['fact']['table'] ?? '')]));
        $derived = [];

        foreach ((array)($pane['derived_columns'] ?? []) as $col) {
            $derived[strtolower((string)$col)] = true;
        }

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

                if (isset($derived[$name])) {
                    continue;
                }

                if ($factTokens !== [] && self::hasAny($name, $factTokens) && self::hasAny($name, self::COUNT_TOKENS) && $f === 0.0) {
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
        $grain = self::inspectGrain((string)($pane['sql_query'] ?? ''), $axis);
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
     * #1 Grain — a SUM over a fact-level total inside a join on the fact's lines (SQL-1): the total
     * repeats once per line, so SUM double-counts where MAX is right. Both tables are declared by
     * the axis (`lines.table`, `fact_totals`); fires only on `SUM(<total alias>.<column>)`.
     */
    private static function inspectGrain(string $sql, array $axis): ?array
    {
        $lines = strtolower((string)($axis['lines']['table'] ?? ''));
        $s = strtolower($sql);

        if ($s === '' || $lines === '' || !str_contains($s, $lines)) {
            return null;
        }

        foreach ((array)($axis['fact_totals'] ?? []) as $total) {
            $table = strtolower((string)($total['table'] ?? ''));
            $column = strtolower((string)($total['column'] ?? ''));

            // Bind the table's alias (prefix-agnostic); an unaliased table is read by its own name.
            if ($table === '' || $column === '' || !preg_match('/' . preg_quote($table, '/') . '\s+(?:as\s+)?([a-z0-9_]+)/', $s, $m)) {
                continue;
            }

            $ref = '(?:' . preg_quote($m[1], '/') . '|[a-z0-9_]*' . preg_quote($table, '/') . ')';

            if (preg_match('/sum\s*\(\s*' . $ref . '\.' . preg_quote($column, '/') . '\b/', $s)) {
                return ['reason_key' => 'text_coherence_grain_explosion', 'column' => null];
            }
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
