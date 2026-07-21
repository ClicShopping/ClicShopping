<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Validator;

/**
 * SqlShapeHeuristics
 *
 * Lightweight, deterministic SQL-shape predicates shared by the SQL validators to
 * decide whether a LIMIT / WHERE clause is actually EXPECTED for a given query shape.
 * Analytics (OLAP) queries are frequently aggregations for which the OLTP "always
 * paginate / always filter" heuristics are false positives:
 *  - a scalar aggregate (COUNT/SUM/AVG/MIN/MAX with no GROUP BY) returns a single row,
 *    so a LIMIT is pointless and a WHERE is optional;
 *  - a GROUP BY without ORDER BY produces grouped rows in no meaningful order, so a
 *    LIMIT would truncate arbitrary groups.
 * Only plain row-listing SELECTs and top-N queries (GROUP BY + ORDER BY) genuinely
 * expect a LIMIT.
 *
 * Input is expected to be the UPPER-CASED SQL (as the validators already normalise it).
 */
trait SqlShapeHeuristics
{
    /**
     * @param string $sqlUpper Upper-cased SQL
     * @return bool True if the query is a scalar aggregate (aggregate function, no GROUP BY)
     */
    private function isScalarAggregate(string $sqlUpper): bool
    {
        return !$this->sqlHasGroupBy($sqlUpper)
            && preg_match('/\b(COUNT|SUM|AVG|MIN|MAX)\s*\(/', $sqlUpper) === 1;
    }

    /**
     * @param string $sqlUpper Upper-cased SQL
     * @return bool True if the query contains a GROUP BY clause
     */
    private function sqlHasGroupBy(string $sqlUpper): bool
    {
        return str_contains($sqlUpper, 'GROUP BY');
    }

    /**
     * @param string $sqlUpper Upper-cased SQL
     * @return bool True if the query contains an ORDER BY clause
     */
    private function sqlHasOrderBy(string $sqlUpper): bool
    {
        return str_contains($sqlUpper, 'ORDER BY');
    }

    /**
     * Whether a LIMIT clause is expected for this query shape (its absence is a real issue).
     *
     * @param string $sqlUpper Upper-cased SQL
     * @return bool False for scalar aggregates and for GROUP BY without ORDER BY
     */
    private function limitExpectedForShape(string $sqlUpper): bool
    {
        if ($this->isScalarAggregate($sqlUpper)) {
            return false; // single-row result
        }

        if ($this->sqlHasGroupBy($sqlUpper) && !$this->sqlHasOrderBy($sqlUpper)) {
            return false; // grouped rows; a LIMIT would truncate arbitrary groups
        }

        return true; // plain SELECT, or top-N (GROUP BY + ORDER BY)
    }

    /**
     * Whether a WHERE clause is expected for this query shape (its absence is a real issue).
     *
     * @param string $sqlUpper Upper-cased SQL
     * @return bool False for a full-table scalar aggregate, true otherwise
     */
    private function whereExpectedForShape(string $sqlUpper): bool
    {
        return !$this->isScalarAggregate($sqlUpper);
    }
}
