<?php
/**
 * ExecutionModeMatrix - deterministic routing of an analytics question to an execution mode.
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Planning;

/**
 * Maps the boolean SHAPE of a question to an execution mode, replacing the ambiguous 1→5
 * complexity score (kept only as a logged indicator). The LLM OBSERVES the booleans; this engine
 * DECIDES — no LLM call here, so the decision is stable and testable offline.
 *
 * Booleans come from the unified analyzer: multiple_metrics, multiple_grains, has_ranking, and
 * has_comparison (the latter reuses the existing time_constraint === 'comparison'). has_aggregations
 * is measured for audit but does not shift the mode (the matrix top row collapses onto the row
 * below it in the conservative mapping), so it is not an input here.
 */
class ExecutionModeMatrix
{
    /** One execution: a single query, possibly a CTE (the matrix "SINGLE / CTE" cells). */
    public const MODE_SINGLE = 'single';

    /** Split into independent analytics sub-queries under the standard ceiling. */
    public const MODE_DECOMPOSE = 'decompose';

    /** Multi-query plan: decomposition is mandatory and the ceiling is raised. */
    public const MODE_MULTI_QUERY = 'multi_query';

    /**
     * Decide the execution mode from the question's boolean characteristics.
     *
     * Reproduces the arbitrated decision matrix (nature × grains). "SINGLE / CTE" cells map to
     * SINGLE (one execution either way); the top "selon validation" cell maps conservatively to
     * DECOMPOSE.
     *
     * @param bool $multipleMetrics Two or more distinct measures asked
     * @param bool $multipleGrains  Answering needs two or more different row levels
     * @param bool $hasComparison   Two or more time periods compared
     * @param bool $hasRanking      A top/bottom N or explicit ordering by a measure
     * @return string One of MODE_SINGLE, MODE_DECOMPOSE, MODE_MULTI_QUERY
     */
    public static function decide(
        bool $multipleMetrics,
        bool $multipleGrains,
        bool $hasComparison,
        bool $hasRanking
    ): string {
        // Ranking AND comparison is the heaviest nature: same grain still decomposes, mixed grains
        // force a multi-query plan.
        if ($hasRanking && $hasComparison) {
            return $multipleGrains ? self::MODE_MULTI_QUERY : self::MODE_DECOMPOSE;
        }

        // A single heavier signal (comparison, ranking, or several metrics) only forces a split when
        // the grains differ; same-grain stays one execution.
        if ($hasComparison || $hasRanking || $multipleMetrics) {
            return $multipleGrains ? self::MODE_DECOMPOSE : self::MODE_SINGLE;
        }

        // One metric, one grain, no comparison or ranking.
        return self::MODE_SINGLE;
    }
}
