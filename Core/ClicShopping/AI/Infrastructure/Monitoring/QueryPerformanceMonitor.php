<?php
/**
 * QueryPerformanceMonitor.php
 * 
 * Monitors and optimizes SQL query performance
 * Identifies slow queries, suggests indexes, and provides optimization recommendations
 * 
 * @package ClicShopping\AI\Infrastructure\Monitoring
 * @author ClicShopping Team
 * @date 2025-12-06
 * @task 6.8 Optimize query performance for slow queries
 */

namespace ClicShopping\AI\Infrastructure\Monitoring;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;

/**
 * Class QueryPerformanceMonitor
 * 
 * Monitors query performance and provides optimization recommendations
 * 
 * Features:
 * - Identifies queries taking > 1 second
 * - Logs slow queries with execution details
 * - Suggests missing indexes
 * - Provides query optimization recommendations
 * - Tracks query patterns over time
 */
class QueryPerformanceMonitor
{
  private SecurityLogger $logger;
  private bool $debug;
  private int $slowQueryThreshold; // milliseconds
  private array $slowQueries = [];
  
  /**
   * Constructor
   * 
   * @param SecurityLogger|null $logger Security logger instance
   * @param bool $debug Enable debug mode
   * @param int $slowQueryThreshold Threshold in milliseconds for slow queries (default: 1000ms)
   */
  public function __construct(
    ?SecurityLogger $logger = null,
    bool $debug = false,
    int $slowQueryThreshold = 1000
  ) {
    $this->logger = $logger ?? new SecurityLogger();
    $this->debug = $debug;
    $this->slowQueryThreshold = $slowQueryThreshold;
  }
  
  /**
   * Monitor query execution and log if slow
   * 
   * @param string $sql SQL query
   * @param float $executionTimeMs Execution time in milliseconds
   * @param int $rowCount Number of rows returned
   * @return array Performance analysis
   */
  public function monitorQuery(string $sql, float $executionTimeMs, int $rowCount): array
  {
    $analysis = [
      'is_slow' => $executionTimeMs > $this->slowQueryThreshold,
      'execution_time_ms' => $executionTimeMs,
      'row_count' => $rowCount,
      'query_type' => $this->detectQueryType($sql),
      'tables_accessed' => $this->extractTables($sql),
      'has_joins' => $this->hasJoins($sql),
      'has_where' => $this->hasWhere($sql),
      'recommendations' => []
    ];
    
    if ($analysis['is_slow']) {
      $this->logSlowQuery($sql, $analysis);
      $analysis['recommendations'] = $this->generateRecommendations($sql, $analysis);
      $this->slowQueries[] = [
        'sql' => $sql,
        'analysis' => $analysis,
        'timestamp' => date('Y-m-d H:i:s')
      ];
    }
    
    return $analysis;
  }
  
  /**
   * Log slow query with details
   * 
   * @param string $sql SQL query
   * @param array $analysis Performance analysis
   * @return void
   */
  private function logSlowQuery(string $sql, array $analysis): void
  {
    $this->logger->logSecurityEvent(
      sprintf(
        "Slow query detected: %dms (threshold: %dms)",
        $analysis['execution_time_ms'],
        $this->slowQueryThreshold
      ),
      'warning',
      [
        'query' => $sql,
        'execution_time_ms' => $analysis['execution_time_ms'],
        'row_count' => $analysis['row_count'],
        'query_type' => $analysis['query_type'],
        'tables' => $analysis['tables_accessed'],
        'has_joins' => $analysis['has_joins'],
        'has_where' => $analysis['has_where']
      ]
    );
    
    if ($this->debug) {
      error_log(sprintf(
        "[QueryPerformanceMonitor] SLOW QUERY: %dms - %s",
        $analysis['execution_time_ms'],
        substr($sql, 0, 100) . (strlen($sql) > 100 ? '...' : '')
      ));
    }
  }
  
  /**
   * Generate optimization recommendations
   * 
   * @param string $sql SQL query
   * @param array $analysis Performance analysis
   * @return array List of recommendations
   */
  private function generateRecommendations(string $sql, array $analysis): array
  {
    $recommendations = [];
    
    // Check for missing indexes on WHERE clauses
    if ($analysis['has_where']) {
      $whereColumns = $this->extractWhereColumns($sql);
      foreach ($whereColumns as $table => $columns) {
        foreach ($columns as $column) {
          $recommendations[] = [
            'type' => 'index',
            'priority' => 'high',
            'message' => "Consider adding index on {$table}.{$column}",
            'sql' => "CREATE INDEX idx_{$column} ON {$table}({$column});"
          ];
        }
      }
    }
    
    // Check for unnecessary JOINs
    if ($analysis['has_joins'] && $analysis['row_count'] < 10) {
      $recommendations[] = [
        'type' => 'query_optimization',
        'priority' => 'medium',
        'message' => "Query has JOINs but returns few rows. Consider if all JOINs are necessary.",
        'suggestion' => "Review JOIN conditions and remove unnecessary joins"
      ];
    }
    
    // Check for SELECT *
    if (preg_match('/SELECT\s+\*/i', $sql)) {
      $recommendations[] = [
        'type' => 'query_optimization',
        'priority' => 'medium',
        'message' => "Query uses SELECT *. Specify only needed columns for better performance.",
        'suggestion' => "Replace SELECT * with specific column names"
      ];
    }
    
    // Check for LIKE without leading wildcard
    if (preg_match('/LIKE\s+[\'"]%/i', $sql)) {
      $recommendations[] = [
        'type' => 'query_optimization',
        'priority' => 'high',
        'message' => "Query uses LIKE with leading wildcard (%). This prevents index usage.",
        'suggestion' => "If possible, avoid leading wildcards or use full-text search"
      ];
    }
    
    // Check for large result sets
    if ($analysis['row_count'] > 1000) {
      $recommendations[] = [
        'type' => 'query_optimization',
        'priority' => 'high',
        'message' => "Query returns {$analysis['row_count']} rows. Consider adding LIMIT clause.",
        'suggestion' => "Add LIMIT clause to restrict result set size"
      ];
    }
    
    return $recommendations;
  }
  
  /**
   * Detect query type (SELECT, INSERT, UPDATE, DELETE)
   * 
   * @param string $sql SQL query
   * @return string Query type
   */
  private function detectQueryType(string $sql): string
  {
    $sql = trim(strtoupper($sql));
    
    if (str_starts_with($sql, 'SELECT')) return 'SELECT';
    if (str_starts_with($sql, 'INSERT')) return 'INSERT';
    if (str_starts_with($sql, 'UPDATE')) return 'UPDATE';
    if (str_starts_with($sql, 'DELETE')) return 'DELETE';
    
    return 'UNKNOWN';
  }
  
  /**
   * Extract table names from SQL query
   * 
   * @param string $sql SQL query
   * @return array List of table names
   */
  private function extractTables(string $sql): array
  {
    $tables = [];
    
    // Match FROM clause
    if (preg_match_all('/FROM\s+`?(\w+)`?/i', $sql, $matches)) {
      $tables = array_merge($tables, $matches[1]);
    }
    
    // Match JOIN clauses
    if (preg_match_all('/JOIN\s+`?(\w+)`?/i', $sql, $matches)) {
      $tables = array_merge($tables, $matches[1]);
    }
    
    return array_unique($tables);
  }
  
  /**
   * Check if query has JOINs
   * 
   * @param string $sql SQL query
   * @return bool True if query has JOINs
   */
  private function hasJoins(string $sql): bool
  {
    return preg_match('/\bJOIN\b/i', $sql) === 1;
  }
  
  /**
   * Check if query has WHERE clause
   * 
   * @param string $sql SQL query
   * @return bool True if query has WHERE clause
   */
  private function hasWhere(string $sql): bool
  {
    return preg_match('/\bWHERE\b/i', $sql) === 1;
  }
  
  /**
   * Extract columns used in WHERE clause
   * 
   * @param string $sql SQL query
   * @return array Associative array of table => columns
   */
  private function extractWhereColumns(string $sql): array
  {
    $columns = [];
    
    // Extract WHERE clause
    if (preg_match('/WHERE\s+(.+?)(?:GROUP BY|ORDER BY|LIMIT|$)/is', $sql, $matches)) {
      $whereClause = $matches[1];
      
      // Match table.column patterns
      if (preg_match_all('/(\w+)\.(\w+)\s*[=<>]/i', $whereClause, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
          $table = $match[1];
          $column = $match[2];
          
          if (!isset($columns[$table])) {
            $columns[$table] = [];
          }
          
          if (!in_array($column, $columns[$table], true)) {
            $columns[$table][] = $column;
          }
        }
      }
    }
    
    return $columns;
  }
  
  /**
   * Get all slow queries recorded during this session
   * 
   * @return array List of slow queries with analysis
   */
  public function getSlowQueries(): array
  {
    return $this->slowQueries;
  }
  
  /**
   * Get summary statistics for slow queries
   * 
   * @return array Summary statistics
   */
  public function getSlowQuerySummary(): array
  {
    if (empty($this->slowQueries)) {
      return [
        'total_slow_queries' => 0,
        'avg_execution_time_ms' => 0,
        'max_execution_time_ms' => 0,
        'most_common_tables' => []
      ];
    }
    
    $totalTime = 0;
    $maxTime = 0;
    $tableFrequency = [];
    
    foreach ($this->slowQueries as $query) {
      $time = $query['analysis']['execution_time_ms'];
      $totalTime += $time;
      $maxTime = max($maxTime, $time);
      
      foreach ($query['analysis']['tables_accessed'] as $table) {
        if (!isset($tableFrequency[$table])) {
          $tableFrequency[$table] = 0;
        }
        $tableFrequency[$table]++;
      }
    }
    
    arsort($tableFrequency);
    
    return [
      'total_slow_queries' => count($this->slowQueries),
      'avg_execution_time_ms' => round($totalTime / count($this->slowQueries), 2),
      'max_execution_time_ms' => $maxTime,
      'most_common_tables' => array_slice($tableFrequency, 0, 5, true)
    ];
  }
  
  /**
   * Analyze database indexes and suggest improvements
   * 
   * @param array $tables List of table names to analyze
   * @return array Index analysis and recommendations
   */
  public function analyzeIndexes(array $tables): array
  {
    $analysis = [];
    
    foreach ($tables as $table) {
      try {
        // Get current indexes
        $indexes = $this->getCurrentIndexes($table);
        
        // Get frequently queried columns from slow queries
        $frequentColumns = $this->getFrequentlyQueriedColumns($table);
        
        // Find missing indexes
        $missingIndexes = [];
        foreach ($frequentColumns as $column => $frequency) {
          if (!$this->hasIndexOnColumn($indexes, $column)) {
            $missingIndexes[] = [
              'column' => $column,
              'frequency' => $frequency,
              'recommendation' => "CREATE INDEX idx_{$column} ON {$table}({$column});"
            ];
          }
        }
        
        $analysis[$table] = [
          'current_indexes' => $indexes,
          'missing_indexes' => $missingIndexes,
          'total_indexes' => count($indexes),
          'recommended_indexes' => count($missingIndexes)
        ];
        
      } catch (\Exception $e) {
        $this->logger->logSecurityEvent(
          "Failed to analyze indexes for table {$table}: " . $e->getMessage(),
          'error'
        );
      }
    }
    
    return $analysis;
  }
  
  /**
   * Get current indexes for a table
   * 
   * @param string $table Table name
   * @return array List of indexes
   */
  private function getCurrentIndexes(string $table): array
  {
    try {
      $sql = "SHOW INDEX FROM {$table}";
      $results = DoctrineOrm::select($sql);
      
      $indexes = [];
      foreach ($results as $row) {
        $indexName = $row['Key_name'];
        if (!isset($indexes[$indexName])) {
          $indexes[$indexName] = [
            'name' => $indexName,
            'unique' => $row['Non_unique'] == 0,
            'columns' => []
          ];
        }
        $indexes[$indexName]['columns'][] = $row['Column_name'];
      }
      
      return array_values($indexes);
      
    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Failed to get indexes for table {$table}: " . $e->getMessage(),
        'error'
      );
      return [];
    }
  }
  
  /**
   * Get frequently queried columns for a table
   * 
   * @param string $table Table name
   * @return array Associative array of column => frequency
   */
  private function getFrequentlyQueriedColumns(string $table): array
  {
    $columns = [];
    
    foreach ($this->slowQueries as $query) {
      if (in_array($table, $query['analysis']['tables_accessed'], true)) {
        $whereColumns = $this->extractWhereColumns($query['sql']);
        if (isset($whereColumns[$table])) {
          foreach ($whereColumns[$table] as $column) {
            if (!isset($columns[$column])) {
              $columns[$column] = 0;
            }
            $columns[$column]++;
          }
        }
      }
    }
    
    arsort($columns);
    return $columns;
  }
  
  /**
   * Check if table has index on specific column
   * 
   * @param array $indexes List of indexes
   * @param string $column Column name
   * @return bool True if index exists
   */
  private function hasIndexOnColumn(array $indexes, string $column): bool
  {
    foreach ($indexes as $index) {
      if (in_array($column, $index['columns'], true)) {
        return true;
      }
    }
    return false;
  }
  
  /**
   * Generate performance report
   * 
   * @return string HTML report
   */
  public function generateReport(): string
  {
    $summary = $this->getSlowQuerySummary();
    
    $html = "<div class='performance-report'>\n";
    $html .= "<h3>Query Performance Report</h3>\n";
    
    // Summary
    $html .= "<div class='summary'>\n";
    $html .= "<h4>Summary</h4>\n";
    $html .= "<ul>\n";
    $html .= "<li>Total Slow Queries: {$summary['total_slow_queries']}</li>\n";
    $html .= "<li>Average Execution Time: {$summary['avg_execution_time_ms']}ms</li>\n";
    $html .= "<li>Max Execution Time: {$summary['max_execution_time_ms']}ms</li>\n";
    $html .= "</ul>\n";
    $html .= "</div>\n";
    
    // Most common tables
    if (!empty($summary['most_common_tables'])) {
      $html .= "<div class='common-tables'>\n";
      $html .= "<h4>Most Frequently Queried Tables</h4>\n";
      $html .= "<ul>\n";
      foreach ($summary['most_common_tables'] as $table => $count) {
        $html .= "<li>{$table}: {$count} queries</li>\n";
      }
      $html .= "</ul>\n";
      $html .= "</div>\n";
    }
    
    // Slow queries
    if (!empty($this->slowQueries)) {
      $html .= "<div class='slow-queries'>\n";
      $html .= "<h4>Slow Queries</h4>\n";
      foreach ($this->slowQueries as $query) {
        $html .= "<div class='query'>\n";
        $html .= "<p><strong>Time:</strong> {$query['analysis']['execution_time_ms']}ms</p>\n";
        $html .= "<p><strong>SQL:</strong> <code>" . htmlspecialchars($query['sql']) . "</code></p>\n";
        
        if (!empty($query['analysis']['recommendations'])) {
          $html .= "<p><strong>Recommendations:</strong></p>\n";
          $html .= "<ul>\n";
          foreach ($query['analysis']['recommendations'] as $rec) {
            $html .= "<li>[{$rec['priority']}] {$rec['message']}</li>\n";
          }
          $html .= "</ul>\n";
        }
        
        $html .= "</div>\n";
      }
      $html .= "</div>\n";
    }
    
    $html .= "</div>\n";
    
    return $html;
  }
}
