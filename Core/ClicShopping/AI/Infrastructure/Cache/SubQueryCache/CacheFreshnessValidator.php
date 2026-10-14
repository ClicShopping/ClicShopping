<?php
declare(strict_types=1);

/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Cache\SubQueryCache;

use ClicShopping\AI\Infrastructure\Cache\Helper\SQLTableParser;
use ClicShopping\AI\RegistryAI\PromptPlaceholderRegistry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * CacheFreshnessValidator - refuses a cached answer whose source tables changed since it was built.
 *
 * TTL alone only bounds staleness; it cannot notice that the very data behind an answer moved.
 * Cache::invalidateCacheByTable() was written for that but never called once, because no write
 * path knows it should announce itself. This validator inverts the direction: instead of asking
 * every writer to declare, it asks the schema at READ time. That covers every writer — admin,
 * cron, import, MCP, direct SQL — without touching a single write path.
 *
 * Freshness rule, both clauses required:
 *  1. the entry was created after the last server start (InnoDB keeps UPDATE_TIME in memory and
 *     loses it on restart, so an older entry cannot be PROVEN fresh — it is refused, fail-closed);
 *  2. no table referenced by the cached SQL, AND no table feeding a dynamic prompt placeholder,
 *     has an UPDATE_TIME newer than the entry. The second set matters because the generated SQL
 *     never names it: a placeholder renders a reference catalogue that teaches the model what a
 *     coded value MEANS, and the query filters that code on the fact table without ever joining
 *     the catalogue. Edit the catalogue and the SQL is unchanged while its meaning is not.
 *     Providers declare their own tables through
 *     PromptPlaceholderProviderInterface::getSourceTables(); Core collects, it never names one.
 *
 * Every comparison is evaluated by the database, never in PHP: `time_zone = "UTC"` in global.php
 * against a server-local NOW() is exactly what makes cache_age read −7200s elsewhere in this
 * codebase, and a freshness check that inherits that bug would be worse than no check at all.
 *
 * @package ClicShopping\AI\Infrastructure\Cache\SubQueryCache
 * @since 2026-07-27
 */
class CacheFreshnessValidator
{
  private mixed $db;
  private bool $debug;

  /**
   * @var string|null Memoized server start time, as a database-side datetime.
   */
  private ?string $serverStart = null;

  private bool $serverStartResolved = false;

  /**
   * @var array<int, string>|null Memoized prefixed tables feeding the dynamic placeholders.
   */
  private ?array $placeholderTables = null;

  public function __construct(bool $debug = false)
  {
    $this->debug = $debug;

    try {
      $this->db = Registry::get('Db');
    } catch (\Exception $e) {
      $this->db = null;
    }
  }

  /**
   * Decide whether a cached entry may still be served.
   *
   * @param string|null $sqlQuery SQL the entry was built from
   * @param string|null $createdAt Entry creation time, as stored by the database
   * @return bool True when the entry may be served
   */
  public function isFresh(?string $sqlQuery, ?string $createdAt): bool
  {
    // Nothing to verify against: the TTL remains the only guard, as before.
    if ($this->db === null || $sqlQuery === null || $createdAt === null || trim($sqlQuery) === '') {
      return true;
    }

    $tables = array_values(array_unique(array_merge(
      SQLTableParser::extractTables($sqlQuery),
      $this->placeholderTables()
    )));

    if ($tables === []) {
      return true;
    }

    $serverStart = $this->serverStart();

    if ($serverStart !== null && $createdAt < $serverStart) {
      $this->debugLog("stale: entry predates the last server start ({$createdAt} < {$serverStart})");

      return false;
    }

    $changed = $this->tablesChangedSince($tables, $createdAt);

    if ($changed > 0) {
      $this->debugLog("stale: {$changed} source table(s) written since {$createdAt}");

      return false;
    }

    return true;
  }

  /**
   * Same rule, for backends that store an age instead of a datetime (Redis/Memcached).
   *
   * A duration is timezone-invariant, so it is resolved as NOW() - INTERVAL age SECOND rather
   * than FROM_UNIXTIME(), which would depend on the session time_zone.
   *
   * @param string|null $sqlQuery SQL the entry was built from
   * @param int $ageSeconds How long ago the entry was created
   * @return bool True when the entry may be served
   */
  public function isFreshSinceAge(?string $sqlQuery, int $ageSeconds): bool
  {
    if ($this->db === null || $sqlQuery === null || trim($sqlQuery) === '' || $ageSeconds < 0) {
      return true;
    }

    try {
      $query = $this->db->prepare("SELECT NOW() - INTERVAL :age SECOND AS built_at");
      $query->bindInt(':age', $ageSeconds);
      $query->execute();

      if ($query->fetch()) {
        return $this->isFresh($sqlQuery, $query->value('built_at'));
      }
    } catch (\Exception $e) {
      $this->debugLog('age resolution failed, entry kept: ' . $e->getMessage());
    }

    return true;
  }

  /**
   * Count the referenced tables written after the given moment.
   *
   * @param array<int, string> $tables Table names extracted from the cached SQL
   * @param string $createdAt Entry creation time, database-side
   * @return int Number of tables changed since
   */
  /**
   * Tables feeding a dynamic prompt placeholder, prefixed like the ones the parser returns.
   *
   * Checked on EVERY entry, not only when the cached SQL names them: that is the whole point.
   * A reference catalogue rewritten by the merchant changes what a coded value MEANS while the
   * SQL keeps referencing the fact table alone — invisible to the table-of-the-query rule.
   *
   * @return array<int, string> Prefixed table names, empty when no provider declares one
   */
  private function placeholderTables(): array
  {
    if ($this->placeholderTables !== null) {
      return $this->placeholderTables;
    }

    $this->placeholderTables = [];

    try {
      $prefix = (string)CLICSHOPPING::getConfig('db_table_prefix');

      foreach (PromptPlaceholderRegistry::getInstance()->getSourceTables() as $table) {
        $this->placeholderTables[] = $prefix . $table;
      }
    } catch (\Throwable $e) {
      // Fail OPEN, like the introspection below: no registry must not disable the cache.
      $this->debugLog('placeholder tables unavailable, entry kept: ' . $e->getMessage());
    }

    return $this->placeholderTables;
  }

  private function tablesChangedSince(array $tables, string $createdAt): int
  {
    $placeholders = [];
    $bindings = [];

    // Never name a placeholder `table_*`: autoPrefixTables would rewrite it into a real name.
    foreach (array_values($tables) as $index => $table) {
      $placeholders[] = ':src' . $index;
      $bindings[':src' . $index] = $table;
    }

    try {
      $query = $this->db->prepare("
        SELECT COUNT(*) AS changed_tables
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME IN (" . implode(', ', $placeholders) . ")
        AND UPDATE_TIME IS NOT NULL
        AND UPDATE_TIME > :built_at
      ");

      foreach ($bindings as $placeholder => $table) {
        $query->bindValue($placeholder, $table);
      }

      $query->bindValue(':built_at', $createdAt);
      $query->execute();

      if ($query->fetch()) {
        return (int)$query->valueInt('changed_tables');
      }
    } catch (\Exception $e) {
      // Fail OPEN here on purpose: a schema-introspection failure must not disable the cache.
      $this->debugLog('freshness lookup failed, entry kept: ' . $e->getMessage());
    }

    return 0;
  }

  /**
   * Resolve and memoize the last server start, database-side.
   *
   * @return string|null Datetime, or null when it cannot be read
   */
  private function serverStart(): ?string
  {
    if ($this->serverStartResolved) {
      return $this->serverStart;
    }

    $this->serverStartResolved = true;

    try {
      $status = $this->db->query("SHOW GLOBAL STATUS LIKE 'Uptime'");
      $uptime = 0;

      if ($status !== false && $status->fetch()) {
        $uptime = (int)$status->value('Value');
      }

      if ($uptime > 0) {
        $query = $this->db->prepare("SELECT NOW() - INTERVAL :uptime SECOND AS started_at");
        $query->bindInt(':uptime', $uptime);
        $query->execute();

        if ($query->fetch()) {
          $this->serverStart = $query->value('started_at');
        }
      }
    } catch (\Exception $e) {
      $this->debugLog('server start lookup failed: ' . $e->getMessage());
    }

    return $this->serverStart;
  }

  /**
   * @param string $message Message to log when debugging
   * @return void
   */
  private function debugLog(string $message): void
  {
    if ($this->debug) {
      error_log("CacheFreshnessValidator: {$message}");
    }
  }
}
