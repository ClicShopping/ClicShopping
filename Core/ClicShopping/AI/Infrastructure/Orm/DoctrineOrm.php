<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Orm;



use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
use Doctrine\ORM\ORMSetup;
use Doctrine\DBAL\Types\Type;
use ClicShopping\AI\DomainsAI\CoreAI\Embedding\VectorType;
use ClicShopping\AI\Rag\MultiDBRAGManager;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\AI\Config\DomainFields;

/**
* Class DoctrineOrm
 *
 * This class manages database connections and operations using Doctrine ORM,
 * specifically adapted for use with LLPhant and MariaDB vector operations.
 * It provides functionality for:
  * - Database connection management
* - MariaDB version verification
* - Table structure management for RAG (Retrieval-Augmented Generation)
 * - Vector embedding table operations
*
 * Requirements:
 * - MariaDB version 11.7.0 or higher
* - Proper database credentials configuration
* - Vector support in MariaDB
*
 * @package ClicShopping\Apps\Configuration\ChatGpt\Classes\Rag
*/

class DoctrineOrm
{
  private static $debug = false;
  private static $prefixDb;
  private static ?SecurityLogger $logger = null;
  private static ?array $cachedFields = null;
  private static ?array $cachedFieldsByTable = null;

  public function __construct()
  {
    self::$debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER == 'True';
     Registry::set('MultiDBRAGManager', new MultiDBRAGManager());

    self::$prefixDb = CLICSHOPPING::getConfig('db_table_prefix');
  }

  /**
   * Configures and initializes Doctrine ORM settings.
   * Sets up the database connection parameters and ORM configuration.
   *
   * @return array Array containing connection parameters and configuration
   * @throws \Exception If configuration cannot be initialized
   */
  private static function Orm(): array
  {
    $config = ORMSetup::createConfiguration(true, null, null);
    $config->setMetadataDriverImpl(new SimplifiedXmlDriver([]));

    // Disable lazy ghost objects to avoid symfony/var-exporter dependency
    // This feature requires PHP 8.4+ or symfony/var-exporter 6.4+
    // We use classic Doctrine proxies instead
    if (method_exists($config, 'enableNativeLazyObjects')) {
      // PHP 8.4+: enable native lazy objects support
      $config->enableNativeLazyObjects(true);
    } elseif (method_exists($config, 'setLazyGhostObjectEnabled')) {
      // Earlier versions: maintain legacy behavior without generating a warning
      try {
        @$config->setLazyGhostObjectEnabled(false);
      } catch (\Throwable $e) {
        // Ignore any exception related to deprecation
      }
    }

    $connectionParams = [
      'driver' => 'pdo_mysql',
      'user' => CLICSHOPPING::getConfig('db_server_username'),
      'password' => CLICSHOPPING::getConfig('db_server_password'),
      'dbname' => CLICSHOPPING::getConfig('db_database'),
      'host' => CLICSHOPPING::getConfig('db_server'),
      'charset' => 'utf8mb4',
      'driverOptions' => [
        \Pdo\Mysql::ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
      ],
    ];

    try {
      $temporaryConnection = DriverManager::getConnection($connectionParams, $config);

      $result = $temporaryConnection->executeQuery("SELECT VERSION()");
      $serverVersion = $result->fetchOne();

      if (self::$debug) {
        error_log("Detected server version: " . $serverVersion);
      }

      if ($serverVersion) {
        $serverVersionLower = strtolower($serverVersion);

        // Extract and properly format the version
        if (strpos($serverVersionLower, 'mariadb') !== false) {
          // Typical format: "10.11.8-MariaDB" or "11.7.0-mariadb"
          preg_match('/(\d+\.\d+\.\d+)/', $serverVersion, $matches);

          if (!empty($matches[1])) {
            $versionNumber = $matches[1];
            $connectionParams['serverVersion'] = 'mariadb-' . $versionNumber;

            if (self::$debug) {
              error_log("[info] Using MariaDB version: mariadb-{$versionNumber}");
            }
          } else {
            $connectionParams['serverVersion'] = 'mariadb-11.7.0';

            if (self::$debug) {
              error_log("[warning]️ Could not extract version, using default: mariadb-11.7.0");
            }
          }
        } else {
          // MySQL
          preg_match('/(\d+\.\d+\.\d+)/', $serverVersion, $matches);

          if (!empty($matches[1])) {
            $versionNumber = $matches[1];
            $connectionParams['serverVersion'] = $versionNumber;

            if (self::$debug) {
              error_log("[info] Using MySQL version: {$versionNumber}");
            }
          } else {
            $connectionParams['serverVersion'] = '8.0.0';

            if (self::$debug) {
              error_log("[warning]️ Could not extract version, using default: 8.0.0");
            }
          }
        }
      } else {
        error_log('[warning]️ Unable to fetch server version, using default MariaDB 11.7.0');
        $connectionParams['serverVersion'] = 'mariadb-11.7.0';
      }

    } catch (\Exception $e) {
      error_log('⚠️ Error fetching server version: ' . $e->getMessage());
      error_log('Using default: mariadb-11.7.0');
      $connectionParams['serverVersion'] = 'mariadb-11.7.0';
    }

    if (self::$debug) {
      error_log("Final serverVersion parameter: " . $connectionParams['serverVersion']);
    }

    return ['connectionParams' => $connectionParams, 'config' => $config];
  }



  /**
   * Creates and returns an instance of the EntityManager.
   * Initializes the database connection and registers custom vector types.
   *
   * @return EntityManager The configured EntityManager instance
   * @throws \Doctrine\DBAL\Exception If connection cannot be established
   */
  public static function getEntityManager(): EntityManager
  {
    $orm = self::Orm();
    $connectionParams = $orm['connectionParams'];
    $config = $orm['config'];

    // Create the connection using the correct driver (pdo_mysql in this case)
    $connection = DriverManager::getConnection($connectionParams, $config);

    if (!Type::hasType('vector')) {
      Type::addType('vector', VectorType::class);
    }

    // EntityManager creation
    return new EntityManager($connection, $config);
  }

  /**
   * Checks if the database has the necessary tables and structures for RAG.
   * Compatible with Doctrine DBAL 3.x+
   *
   * @param string $tableName Name of the table to check
   * @return bool True if the structure is correct, false otherwise
   */
  public static function checkTableStructure(string $tableName): bool
  {
    try {
      $connection = self::getEntityManager()->getConnection();

      // Use information_schema to check existence
      $sql = "SELECT COUNT(*) 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name = :tableName";

      $result = $connection->executeQuery($sql, ['tableName' => $tableName]);
      $count = (int)$result->fetchOne();

      if (self::$debug) {
        error_log(" Table {$tableName}: " . ($count > 0 ? 'EXISTS' : 'NOT FOUND'));
      }

      return $count > 0;

    } catch (\Exception $e) {
      if (self::$debug) {
        error_log("Error checking table {$tableName}: " . $e->getMessage());
      }
      return false;
    }
  }

   /**
   * Logs an error message if debugging is enabled.
   * This function is used to log errors related to database operations.
   *
   * @param string $message The error message to log
   * @return void
   */
  private static function logError(string $message): void
  {
    if (self::$debug == 'True') {
      error_log($message);
    }
  }
  
  /**
   * Creates the necessary database structure for RAG if it doesn't exist.
   * Sets up tables with appropriate columns and vector indices for embedding storage.
   *
   * @param string $tableName Name of the table to create
   * @return bool True if creation succeeds, false otherwise
   * @throws \Exception If table creation fails
   */
  public static function createTableStructure(string $tableName): bool
  {
     return false;
  }

  /**
   * Get all relevant business tables for analytics queries
   * 
   * Dynamically discovers ALL business tables from database.
   * Excludes only system tables, logs, and temporary tables.
   * 
   * @param bool $useCache Whether to use cached results (default: true)
   * @return array List of table names
   */
  public static function getRelevantTables(bool $useCache = true): array
  {
    static $cachedTables = null;
    
    if ($useCache && $cachedTables !== null) {
      return $cachedTables;
    }
    
    try {
      $connection = self::getEntityManager()->getConnection();
      $prefix = self::$prefixDb;
      
      // Get ALL tables with the prefix
      $sql = "SELECT TABLE_NAME 
              FROM INFORMATION_SCHEMA.TABLES 
              WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME LIKE :pattern 
              ORDER BY TABLE_NAME";
      
      $result = $connection->executeQuery($sql, ['pattern' => $prefix . '%']);
      
      $allTables = [];
      while ($row = $result->fetchAssociative()) {
        $tableName = $row['TABLE_NAME'];
        
        // Exclude system/internal tables
        if (!self::isSystemTable($tableName)) {
          $allTables[] = $tableName;
        }
      }
      
      if (self::$debug) {
        error_log("DoctrineOrm::getRelevantTables() discovered " . count($allTables) . " tables");
      }
      
      $cachedTables = $allTables;
      return $allTables;
      
    } catch (\Exception $e) {
      if (self::$debug) {
        error_log("Failed to discover relevant tables: " . $e->getMessage());
      }
      
      // Fallback to static list
      return self::getFallbackRelevantTables();
    }
  }
  
  /**
   * Check if a table is a system/internal table that should be excluded
   * 
   * @param string $tableName Full table name
   * @return bool True if it's a system table
   */
  private static function isSystemTable(string $tableName): bool
  {
    // Exclude patterns for system tables
    $excludePatterns = [
      '_log$',           // Log tables
      '_cache$',         // Cache tables
      '_session',        // Session tables
      '_tmp',            // Temporary tables
      '_temp',           // Temporary tables
      '_backup',         // Backup tables
      '_old',            // Old/archived tables
      'migration',       // Migration tables
      'schema_version',  // Schema version tables
    ];
    
    foreach ($excludePatterns as $pattern) {
      if (preg_match('/' . $pattern . '/i', $tableName)) {
        return true;
      }
    }
    
    // Exclude specific system tables
    $excludeTables = [
      'administrators_log',
      'action_recorder',
      'whos_online',
      'sessions',
    ];
    
    foreach ($excludeTables as $table) {
      if ($tableName === self::$prefixDb . $table) {
        return true;
      }
    }
    
    return false;
  }
  
  /**
   * Get fallback table list (domain-agnostic)
   * 
   * Used when dynamic discovery fails.
   * 
   * MIGRATION: Removed hardcoded e-commerce table list.
   * Now uses EntityConfig when Ecommerce domain is active,
   * or returns empty array for domain-agnostic mode.
   * 
   * @return array List of tables from domain configuration or empty array
   */
  public static function getFallbackRelevantTables(): array
  {
    // Ensure prefix is initialized (may be called before constructor)
    if (empty(self::$prefixDb)) {
      self::$prefixDb = CLICSHOPPING::getConfig('db_table_prefix');
    }
    
    $activeDomain = DomainConfig::getActivities();
    $entityConfigClass = DomainFields::resolveAppClass($activeDomain, 'EntityConfig');
    if ($entityConfigClass !== null) {
      try {
        $entityConfig = $entityConfigClass::getConfig();

        $tables = [];
        foreach ($entityConfig as $entityType => $config) {
          if (isset($config['table'])) {
            $tables[] = $config['table'];
          }
        }

        if (self::$debug) {
          error_log("getFallbackRelevantTables: Using EntityConfig, found " . count($tables) . " tables");
        }

        return $tables;

      } catch (\Exception $e) {
        if (self::$debug) {
          error_log("getFallbackRelevantTables: EntityConfig failed: " . $e->getMessage());
        }
      }
    }
    
    // Domain-agnostic mode: return empty array
    // Dynamic discovery should handle this case
    if (self::$debug) {
      error_log("getFallbackRelevantTables: No domain configured, returning empty array");
    }
    
    return [];
  }

  /**
   * Returns a list of all available embedding tables in the database.
   * Queries the database to find tables that contain a VECTOR type embedding column.
   *
   * @return array List of table names containing vector embeddings
   * @throws \Exception If there is an error connecting to the database or executing the query
   */
  public static function getEmbeddingTables(): array
  {
    try {
      if (self::$debug) {
        error_log("========================================================");
        error_log("[INFO : ANALYSE] DoctrineOrm::getEmbeddingTables() START");
      }

      $entityManager = self::getEntityManager();
      $connection = $entityManager->getConnection();
      $prefix = self::$prefixDb;

      if (self::$debug) {
        error_log("Database prefix: '{$prefix}'");
        error_log("Database name: " . CLICSHOPPING::getConfig('db_database'));
      }

      $tables = [];

      // METHOD 1: Via information_schema
      try {
        $sql = "
        SELECT DISTINCT table_name
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND column_name = 'embedding'
          AND (data_type LIKE '%vector%' OR data_type = 'vector')
      ";

        if (self::$debug) {
          error_log("SQL Query: " . $sql);
        }

        $result = $connection->executeQuery($sql);
        $tables = $result->fetchFirstColumn();

        if (self::$debug) {
          error_log("information_schema result: " . print_r($tables, true));
        }

      } catch (\Exception $e) {
        if (self::$debug) {
          error_log("information_schema method failed: " . $e->getMessage());
        }
      }

      // METHOD 2 (FALLBACK): SHOW TABLES LIKE
      if (empty($tables)) {
        if (self::$debug) {
          error_log("[info] Trying SHOW TABLES fallback method...");
        }

        try {
          $sql = "SHOW TABLES LIKE ?";
          $pattern = self::$prefixDb . '%_embedding';

          if (self::$debug) {
            error_log("Pattern: {$pattern}");
          }

          $result = $connection->executeQuery($sql, [$pattern]);

          while ($row = $result->fetchNumeric()) {
            $tableName = $row[0];

            // Verify that the table has an 'embedding' column
            if (self::tableHasEmbeddingColumn($tableName)) {
              $tables[] = $tableName;

              if (self::$debug) {
                error_log("Valid table found: {$tableName}");
              }
            }
          }

        } catch (\Exception $e) {
          if (self::$debug) {
            error_log("SHOW TABLES method also failed: " . $e->getMessage());
          }
        }
      }

      // METHOD 3 (ULTIMATE FALLBACK): Validated hardcoded list
      if (empty($tables)) {
        if (self::$debug) {
          error_log("[info] Using hardcoded fallback list...");
        }

        $tables = self::getFallbackEmbeddingTables();
      }

      if (self::$debug) {
        error_log("-------------------------------------------------------");
        error_log(" FINAL RESULT: " . count($tables) . " tables found");
        error_log("Tables: " . implode(", ", $tables));
        error_log("========================================================");
      }

      return $tables;

    } catch (\Exception $e) {
      if (self::$debug) {
        error_log(" CRITICAL ERROR in getEmbeddingTables()");
        error_log("Error: " . $e->getMessage());
      }

      $logger = new SecurityLogger();
      $logger->logSecurityEvent(
        "Error in getEmbeddingTables: " . $e->getMessage(),
        'error'
      );

      return self::getFallbackEmbeddingTables();
    }
  }

  /**
   * Checks if a table has an 'embedding' column
   *
   * @param string $tableName
   * @return bool
   */
  private static function tableHasEmbeddingColumn(string $tableName): bool
  {
    try {
      $connection = self::getEntityManager()->getConnection();

      $sql = "SHOW COLUMNS FROM `{$tableName}` LIKE 'embedding'";

      $result = $connection->executeQuery($sql);
      $columnExists = $result->fetchOne();

      return $columnExists !== false;

    } catch (\Exception $e) {
      if (self::$debug) {
        error_log("️ Error checking column in {$tableName}: " . $e->getMessage());
      }
      return false;
    }
  }

  /**
   * Fallback list of known embedding tables
   * Validates each table before returning it
   *
   * @return array
   */
  private static function getFallbackEmbeddingTables(): array
  {
    $knownTables = (new MultiDBRAGManager)->knownEmbeddingTable();

    $validTables = [];

    foreach ($knownTables as $table) {
      $fullTableName = $table;

      if (self::checkTableStructure($fullTableName)) {
        $validTables[] = $fullTableName;

        if (self::$debug) {
          error_log("Fallback validated: {$fullTableName}");
        }
      } else {
        if (self::$debug) {
          error_log("Fallback table not found: {$fullTableName}");
        }
      }
    }

    if (self::$debug) {
      error_log("Fallback returned " . count($validTables) . " valid tables");
    }

    return $validTables;
  }
  
  // ============================================================================
  // Field Discovery Methods (moved from DatabaseSchemaIntrospector)
  // ============================================================================
  
  /**
   * Get all database field names across all relevant tables
   * 
   * Dynamically discovers fields from database schema using INFORMATION_SCHEMA.
   * Results are cached for performance.
   * 
   * @param bool $useCache Whether to use cached results (default: true)
   * @return array List of field names (e.g., ['sku', 'ean', 'price', 'stock'])
   */
  public static function getAllDatabaseFields(bool $useCache = true): array
  {
    if ($useCache && self::$cachedFields !== null) {
      if (self::$debug) {
        error_log("Using cached database fields (" . count(self::$cachedFields) . " fields)");
      }
      return self::$cachedFields;
    }
    
    try {
      $connection = self::getEntityManager()->getConnection();
      $relevantTables = self::getRelevantTables();
      
      if (self::$debug) {
        error_log("Discovering fields from " . count($relevantTables) . " tables");
      }
      
      $allFields = [];
      
      foreach ($relevantTables as $table) {
        try {
          $sql = "SELECT COLUMN_NAME 
                  FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = :tableName";
          
          $result = $connection->executeQuery($sql, ['tableName' => $table]);
          
          while ($row = $result->fetchAssociative()) {
            $fieldName = $row['COLUMN_NAME'];
            
            // Extract meaningful field name
            $cleanField = self::cleanFieldName($fieldName);
            
            if ($cleanField && !in_array($cleanField, $allFields, true)) {
              $allFields[] = $cleanField;
            }
          }
        } catch (\Exception $e) {
          if (self::$debug) {
            error_log("Error discovering fields from table {$table}: " . $e->getMessage());
          }
        }
      }
      
      // Add common field variations
      $allFields = self::addFieldVariations($allFields);
      
      if (self::$debug) {
        error_log("Discovered " . count($allFields) . " database fields");
      }
      
      self::$cachedFields = $allFields;
      return $allFields;
      
    } catch (\Exception $e) {
      if (self::$debug) {
        error_log("Failed to discover database fields: " . $e->getMessage());
      }
      
      // Fallback to static list
      return self::getFallbackDatabaseFields();
    }
  }
  
  /**
   * Get fields grouped by table
   * 
   * @param bool $useCache Whether to use cached results (default: true)
   * @return array ['products' => ['sku', 'price'], 'orders' => ['status', 'total']]
   */
  public static function getFieldsByTable(bool $useCache = true): array
  {
    if ($useCache && self::$cachedFieldsByTable !== null) {
      return self::$cachedFieldsByTable;
    }
    
    try {
      $connection = self::getEntityManager()->getConnection();
      $relevantTables = self::getRelevantTables();
      $fieldsByTable = [];
      
      foreach ($relevantTables as $table) {
        try {
          $sql = "SELECT COLUMN_NAME 
                  FROM INFORMATION_SCHEMA.COLUMNS 
                  WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = :tableName";
          
          $result = $connection->executeQuery($sql, ['tableName' => $table]);
          
          $tableFields = [];
          while ($row = $result->fetchAssociative()) {
            $fieldName = $row['COLUMN_NAME'];
            $cleanField = self::cleanFieldName($fieldName);
            
            if ($cleanField) {
              $tableFields[] = $cleanField;
            }
          }
          
          // Extract entity type from table name
          $entityType = self::extractEntityType($table);
          if ($entityType) {
            $fieldsByTable[$entityType] = $tableFields;
          }
          
        } catch (\Exception $e) {
          if (self::$debug) {
            error_log("Error discovering fields from table {$table}: " . $e->getMessage());
          }
        }
      }
      
      self::$cachedFieldsByTable = $fieldsByTable;
      return $fieldsByTable;
      
    } catch (\Exception $e) {
      if (self::$debug) {
        error_log("Failed to discover fields by table: " . $e->getMessage());
      }
      
      return [];
    }
  }
  
  /**
   * Check if a word is a database field (dynamic version)
   * 
   * @param string $word Word to check
   * @return bool True if it's a database field
   */
  public static function isDatabaseField(string $word): bool
  {
    $word = strtolower(trim($word));
    
    if (empty($word)) {
      return false;
    }
    
    // Get all fields dynamically
    $allFields = self::getAllDatabaseFields();
    
    // Direct match
    if (in_array($word, $allFields, true)) {
      return true;
    }
    
    // Fuzzy match
    foreach ($allFields as $field) {
      if (strpos($field, $word) !== false || strpos($word, $field) !== false) {
        return true;
      }
    }
    
    // Check against non-database words
    $nonDbWords = self::getNonDatabaseWords();
    if (in_array($word, $nonDbWords, true)) {
      return false;
    }
    
    return false;
  }
  
  /**
   * Clean field name for pattern matching
   * 
   * @param string $fieldName Raw field name from database
   * @return string|null Cleaned field name or null if not relevant
   */
  private static function cleanFieldName(string $fieldName): ?string
  {
    // Remove common table prefixes
    $fieldName = preg_replace('/^(products|orders|customers|categories|manufacturers|suppliers|reviews|pages_manager)_/', '', $fieldName);
    
    // Skip internal/system fields
    $skipFields = [
      'date_added', 'date_modified', 'last_modified', 'created_at', 'updated_at',
      'language_id', 'languages_id', 'customers_id', 'products_id', 'orders_id',
      'categories_id', 'manufacturers_id', 'suppliers_id'
    ];
    
    if (in_array($fieldName, $skipFields, true)) {
      return null;
    }
    
    // Remove _id suffix for non-ID fields
    if ($fieldName !== 'id' && preg_match('/_id$/', $fieldName)) {
      return null;
    }
    
    return $fieldName;
  }
  
  /**
   * Extract entity type from table name
   * 
   * @param string $tableName Full table name
   * @return string|null Entity type or null
   */
  private static function extractEntityType(string $tableName): ?string
  {
    // Remove prefix
    $entityType = str_replace(self::$prefixDb, '', $tableName);
    
    // Remove _description suffix
    $entityType = preg_replace('/_description$/', '', $entityType);
    
    // Take first part for compound names
    $parts = explode('_', $entityType);
    $entityType = $parts[0];
    
    return $entityType ?: null;
  }
  
  /**
   * Add common field variations
   * 
   * @param array $fields Original field list
   * @return array Extended field list with variations
   */
  private static function addFieldVariations(array $fields): array
  {
    $variations = [];
    $abbreviations = self::getFieldAbbreviations();
    
    foreach ($fields as $field) {
      $variations[] = $field;
      
      // Add plural form
      if (!str_ends_with($field, 's')) {
        $variations[] = $field . 's';
      }
      
      // Add singular form
      if (str_ends_with($field, 's')) {
        $variations[] = rtrim($field, 's');
      }
      
      // Add abbreviations
      foreach ($abbreviations as $full => $abbr) {
        if ($field === $full) {
          $variations[] = $abbr;
        }
        if ($field === $abbr) {
          $variations[] = $full;
        }
      }
    }
    
    return array_unique($variations);
  }
  
  /**
   * Clear field cache
   * 
   * @return void
   */
  public static function clearFieldCache(): void
  {
    self::$cachedFields = null;
    self::$cachedFieldsByTable = null;
    
    if (self::$debug) {
      error_log("DoctrineOrm field cache cleared");
    }
  }
  
  // ============================================================================
  // Common Query Methods (Task 4.4.1 - DB Operations Consolidation)
  // ============================================================================
  
  /**
   * Execute a SELECT query with parameters
   * 
   * Replaces direct $db->prepare() and $db->query() calls.
   * Provides consistent error handling and logging.
   * 
   * @param string $sql SQL query
   * @param array $params Parameters (named or positional)
   * @return array Results as associative array
   * @throws \Exception If query execution fails
   */
  public static function select(string $sql, array $params = []): array
  {
    try {
      $connection = self::getEntityManager()->getConnection();
      $result = $connection->executeQuery($sql, $params);
      return $result->fetchAllAssociative();
    } catch (\Exception $e) {
      if (self::$debug) {
        error_log("DoctrineOrm::select() error: " . $e->getMessage());
        error_log("SQL: " . $sql);
        error_log("Params: " . print_r($params, true));
      }
      throw $e;
    }
  }
  
  /**
   * Execute a SELECT query and return first row
   * 
   * Useful for queries that should return a single record.
   * 
   * @param string $sql SQL query
   * @param array $params Parameters
   * @return array|null First row or null if no results
   * @throws \Exception If query execution fails
   */
  public static function selectOne(string $sql, array $params = []): ?array
  {
    try {
      $connection = self::getEntityManager()->getConnection();
      $result = $connection->executeQuery($sql, $params);
      $row = $result->fetchAssociative();
      return $row ?: null;
    } catch (\Exception $e) {
      if (self::$debug) {
        error_log("DoctrineOrm::selectOne() error: " . $e->getMessage());
        error_log("SQL: " . $sql);
      }
      throw $e;
    }
  }
  
  /**
   * Execute a SELECT query and return single value
   * 
   * Useful for COUNT(), MAX(), single column queries, etc.
   * 
   * @param string $sql SQL query
   * @param array $params Parameters
   * @return mixed Single value or null
   * @throws \Exception If query execution fails
   */
  public static function selectValue(string $sql, array $params = [])
  {
    try {
      $connection = self::getEntityManager()->getConnection();
      $result = $connection->executeQuery($sql, $params);
      return $result->fetchOne();
    } catch (\Exception $e) {
      if (self::$debug) {
        error_log("DoctrineOrm::selectValue() error: " . $e->getMessage());
        error_log("SQL: " . $sql);
      }
      throw $e;
    }
  }
  
  /**
   * Check if a column exists in a table
   * 
   * Uses INFORMATION_SCHEMA with caching for performance.
   * 
   * @param string $tableName Table name
   * @param string $columnName Column name
   * @return bool True if column exists
   */
  public static function columnExists(string $tableName, string $columnName): bool
  {
    static $cache = [];
    $cacheKey = "{$tableName}.{$columnName}";
    
    if (isset($cache[$cacheKey])) {
      return $cache[$cacheKey];
    }
    
    try {
      $sql = "SELECT COUNT(*) as count 
              FROM INFORMATION_SCHEMA.COLUMNS 
              WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = :tableName 
              AND COLUMN_NAME = :columnName";
      
      $count = self::selectValue($sql, [
        'tableName' => $tableName,
        'columnName' => $columnName
      ]);
      
      $exists = ($count > 0);
      $cache[$cacheKey] = $exists;
      
      return $exists;
    } catch (\Exception $e) {
      if (self::$debug) {
        error_log("DoctrineOrm::columnExists() error: " . $e->getMessage());
      }
      return false;
    }
  }
  
  /**
   * Get all columns for a table
   * 
   * Uses INFORMATION_SCHEMA with caching for performance.
   * 
   * @param string $tableName Table name
   * @return array List of column names
   */
  public static function getTableColumns(string $tableName): array
  {
    static $cache = [];
    
    if (isset($cache[$tableName])) {
      return $cache[$tableName];
    }
    
    try {
      $sql = "SELECT COLUMN_NAME 
              FROM INFORMATION_SCHEMA.COLUMNS 
              WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = :tableName 
              ORDER BY ORDINAL_POSITION";
      
      $result = self::select($sql, ['tableName' => $tableName]);
      $columns = array_column($result, 'COLUMN_NAME');
      
      $cache[$tableName] = $columns;
      return $columns;
    } catch (\Exception $e) {
      if (self::$debug) {
        error_log("DoctrineOrm::getTableColumns() error: " . $e->getMessage());
      }
      return [];
    }
  }
  
  /**
   * Execute an INSERT/UPDATE/DELETE query
   * 
   * Returns the number of affected rows.
   * 
   * @param string $sql SQL query
   * @param array $params Parameters
   * @return int Number of affected rows
   * @throws \Exception If query execution fails
   */
  public static function execute(string $sql, array $params = []): int
  {
    try {
      $connection = self::getEntityManager()->getConnection();
      return $connection->executeStatement($sql, $params);
    } catch (\Exception $e) {
      if (self::$debug) {
        error_log("DoctrineOrm::execute() error: " . $e->getMessage());
        error_log("SQL: " . $sql);
      }
      throw $e;
    }
  }
  
  /**
   * Insert a record into a table
   * 
   * Automatically builds INSERT query from data array.
   * 
   * @param string $tableName Table name (without prefix)
   * @param array $data Associative array of column => value
   * @return bool True on success, false on failure
   */
  public static function insert(string $tableName, array $data): bool
  {
    try {
      // Initialize prefix if not set
      if (self::$prefixDb === null) {
        self::$prefixDb = CLICSHOPPING::getConfig('db_table_prefix');
      }
      
      // Add prefix if not already present
      if (strpos($tableName, self::$prefixDb) !== 0) {
        $tableName = self::$prefixDb . $tableName;
      }
      
      $connection = self::getEntityManager()->getConnection();
      $affectedRows = $connection->insert($tableName, $data);
      
      return $affectedRows > 0;
    } catch (\Exception $e) {
      if (self::$debug) {
        error_log("DoctrineOrm::insert() error: " . $e->getMessage());
        error_log("Table: " . $tableName);
        error_log("Data: " . print_r($data, true));
      }
      throw $e;
    }
  }
  
  /**
   * Update records in a table
   * 
   * @param string $tableName Table name (without prefix)
   * @param array $data Associative array of column => value to update
   * @param array $criteria WHERE conditions as associative array
   * @return int Number of affected rows
   */
  public static function update(string $tableName, array $data, array $criteria): int
  {
    try {
      // Initialize prefix if not set
      if (self::$prefixDb === null) {
        self::$prefixDb = CLICSHOPPING::getConfig('db_table_prefix');
      }
      
      // Add prefix if not already present
      if (strpos($tableName, self::$prefixDb) !== 0) {
        $tableName = self::$prefixDb . $tableName;
      }
      
      $connection = self::getEntityManager()->getConnection();
      return $connection->update($tableName, $data, $criteria);
    } catch (\Exception $e) {
      if (self::$debug) {
        error_log("DoctrineOrm::update() error: " . $e->getMessage());
        error_log("Table: " . $tableName);
      }
      throw $e;
    }
  }
  
  /**
   * Delete records from a table
   * 
   * @param string $tableName Table name (without prefix)
   * @param array $criteria WHERE conditions as associative array
   * @return int Number of affected rows
   */
  public static function delete(string $tableName, array $criteria): int
  {
    try {
      // Initialize prefix if not set
      if (self::$prefixDb === null) {
        self::$prefixDb = CLICSHOPPING::getConfig('db_table_prefix');
      }
      
      // Add prefix if not already present
      if (strpos($tableName, self::$prefixDb) !== 0) {
        $tableName = self::$prefixDb . $tableName;
      }
      
      $connection = self::getEntityManager()->getConnection();
      return $connection->delete($tableName, $criteria);
    } catch (\Exception $e) {
      if (self::$debug) {
        error_log("DoctrineOrm::delete() error: " . $e->getMessage());
        error_log("Table: " . $tableName);
      }
      throw $e;
    }
  }
  
  /**
   * Check if a table exists
   * 
   * Uses INFORMATION_SCHEMA with caching for performance.
   * 
   * @param string $tableName Table name
   * @return bool True if table exists
   */
  public static function tableExists(string $tableName): bool
  {
    static $cache = [];
    
    if (isset($cache[$tableName])) {
      return $cache[$tableName];
    }
    
    try {
      $sql = "SELECT COUNT(*) as count 
              FROM INFORMATION_SCHEMA.TABLES 
              WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = :tableName";
      
      $count = self::selectValue($sql, ['tableName' => $tableName]);
      
      $exists = ($count > 0);
      $cache[$tableName] = $exists;
      
      return $exists;
    } catch (\Exception $e) {
      if (self::$debug) {
        error_log("DoctrineOrm::tableExists() error: " . $e->getMessage());
      }
      return false;
    }
  }
  
  // ============================================================================
  // Utility Methods (moved from Old\AnalyticsPattern)
  // ============================================================================
  
  /**
   * Get fallback database fields (static list)
   * 
   * Used when dynamic discovery fails.
   * Get fallback database fields (domain-agnostic)
   * 
   * MIGRATION: Removed hardcoded e-commerce field list.
   * Now uses AnalyticsConfig when Ecommerce domain is active,
   * or returns empty array for domain-agnostic mode.
   * 
   * @return array List of database fields from domain configuration or empty array
   */
  private static function getFallbackDatabaseFields(): array
  {
    $activeDomain = \ClicShopping\AI\Config\DomainConfig::getActivities();
    $analyticsConfigClass = \ClicShopping\AI\Config\DomainFields::resolveAppClass($activeDomain, 'AnalyticsConfig');
    if ($analyticsConfigClass !== null) {
      try {
        return $analyticsConfigClass::getFallbackDatabaseFields();
      } catch (\Exception $e) {
        if (self::$debug) {
          error_log("getFallbackDatabaseFields: AnalyticsConfig failed: " . $e->getMessage());
        }
      }
    }
    
    // Domain-agnostic mode: return empty array
    if (self::$debug) {
      error_log("getFallbackDatabaseFields: No domain configured, returning empty array");
    }
    
    return [];
  }
  
  /**
   * Get non-database words (domain-agnostic)
   * 
   * MIGRATION: Removed hardcoded e-commerce word list.
   * Now uses AnalyticsConfig when Ecommerce domain is active,
   * or returns empty array for domain-agnostic mode.
   * 
   * @return array List of non-database words from domain configuration or empty array
   */
  private static function getNonDatabaseWords(): array
  {
    $activeDomain = DomainConfig::getActivities();
    $analyticsConfigClass = DomainFields::resolveAppClass($activeDomain, 'AnalyticsConfig');
    if ($analyticsConfigClass !== null) {
      try {
        return $analyticsConfigClass::getNonDatabaseWords();
      } catch (\Exception $e) {
        if (self::$debug) {
          error_log("getNonDatabaseWords: AnalyticsConfig failed: " . $e->getMessage());
        }
      }
    }
    
    // Domain-agnostic mode: return empty array
    if (self::$debug) {
      error_log("getNonDatabaseWords: No domain configured, returning empty array");
    }
    
    return [];
  }
  
  /**
   * Get field abbreviations mapping (domain-agnostic)
   * 
   * MIGRATION: Removed hardcoded e-commerce abbreviations.
   * Now uses AnalyticsConfig when Ecommerce domain is active,
   * or returns empty array for domain-agnostic mode.
   * 
   * @return array Mapping of full name => abbreviation from domain configuration or empty array
   */
  private static function getFieldAbbreviations(): array
  {
    $activeDomain = DomainConfig::getActivities();
    $analyticsConfigClass = DomainFields::resolveAppClass($activeDomain, 'AnalyticsConfig');
    if ($analyticsConfigClass !== null) {
      try {
        return $analyticsConfigClass::getFieldAbbreviations();
      } catch (\Exception $e) {
        if (self::$debug) {
          error_log("getFieldAbbreviations: AnalyticsConfig failed: " . $e->getMessage());
        }
      }
    }
    
    // Domain-agnostic mode: return empty array
    if (self::$debug) {
      error_log("getFieldAbbreviations: No domain configured, returning empty array");
    }
    
    return [];
  }
}
