<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Patterns;

/**
 * EncryptedColumnPattern — schema-level guard for GDPR-encrypted columns in SQL.
 *
 * Unlike the (deprecated, intent-detection) patterns in this directory, this is NOT an LLM-output
 * heuristic: it keys on the DATABASE SCHEMA (which columns are encrypted) and standard SQL syntax
 * (GROUP BY). Those are identical across LLM providers, so this guard is portable multi-LLM
 * (OpenAI, Phi-4, …) — it does not break when the model changes.
 *
 * Why it is needed: columns like customers_name / customers_email_address are encrypted at rest
 * with a NON-deterministic cipher (a different ciphertext per row for the same logical value).
 * Grouping by them shatters an aggregation into one group per row (one row per order instead of
 * one per customer), which silently produces wrong — even inverted — business results. The LLM is
 * also told this in rag_analytics_agent RULE -2, but does not always comply; this deterministic
 * guard enforces it before execution.
 */
final class EncryptedColumnPattern
{
  /**
   * Columns encrypted with a non-deterministic cipher → never groupable / equality-filterable.
   * @var string[]
   */
  public const ENCRYPTED_COLUMNS = ['customers_name', 'customers_email_address'];

  /**
   * True if the GROUP BY clause references an encrypted column.
   */
  public static function isInGroupBy(string $sql): bool
  {
    return self::removeFromGroupBy($sql) !== $sql;
  }

  /**
   * Removes encrypted columns from the GROUP BY clause (only). The column is left untouched in
   * SELECT — the server's sql_mode does not enforce ONLY_FULL_GROUP_BY, so a bare non-aggregated
   * column stays valid and returns one representative value per group. Never produces an empty
   * GROUP BY (returns the SQL unchanged in that edge case).
   *
   * @return string The SQL with encrypted columns stripped from GROUP BY (or unchanged).
   */
  public static function removeFromGroupBy(string $sql): string
  {
    // Isolate the GROUP BY clause (up to HAVING / ORDER BY / LIMIT or end of query).
    if (!preg_match('/\bGROUP\s+BY\b/i', $sql, $m, PREG_OFFSET_CAPTURE)) {
      return $sql;
    }
    $gbStart = $m[0][1];
    $after = substr($sql, $gbStart);

    $boundaryLen = \strlen($after);
    if (preg_match('/\b(HAVING|ORDER\s+BY|LIMIT)\b/i', $after, $bm, PREG_OFFSET_CAPTURE)) {
      $boundaryLen = $bm[0][1];
    }
    $clause = substr($after, 0, $boundaryLen);
    $original = $clause;

    foreach (self::ENCRYPTED_COLUMNS as $col) {
      // Remove ", alias.col" / ", col" (middle or trailing item).
      $clause = preg_replace('/\s*,\s*(?:[A-Za-z_][A-Za-z0-9_]*\.)?' . $col . '\b/i', '', $clause);
      // Remove "alias.col ," when it is the first item right after GROUP BY.
      $clause = preg_replace('/(\bGROUP\s+BY\s+)(?:[A-Za-z_][A-Za-z0-9_]*\.)?' . $col . '\s*,\s*/i', '$1', $clause);
    }

    if ($clause === $original) {
      return $sql;
    }

    // Safety: never produce an empty GROUP BY (invalid SQL).
    if (preg_match('/\bGROUP\s+BY\s*$/i', rtrim($clause))) {
      return $sql;
    }

    return substr($sql, 0, $gbStart) . $clause . substr($after, $boundaryLen);
  }
}
