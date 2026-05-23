<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Agent;


use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Security\InputValidator;
use ClicShopping\AI\Security\SecurityLogger;

/**
 * Class DatabaseSchemaManager
 * Manages database schema information, table relationships, and column indexing
 * Provides caching mechanisms for improved performance
 */

class DatabaseSchemaManager
{
  private \PDO $db;
  private SecurityLogger $securityLogger;
  private bool $debug;
  private array $tableSchemaCache = [];
  private array $tableRelationships = [];
  private array $columnSynonyms = [];
  private array $databaseSchema = [];
  private array $columnIndex = [];

  /**
   * Constructor
   *
   * @param \PDO $db Database connection
   * @param SecurityLogger $securityLogger Security logger instance
   * @param bool $debug Enable debug mode
   */
  public function __construct(
    \PDO $db,
    SecurityLogger $securityLogger,
    bool $debug = false
  ) {
    $this->db = $db;
    $this->securityLogger = $securityLogger;
    $this->debug = $debug;
  }

  /**
   * Gets the schema for a specific table
   * Uses caching to improve performance
   * Handles table name validation and error logging
   *
   * @param string $table Table name to get schema for
   * @return array Associative array where keys are column names and values are column types
   */
  public function getTableSchema(string $table): array
  {
    // Validate table name
    $safeTable = InputValidator::sanitizeIdentifier($table);

    if ($safeTable !== $table) {
      $this->securityLogger->logSecurityEvent(
        "Suspicious table name sanitized in getTableSchema: {$table} -> {$safeTable}",
        'warning'
      );
      $table = $safeTable;
    }

    // Check cache first
    if (isset($this->tableSchemaCache[$table])) {
      return $this->tableSchemaCache[$table];
    }

    try {
      // Retrieve the table schema WITH COMMENTS
      $query = $this->db->prepare("SHOW FULL COLUMNS FROM " . $table);
      $query->execute();
      $columns = $query->fetchAll(\PDO::FETCH_ASSOC);

      $schema = [];
      foreach ($columns as $column) {
        $schema[$column['Field']] = [
          'type' => $column['Type'],
          'comment' => $column['Comment'] ?? ''
        ];
      }

      // Cache the schema
      $this->tableSchemaCache[$table] = $schema;

      return $schema;
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error getting schema for table {$table}: " . $e->getMessage(),
        'error'
      );

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Error getting schema for table {$table}: " . $e->getMessage(),
          'error'
        );
      }

      return [];
    }
  }

  /**
   * Builds a comprehensive database schema for validation and correction
   * Creates detailed mapping of table structures including column properties
   * Generates an inverse index for quick column lookups
   * Implements security checks and error handling
   *
   * @return void
   * @throws \Exception When schema building encounters errors
   */
  public function buildDatabaseSchema(): void
  {
    try {
      $query = $this->db->prepare("SHOW TABLES");
      $query->execute();

      $tables = $query->fetchAll(\PDO::FETCH_COLUMN);

      $this->databaseSchema = [];

      foreach ($tables as $table) {
        // Validate table name
        $safeTable = InputValidator::sanitizeIdentifier($table);

        if ($safeTable !== $table) {
          $this->securityLogger->logSecurityEvent(
            "Suspicious table name sanitized in buildDatabaseSchema: {$table} -> {$safeTable}",
            'warning'
          );
          $table = $safeTable;
        }

        // Retrieve columns for each table WITH COMMENTS
        $columnsQuery = $this->db->prepare("SHOW FULL COLUMNS FROM " . $table);
        $columnsQuery->execute();
        $columns = $columnsQuery->fetchAll();

        $this->databaseSchema[$table] = [];

        foreach ($columns as $column) {
          $this->databaseSchema[$table][$column['Field']] = [
            'type' => $column['Type'],
            'null' => $column['Null'],
            'key' => $column['Key'],
            'default' => $column['Default'],
            'extra' => $column['Extra'],
            'comment' => $column['Comment'] ?? '' // Include column comment
          ];
        }
      }

      // Build inverse index for quick column lookups
      $this->columnIndex = [];

      foreach ($this->databaseSchema as $table => $columns) {
        foreach (array_keys($columns) as $column) {
          if (!isset($this->columnIndex[$column])) {
            $this->columnIndex[$column] = [];
          }
          $this->columnIndex[$column][] = $table;
        }
      }

      $this->securityLogger->logSecurityEvent(
        "Database schema built successfully: " . count($this->databaseSchema) . " tables indexed",
        'info'
      );
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error building database schema: " . $e->getMessage(),
        'error'
      );

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Error while building the database schema: " . $e->getMessage(),
          'error'
        );
      }

      // Re-throw for higher-level handling
      throw $e;
    }
  }

  /**
   * Initializes table relationships based on database schema
   * Analyzes table structures to identify foreign key relationships
   * Builds relationship mappings and column synonyms dictionary
   * Implements security checks and error handling
   *
   * @return void
   * @throws \Exception When database queries fail
   */
  public function initializeTableRelationships(): void
  {
    try {
      // Retrieve all tables from the database
      $query = $this->db->prepare("SHOW TABLES");
      $query->execute();
      $tables = $query->fetchAll(\PDO::FETCH_COLUMN);

      // For each table, analyze the columns to detect potential relationships
      foreach ($tables as $table) {
        // Validate table name
        $safeTable = InputValidator::sanitizeIdentifier($table);

        if ($safeTable !== $table) {
          $this->securityLogger->logSecurityEvent(
            "Suspicious table name sanitized: {$table} -> {$safeTable}",
            'warning'
          );
          $table = $safeTable;
        }

        $schema = $this->getTableSchema($table);

        foreach ($schema as $column => $columnInfo) {
          // Validate column name
          $safeColumn = InputValidator::sanitizeIdentifier($column);

          if ($safeColumn !== $column) {
            $this->securityLogger->logSecurityEvent(
              "Suspicious column name sanitized: {$column} -> {$safeColumn}",
              'warning'
            );
            $column = $safeColumn;
          }

          // Extract type from column info (now returns array with 'type' and 'comment')
          $type = is_array($columnInfo) ? $columnInfo['type'] : $columnInfo;

          // Detect ID columns that could be foreign keys
          if (preg_match('/_id$/', $column) && strpos($type, 'int') !== false) {
            $relatedTable = str_replace('_id', '', $column);

            // Validate related table name
            $safeRelatedTable = InputValidator::sanitizeIdentifier($relatedTable);

            if ($safeRelatedTable !== $relatedTable) {
              $this->securityLogger->logSecurityEvent(
                "Suspicious related table name sanitized: {$relatedTable} -> {$safeRelatedTable}",
                'warning'
              );
              $relatedTable = $safeRelatedTable;
            }

            $prefix = CLICSHOPPING::getConfig('prefix_table');
            // Check if the related table exists
            if (in_array($relatedTable, $tables, true) || in_array($prefix . $relatedTable, $tables, true)) {
              $actualTable = in_array($prefix . $relatedTable, $tables, true) ? $prefix . $relatedTable : $relatedTable;
              $this->tableRelationships[$table][$column] = $actualTable;
            }
          }
        }
      }

      // Build a dictionary of column synonyms based on similar names
      $this->buildColumnSynonyms($tables);
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error initializing table relationships: " . $e->getMessage(),
        'error'
      );

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Error initializing table relationships: " . $e->getMessage(),
          'error'
        );
      }

      // Re-throw for higher-level handling
      throw $e;
    }
  }

  /**
   * Builds column synonyms dictionary by analyzing table schemas
   * Identifies columns with similar names across different tables
   * Groups related columns based on common naming patterns
   *
   * @param array $tables List of database tables to analyze
   * @return void
   */
  public function buildColumnSynonyms(array $tables): void
  {
    $allColumns = [];

    // Collect all columns from all tables
    foreach ($tables as $table) {
      $schema = $this->getTableSchema($table);

      foreach ($schema as $column => $columnInfo) {
        if (!isset($allColumns[$column])) {
          $allColumns[$column] = [];
        }

        $allColumns[$column][] = $table;
      }
    }

    // Identify potential synonyms based on common name parts
    foreach ($allColumns as $column => $tables) {
      // Extract the significant part of the column name (without common prefixes/suffixes)
      $baseName = preg_replace('/^(.*?)_|_(.*?)$/', '', $column);

      if (strlen($baseName) >= 3) { // ignore names that are too short
        if (!isset($this->columnSynonyms[$baseName])) {
          $this->columnSynonyms[$baseName] = [];
        }

        $this->columnSynonyms[$baseName][] = $column;
      }
    }
  }

  /**
   * Gets the primary key column for a specific table
   * Searches for column with KEY='PRI' in DESCRIBE output
   *
   * @param string $table Table name
   * @return string|null Primary key column name or null if not found
   */
  public function getPrimaryKeyColumn(string $table): ?string
  {
    try {
      $safeTable = InputValidator::sanitizeIdentifier($table);
      $query = $this->db->prepare("DESCRIBE " . $safeTable);
      $query->execute();
      $columns = $query->fetchAll(\PDO::FETCH_ASSOC);

      foreach ($columns as $column) {
        if ($column['Key'] === 'PRI') {
          return $column['Field'];
        }
      }

      return null;
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error getting primary key for table {$table}: " . $e->getMessage(),
        'error'
      );
      return null;
    }
  }

  /**
   * Gets the table relationships mapping
   *
   * @return array Table relationships
   */
  public function getTableRelationships(): array
  {
    return $this->tableRelationships;
  }

  /**
   * Gets the complete database schema
   *
   * @return array Database schema
   */
  public function getDatabaseSchema(): array
  {
    return $this->databaseSchema;
  }


  /**
   * Gets the column index (inverse mapping of columns to tables)
   *
   * @return array Column index
   */
  public function getColumnIndex(): array
  {
    return $this->columnIndex;
  }


  /**
   * Gets the column synonyms dictionary
   *
   * @return array Column synonyms
   */
  public function getColumnSynonyms(): array
  {
    return $this->columnSynonyms;
  }

  /**
   * Finds tables that contain a specific column
   * Uses columnIndex to lookup tables containing the column
   * Returns empty array if column not found in any table
   *
   * @param string $column Column name to search for
   * @return array Array of table names containing the column
   */
  public function findTablesWithColumn(string $column): array
  {
    if (isset($this->columnIndex[$column])) {
      return $this->columnIndex[$column];
    }

    return [];
  }
}
