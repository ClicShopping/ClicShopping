<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Executor;

use ClicShopping\AI\Rag\MultiDBRAGManager;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Security\DbSecurity;
use ClicShopping\AI\DomainsAI\Shared\Entity\EntityRegistry;
use ClicShopping\AI\Infrastructure\Monitoring\QueryPerformanceMonitor;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;

/**
 * Class QueryExecutor
 * Handles secure SQL query execution, deduplication, and entity extraction
 * Implements retry mechanisms and comprehensive error handling
 * 
 */
class QueryExecutor
{
  private SecurityLogger $securityLogger;
  private DbSecurity $dbSecurity;
  private bool $debug;
  private ?QueryPerformanceMonitor $performanceMonitor;

  /**
   * Constructor
   * 
   *
   * @param \PDO|null $db Database connection (deprecated, kept for backward compatibility)
   * @param SecurityLogger $securityLogger Security logger instance
   * @param DbSecurity $dbSecurity Database security handler
   * @param bool $debug Enable debug mode
   * @param int $queryTimeout Query timeout in seconds (deprecated, not used)
   * @param bool $enablePerformanceMonitoring Enable query performance monitoring (default: true)
   */
  public function __construct(
    ?\PDO $db = null,
    ?SecurityLogger $securityLogger = null,
    ?DbSecurity $dbSecurity = null,
    bool $debug = false,
    int $queryTimeout = 30,
    bool $enablePerformanceMonitoring = true
  ) {
    // PDO parameter is now optional and ignored (kept for backward compatibility)
    $this->securityLogger = $securityLogger ?? new SecurityLogger();
    $this->dbSecurity = $dbSecurity ?? new DbSecurity();
    $this->debug = $debug;
    
    if ($enablePerformanceMonitoring) {
      $this->performanceMonitor = new QueryPerformanceMonitor(
        $this->securityLogger,
        $debug,
        1000 // 1 second threshold for slow queries
      );
    } else {
      $this->performanceMonitor = null;
    }
  }

  /**
   * Executes a SQL query and returns the results
   * Implements error handling, logging, and timeout protection
   * 
   *
   * @param string $sqlQuery SQL query to execute
   * @param array $parameters Optional parameters for prepared statement
   * @return array Result array with 'success', 'data', 'row_count', 'columns', or 'error'
   */
  public function execute(string $sqlQuery, array $parameters = []): array
  {
    $startTime = microtime(true);
    
    try {
      if ($this->debug) {
        error_log("QueryExecutor: Executing query via DoctrineOrm...");
      }
       
      $rows = DoctrineOrm::select($sqlQuery, $parameters);
      
      // Apply deduplication
      $dedupedRows = $this->deduplicateRows($rows);
      
      // Extract column names
      $columns = !empty($dedupedRows) ? array_keys($dedupedRows[0]) : [];
      
      $executionTime = round((microtime(true) - $startTime) * 1000, 2);

      // ✅ ALWAYS LOG SQL EXECUTION (Requirement 8.3)
      error_log(sprintf(
        '[RAG] SQL Execution: rows=%d, time=%dms, query=%s',
        count($dedupedRows),
        $executionTime,
        substr($sqlQuery, 0, 100) . (strlen($sqlQuery) > 100 ? '...' : '')
      ));

      if ($this->debug) {
        error_log("QueryExecutor: Query executed successfully, " . count($dedupedRows) . " rows returned in {$executionTime}ms");
      }

      $performanceAnalysis = null;
      if ($this->performanceMonitor !== null) {
        $performanceAnalysis = $this->performanceMonitor->monitorQuery(
          $sqlQuery,
          $executionTime,
          count($dedupedRows)
        );
        
        if ($this->debug && $performanceAnalysis['is_slow']) {
          error_log("QueryExecutor: Performance recommendations:");
          foreach ($performanceAnalysis['recommendations'] as $rec) {
            error_log("  [{$rec['priority']}] {$rec['message']}");
          }
        }
      }

      return [
        'success' => true,
        'data' => $dedupedRows,
        'row_count' => count($dedupedRows),
        'columns' => $columns,
        'execution_time_ms' => $executionTime,
        'metadata' => [
          'query_length' => strlen($sqlQuery),
          'parameter_count' => count($parameters),
        ],
        'performance_analysis' => $performanceAnalysis,
      ];

    } catch (\Exception $e) {
      $executionTime = round((microtime(true) - $startTime) * 1000, 2);
      error_log("QueryExecutor: Execution failed - " . $e->getMessage());
      
      $this->securityLogger->logSecurityEvent(
        "Query execution failed: " . $e->getMessage(),
        'error',
        [
          'query' => $sqlQuery,  // Log full query for errors to help debugging
          'execution_time_ms' => $executionTime,
          'parameters' => $parameters,
          'error_code' => $e->getCode(),
        ]
      );

      return [
        'success' => false,
        'error' => $e->getMessage(),
        'query' => $sqlQuery,
        'execution_time_ms' => $executionTime,
        'data' => [],
        'row_count' => 0,
        'columns' => [],
      ];
    }
  }

  /**
   * Executes SQL query with structured return format
   * Alias for execute() method with consistent interface
   *
   * @param string $sql SQL query to execute
   * @param array $parameters Optional parameters for prepared statement
   * @return array Structured result with success, data, row_count, error
   */
  public function executeSql(string $sql, array $parameters = []): array
  {
    return $this->execute($sql, $parameters);
  }

  /**
   * Executes a query with automatic retry on failure
   * Implements exponential backoff for transient errors
   *
   * @param string $sqlQuery SQL query to execute
   * @param int $maxAttempts Maximum number of retry attempts
   * @return array Result array with 'success', 'data', 'count', or 'error'
   */
  public function executeWithRetry(string $sqlQuery, int $maxAttempts = 3): array
  {
    $attempt = 0;
    $lastError = null;

    while ($attempt < $maxAttempts) {
      $attempt++;
      error_log("QueryExecutor: Attempt {$attempt}/{$maxAttempts}");

      $result = $this->execute($sqlQuery);

      if ($result['success']) {
        if ($attempt > 1) {
          $this->securityLogger->logSecurityEvent(
            "Query succeeded on attempt {$attempt}",
            'info'
          );
        }
        return $result;
      }

      $lastError = $result['error'] ?? 'Unknown error';
      
      // Check if error is retryable (e.g., deadlock, timeout)
      if (!$this->isRetryableError($lastError)) {
        error_log("QueryExecutor: Non-retryable error, aborting");
        break;
      }

      if ($attempt < $maxAttempts) {
        $waitTime = pow(2, $attempt - 1); // Exponential backoff: 1s, 2s, 4s
        error_log("QueryExecutor: Waiting {$waitTime}s before retry...");
        sleep($waitTime);
      }
    }

    $this->securityLogger->logSecurityEvent(
      "Query failed after {$attempt} attempts: {$lastError}",
      'error',
      ['query' => $sqlQuery]
    );

    return [
      'success' => false,
      'error' => $lastError,
      'attempts' => $attempt,
      'query' => $sqlQuery,
    ];
  }

  /**
   * Deduplicates rows in a result set
   * Uses MD5 hash to identify unique rows
   *
   * @param array $rows Array of rows to deduplicate
   * @return array Array of unique rows
   */
  public function deduplicateRows(array $rows): array
  {
    $seen = [];
    $unique = [];

    foreach ($rows as $r) {
      $h = md5(json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
      if (!isset($seen[$h])) {
        $seen[$h] = true;
        $unique[] = $r;
      }
    }

    return $unique;
  }

  /**
   * Extracts entity_id and entity_type from query results
   * Identifies the primary key value from results based on column naming conventions
   * 
   * Uses MultiDBRAGManager::knownEmbeddingTable() to dynamically determine entity types
   * instead of hardcoded mappings.
   *
   * @param array $results Query results
   * @return array Array with 'entity_id' and 'entity_type' keys
   */
  public function extractEntityIdFromResults(array $results): array
  {
    $entityId = null;
    $entityType = null;

    if (empty($results)) {
      $array = [
        'entity_id' => $entityId,
        'entity_type' => $entityType
      ];

      return $array;
    }

    $firstRow = $results[0];

    // Get ID column mappings dynamically from known embedding tables
    $idColumnNames = $this->getEntityIdColumnMappings();

    foreach ($idColumnNames as $idCol => $type) {
      if (isset($firstRow[$idCol]) && !empty($firstRow[$idCol])) {
        $entityId = (int) $firstRow[$idCol];
        $entityType = $type;

        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Entity extracted from results: ID={$entityId}, Type={$entityType} (column: {$idCol})",
            'info'
          );
        }

        break;
      }
    }

    return [
      'entity_id' => $entityId,
      'entity_type' => $entityType,
    ];
  }

  /**
   * Gets entity ID column mappings from EntityRegistry
   * 
   * Uses the centralized EntityRegistry to get ID column mappings
   * instead of duplicating the logic here.
   * 
   * @return array Associative array mapping ID column names to entity types
   */
  private function getEntityIdColumnMappings(): array
  {
    try {
      // Use centralized EntityRegistry for all entity table mappings
      $registry = EntityRegistry::getInstance();
      $idColumnMappings = $registry->getIdColumnMappings();
      
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Retrieved ID column mappings from EntityRegistry: " . json_encode($idColumnMappings),
          'info'
        );
      }
      
      return $idColumnMappings;
      
    } catch (\Exception $e) {
      // Fallback to basic mappings if EntityRegistry is not available
      $this->securityLogger->logSecurityEvent(
        "Failed to get ID mappings from EntityRegistry, using fallback: " . $e->getMessage(),
        'warning'
      );
      
      return $this->getFallbackIdColumnMappings();
    }
  }

  /**
   * Gets fallback ID column mappings when EntityRegistry fails
   * 
   * @return array Fallback ID column mappings
   */
  private function getFallbackIdColumnMappings(): array
  {
    return [
      'products_id' => 'products',
      'categories_id' => 'categories',
      'orders_id' => 'orders',
      'customers_id' => 'customers',
      'pages_id' => 'pages_manager',
      'suppliers_id' => 'suppliers',
      'manufacturers_id' => 'manufacturers',
      'reviews_id' => 'reviews',
      'return_id' => 'return_orders',
      'id' => 'generic',
    ];
  }

  /**
   * Determines if an error is retryable
   * Checks for transient errors like deadlocks or timeouts
   *
   * @param string $errorMessage Error message from database
   * @return bool True if error is retryable, false otherwise
   */
  private function isRetryableError(string $errorMessage): bool
  {
    $retryablePatterns = [
      '/deadlock/i',
      '/lock wait timeout/i',
      '/connection lost/i',
      '/server has gone away/i',
    ];

    foreach ($retryablePatterns as $pattern) {
      if (preg_match($pattern, $errorMessage)) {
        return true;
      }
    }

    return false;
  }
  
  /**
   * Get performance monitor instance
   * 
   * 
   * @return QueryPerformanceMonitor|null Performance monitor instance
   */
  public function getPerformanceMonitor(): ?QueryPerformanceMonitor
  {
    return $this->performanceMonitor;
  }
  
  /**
   * Get slow queries recorded during this session
   * 
   * 
   * @return array List of slow queries with analysis
   */
  public function getSlowQueries(): array
  {
    if ($this->performanceMonitor === null) {
      return [];
    }
    
    return $this->performanceMonitor->getSlowQueries();
  }
  
  /**
   * Get performance summary
   * 
   * 
   * @return array Performance summary
   */
  public function getPerformanceSummary(): array
  {
    if ($this->performanceMonitor === null) {
      return [
        'monitoring_enabled' => false,
        'message' => 'Performance monitoring is disabled'
      ];
    }
    
    return array_merge(
      ['monitoring_enabled' => true],
      $this->performanceMonitor->getSlowQuerySummary()
    );
  }
  
  /**
   * Analyze indexes for tables and get recommendations
   * 
   * 
   * @param array $tables List of table names to analyze
   * @return array Index analysis and recommendations
   */
  public function analyzeIndexes(array $tables): array
  {
    if ($this->performanceMonitor === null) {
      return [
        'error' => 'Performance monitoring is disabled'
      ];
    }
    
    return $this->performanceMonitor->analyzeIndexes($tables);
  }
  
  /**
   * Generate performance report
   * 
   * 
   * @return string HTML report
   */
  public function generatePerformanceReport(): string
  {
    if ($this->performanceMonitor === null) {
      return '<p>Performance monitoring is disabled</p>';
    }
    
    return $this->performanceMonitor->generateReport();
  }
}
