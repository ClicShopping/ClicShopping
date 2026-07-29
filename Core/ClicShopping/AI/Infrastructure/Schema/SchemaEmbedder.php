<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Schema;

use ClicShopping\AI\DomainsAI\Shared\Embedding\NewVector;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Cache as OMCache;

/**
 * SchemaEmbedder
 *
 * Owns the schema embedding store consumed by SchemaRetriever: it derives one
 * schema text per business table from the live database, embeds it and keeps
 * the store in sync with the schema.
 *
 * Responsibilities:
 * - Discover the business tables of the install (prefix scoped, technical stores excluded)
 * - Build the table schema text (same format as the schema injected in the prompt)
 * - Generate embeddings through NewVector and store them in a VECTOR column
 * - Report coverage so a stale store degrades visibly instead of silently
 *
 * @package ClicShopping\AI\Infrastructure\Schema
 */
class SchemaEmbedder
{
  private static bool $currencyChecked = false;
  private mixed $db;
  private bool $debug;
  private string $tablePrefix;

  /**
   * Constructor
   *
   * @param bool $debug Debug mode flag for logging
   */
  public function __construct(bool $debug = false)
  {
    $this->db = Registry::get('Db');
    $this->debug = $debug;
    $this->tablePrefix = CLICSHOPPING::getConfig('db_table_prefix');
  }

  /**
   * Get all table embeddings
   *
   * @return array Associative array of table_name => embedding_vector
   */
  public function getAllTableEmbeddings(): array
  {
    $Qembeddings = $this->db->query('
      SELECT table_name, VEC_ToText(embedding_vector) as embedding_text
      FROM :table_rag_schema_embedding
      ORDER BY table_name
    ');

    $embeddings = [];

    while ($Qembeddings->fetch()) {
      $tableName = $Qembeddings->value('table_name');

      // Technical stores are never business data — filtered at write, filtered again here
      if (self::isTechnicalTable($tableName, $this->tablePrefix)) {
        continue;
      }

      $embeddingText = trim($Qembeddings->value('embedding_text'), '[]');
      $embeddings[$tableName] = array_map('floatval', explode(',', $embeddingText));
    }

    return $embeddings;
  }

  /**
   * Fingerprint of the live schema: names, types and comments of every column, in one round-trip.
   *
   * Cheap enough (~4 ms) to be tested on every retrieval, unlike buildAllSchemaTexts() which
   * issues one SHOW FULL COLUMNS per table.
   *
   * @return string MD5 of the schema shape, '' when it cannot be computed
   */
  public function schemaFingerprint(): string
  {
    $Q = $this->db->query(
      "SELECT MD5(GROUP_CONCAT(CONCAT_WS(0x1f, TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IFNULL(COLUMN_COMMENT,''))
                               ORDER BY TABLE_NAME, ORDINAL_POSITION SEPARATOR 0x1e)) AS fingerprint
       FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()"
    );

    return $Q->fetch() ? (string)$Q->value('fingerprint') : '';
  }

  /**
   * Bring the store back in line with the schema when the schema has moved.
   *
   * The weekly `embeddings` cron is the intended refresh, but nothing guarantees it runs — on this
   * install it does not. Without a second path, a table added, dropped or re-commented degrades
   * retrieval silently until someone notices. Same principle as the language definitions: the live
   * schema is the source of truth and the derived store repairs itself.
   *
   * A schema is near-constant, so this is throttled to ONE check a day: within that window the
   * common case costs zero queries. Past it, two stages — a one-query fingerprint, and the exact
   * missing/stale comparison only when that fingerprint moved. A missing baseline is NOT treated
   * as drift — it costs one comparison, never a blind re-embedding of the whole store.
   *
   * @param int $maxTables Above this many changed tables the work is left to the cron / manual sync
   * @param string $checkEveryMinutes Throttle window, in minutes (default one day)
   * @return array Sync statistics, or [] when nothing had to be done
   */
  public function ensureCurrent(int $maxTables = 25, string $checkEveryMinutes = '1440'): array
  {
    if (self::$currencyChecked) {
      return [];
    }

    self::$currencyChecked = true;

    try {
      $cache = new OMCache('rag-schema-fingerprint');

      if ($cache->exists($checkEveryMinutes)) {
        return [];
      }

      $fingerprint = $this->schemaFingerprint();

      if ($fingerprint === '') {
        return [];
      }

      $baseline = $cache->get();

      if (is_string($baseline) && $baseline === $fingerprint) {
        $cache->save($fingerprint);

        return [];
      }

      $coverage = $this->getCoverage();
      $changed = count($coverage['missing'] ?? []) + count($coverage['stale'] ?? []);

      if ($changed === 0) {
        $cache->save($fingerprint);

        return [];
      }

      $logger = new SecurityLogger();

      if ($changed > $maxTables) {
        $logger->logApplicationError(
          "Schema store behind the schema by {$changed} tables — above the inline limit, left to the cron",
          ['missing' => count($coverage['missing'] ?? []), 'stale' => count($coverage['stale'] ?? [])]
        );

        return [];
      }

      $stats = $this->syncAllTables();
      $cache->save($this->schemaFingerprint());

      $logger->logApplicationError(
        'Schema store re-synced inline (cron did not run): '
          . ($stats['created'] ?? 0) . ' created, ' . ($stats['updated'] ?? 0) . ' updated, '
          . ($stats['deleted'] ?? 0) . ' deleted',
        ['changed_tables' => $changed]
      );

      return $stats;
    } catch (\Throwable $e) {
      // Never let a freshness check break a user query: degraded retrieval beats no answer.
      return [];
    }
  }

  /**
   * Synchronize the store with the current schema
   *
   * Embeds missing tables, re-embeds tables whose schema text changed, drops rows
   * of tables that no longer exist. Cheap when the schema did not move.
   *
   * @param bool $force Re-embed every table even when its schema text is unchanged
   * @return array Statistics: total, created, updated, unchanged, deleted, failed, duration_seconds
   */
  public function syncAllTables(bool $force = false): array
  {
    $startTime = microtime(true);

    $freshTexts = $this->buildAllSchemaTexts();
    $storedTexts = $this->getStoredSchemaTexts();
    $coverage = self::diffCoverage($freshTexts, $storedTexts);

    $stats = [
      'total_tables' => count($freshTexts),
      'created' => 0,
      'updated' => 0,
      'unchanged' => 0,
      'deleted' => 0,
      'failed' => 0,
      'duration_seconds' => 0
    ];

    $toEmbed = $force ? array_keys($freshTexts) : array_merge($coverage['missing'], $coverage['stale']);

    foreach ($toEmbed as $tableName) {
      $isNew = !isset($storedTexts[$tableName]);

      try {
        $this->embedAndStore($tableName, $freshTexts[$tableName]);
        $stats[$isNew ? 'created' : 'updated']++;
        $this->debugLog("embedded {$tableName}");
      } catch (\Exception $e) {
        $stats['failed']++;
        (new SecurityLogger())->logApplicationError(
          'SchemaEmbedder failed to embed table ' . $tableName . ': ' . $e->getMessage()
        );
      }
    }

    $stats['unchanged'] = $force ? 0 : count($coverage['unchanged']);

    if (!empty($coverage['orphan'])) {
      $stats['deleted'] = $this->deleteRows($coverage['orphan']);
    }

    $stats['duration_seconds'] = round(microtime(true) - $startTime, 2);

    // Re-read the store: what the sync could not fix is the degradation to make visible
    $this->reportCoverage(self::diffCoverage($freshTexts, $this->getStoredSchemaTexts()));

    $this->debugLog("sync done: {$stats['created']} created, {$stats['updated']} updated, {$stats['deleted']} deleted, {$stats['failed']} failed in {$stats['duration_seconds']}s");

    return $stats;
  }

  /**
   * Coverage of the store against the current schema
   *
   * @return array complete, db_tables, embedded, missing[], stale[], orphan[], unchanged[]
   */
  public function getCoverage(): array
  {
    return self::diffCoverage($this->buildAllSchemaTexts(), $this->getStoredSchemaTexts());
  }

  /**
   * Log an application error when the store does not cover the schema
   *
   * Silent degradation is the failure mode of this store: an incomplete store sends
   * wrong tables to the LLM without any error.
   *
   * @param array|null $coverage Coverage already computed, recomputed when null
   * @return bool True when the coverage is complete
   */
  public function reportCoverage(?array $coverage = null): bool
  {
    $coverage ??= $this->getCoverage();

    if ($coverage['complete']) {
      return true;
    }

    (new SecurityLogger())->logApplicationError(
      'Schema embedding store incomplete: ' . count($coverage['missing']) . ' table(s) missing, '
      . count($coverage['stale']) . ' stale, ' . count($coverage['orphan']) . ' orphan',
      [
        'missing' => array_slice($coverage['missing'], 0, 10),
        'stale' => array_slice($coverage['stale'], 0, 10)
      ]
    );

    return false;
  }

  /**
   * Build the schema text of every business table of the install
   *
   * @return array table_name => schema text
   */
  public function buildAllSchemaTexts(): array
  {
    $texts = [];

    foreach ($this->listSchemaTables() as $tableName) {
      $texts[$tableName] = self::formatTableText($tableName, $this->getTableColumns($tableName));
    }

    return $texts;
  }

  /**
   * List the business tables to embed
   *
   * Prefix scoped: an unprefixed table does not belong to this install.
   *
   * @return array Table names
   */
  public function listSchemaTables(): array
  {
    $like = str_replace(['\\', '_', '%'], ['\\\\', '\_', '\%'], $this->tablePrefix);
    $Qtables = $this->db->query("SHOW TABLES LIKE '{$like}%'");

    $tables = [];

    while ($Qtables->fetch()) {
      $row = $Qtables->toArray();

      if (empty($row)) {
        continue;
      }

      $tableName = (string)reset($row);

      if (!str_starts_with($tableName, $this->tablePrefix) || self::isTechnicalTable($tableName, $this->tablePrefix)) {
        continue;
      }

      $tables[] = $tableName;
    }

    sort($tables);

    return $tables;
  }

  /**
   * Technical stores carry no business semantics and must stay out of the schema retrieval
   *
   * @param string $tableName Table name
   * @param string $prefix Table prefix of the install
   * @return bool
   */
  public static function isTechnicalTable(string $tableName, string $prefix): bool
  {
    return str_contains($tableName, '_embedding') || str_starts_with($tableName, $prefix . 'rag_');
  }

  /**
   * Format the schema text of one table
   *
   * Same shape as the schema injected in the prompt: "Table: x" then one line per column.
   *
   * @param string $tableName Table name
   * @param array $columns Rows of SHOW FULL COLUMNS (Field, Type, Comment)
   * @return string
   */
  public static function formatTableText(string $tableName, array $columns): string
  {
    $text = "Table: {$tableName}\n";

    foreach ($columns as $column) {
      $comment = $column['Comment'] ?? '';
      $text .= "  - {$column['Field']} ({$column['Type']})" . (!empty($comment) ? ": {$comment}" : '') . "\n";
    }

    return trim($text);
  }

  /**
   * Compare the schema with the store
   *
   * @param array $freshTexts table_name => schema text derived from the live schema
   * @param array $storedTexts table_name => schema text currently embedded
   * @return array complete, db_tables, embedded, missing[], stale[], orphan[], unchanged[]
   */
  public static function diffCoverage(array $freshTexts, array $storedTexts): array
  {
    $missing = [];
    $stale = [];
    $unchanged = [];

    foreach ($freshTexts as $tableName => $text) {
      if (!isset($storedTexts[$tableName])) {
        $missing[] = $tableName;
      } elseif ($storedTexts[$tableName] !== $text) {
        $stale[] = $tableName;
      } else {
        $unchanged[] = $tableName;
      }
    }

    $orphan = array_values(array_diff(array_keys($storedTexts), array_keys($freshTexts)));

    return [
      'complete' => empty($missing) && empty($stale) && empty($orphan),
      'db_tables' => count($freshTexts),
      'embedded' => count($storedTexts),
      'missing' => $missing,
      'stale' => $stale,
      'orphan' => $orphan,
      'unchanged' => $unchanged
    ];
  }

  /**
   * Columns of a table with their comments
   *
   * @param string $tableName Table name
   * @return array Rows with Field, Type and Comment
   */
  private function getTableColumns(string $tableName): array
  {
    $columns = [];
    $Qcolumns = $this->db->query("SHOW FULL COLUMNS FROM `{$tableName}`");

    while ($Qcolumns->fetch()) {
      $columns[] = [
        'Field' => $Qcolumns->value('Field'),
        'Type' => $Qcolumns->value('Type'),
        'Comment' => $Qcolumns->value('Comment')
      ];
    }

    return $columns;
  }

  /**
   * Schema texts currently stored, technical rows included so they can be dropped
   *
   * Trimmed like the freshly built texts: surrounding whitespace carries no schema
   * meaning and would report every row stale.
   *
   * @return array table_name => schema text
   */
  private function getStoredSchemaTexts(): array
  {
    $Qstored = $this->db->query('
      SELECT table_name, schema_text
      FROM :table_rag_schema_embedding
      ORDER BY table_name
    ');

    $stored = [];

    while ($Qstored->fetch()) {
      $stored[$Qstored->value('table_name')] = trim($Qstored->value('schema_text'));
    }

    return $stored;
  }

  /**
   * Generate the embedding of a table and store it
   *
   * Stores the FIRST chunk only, on purpose: this is what the whole store holds and
   * what the retriever was tuned on. A wide table is therefore covered by its first
   * 800 tokens of columns — switching to a full-text vector is a measured change of
   * the retrieval ranking, not a detail (see BACKLOG).
   *
   * @param string $tableName Table name
   * @param string $schemaText Schema text to embed
   * @return void
   * @throws \Exception If the embedding generation or the write fails
   */
  private function embedAndStore(string $tableName, string $schemaText): void
  {
    $embeddedDocuments = NewVector::createEmbedding(null, $schemaText);

    if (empty($embeddedDocuments) || !isset($embeddedDocuments[0]->embedding)) {
      throw new \Exception('Embedding generation returned empty result');
    }

    $this->storeEmbedding($tableName, $schemaText, $embeddedDocuments[0]->embedding);
  }

  /**
   * Write the embedding in the VECTOR column
   *
   * @param string $tableName Table name
   * @param string $schemaText Schema text
   * @param array $embedding Embedding vector
   * @return void
   * @throws \Exception If the write fails
   */
  private function storeEmbedding(string $tableName, string $schemaText, array $embedding): void
  {
    $vectorString = '[' . implode(',', $embedding) . ']';
    $tokenCount = (int)ceil(strlen($schemaText) / 4);
    $now = date('Y-m-d H:i:s');

    $Qcheck = $this->db->prepare('
      SELECT id
      FROM :table_rag_schema_embedding
      WHERE table_name = :tbl_name
    ');

    $Qcheck->bindValue(':tbl_name', $tableName);
    $Qcheck->execute();

    if ($Qcheck->fetch() !== false) {
      $Qwrite = $this->db->prepare('
        UPDATE :table_rag_schema_embedding
        SET schema_text = :schema_text,
            embedding_vector = VEC_FromText(:embedding_vector),
            token_count = :token_count,
            updated_at = :updated_at
        WHERE table_name = :tbl_name
      ');
      $Qwrite->bindValue(':updated_at', $now);
    } else {
      $Qwrite = $this->db->prepare('
        INSERT INTO :table_rag_schema_embedding
        (table_name, schema_text, embedding_vector, token_count, created_at, updated_at)
        VALUES
        (:tbl_name, :schema_text, VEC_FromText(:embedding_vector), :token_count, :created_at, :updated_at)
      ');
      $Qwrite->bindValue(':created_at', $now);
      $Qwrite->bindValue(':updated_at', $now);
    }

    $Qwrite->bindValue(':tbl_name', $tableName);
    $Qwrite->bindValue(':schema_text', $schemaText);
    $Qwrite->bindValue(':embedding_vector', $vectorString);
    $Qwrite->bindInt(':token_count', $tokenCount);

    // A false return swallows the SQL error, so it must be raised explicitly
    if ($Qwrite->execute() === false) {
      throw new \Exception('Write of the schema embedding failed');
    }
  }

  /**
   * Delete stored rows of tables that are gone or technical
   *
   * @param array $tableNames Table names to drop from the store
   * @return int Number of deleted rows
   */
  private function deleteRows(array $tableNames): int
  {
    $deleted = 0;

    $Qdelete = $this->db->prepare('
      DELETE FROM :table_rag_schema_embedding
      WHERE table_name = :tbl_name
    ');

    foreach ($tableNames as $tableName) {
      $Qdelete->bindValue(':tbl_name', $tableName);

      if ($Qdelete->execute() !== false) {
        $deleted++;
        $this->debugLog("dropped stale row {$tableName}");
      }
    }

    return $deleted;
  }

  /**
   * Guarded debug logging
   *
   * @param string $message Message to log
   * @return void
   */
  private function debugLog(string $message): void
  {
    if ($this->debug) {
      error_log('[SchemaEmbedder] ' . $message);
    }
  }
}
