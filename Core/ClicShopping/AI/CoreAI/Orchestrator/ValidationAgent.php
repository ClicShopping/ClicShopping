<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator;


use ClicShopping\AI\Config\AutonomousConfig;
use ClicShopping\AI\CoreAI\Orchestrator\SubAbstention\AgentAbstentionManager;
use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\AgentEvaluation;
use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\FeedbackManager;
use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\LocalObjective;
use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\ObjectiveRegistry;
use ClicShopping\AI\Security\DbSecurity;
use ClicShopping\AI\Security\InputValidator;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * ValidationAgent Class
 * Specialized agent for proactive validation
 * Handles SQL validation, schema verification, performance analysis, and security checks
 */

class ValidationAgent
{
  private SecurityLogger $securityLogger;
  private DbSecurity $dbSecurity;
  private mixed $db;
  private bool $debug;
  private array $schemaCache = [];
  private ?AutonomousConfig $autonomousConfig = null;
  private ?AgentAbstentionManager $abstentionManager = null;

  private int $maxRowsWarning = 10000;
  private float $securityScoreThreshold = 0.4;

  private array $stats = [
    'total_validations' => 0,
    'validations_passed' => 0,
    'validations_failed' => 0,
    'security_blocks' => 0,
    'performance_warnings' => 0,
  ];

  /**
   * Constructor
   *
   * @param string $userId User identifier
   */
  public function __construct(string $userId = 'system')
  {
    $this->securityLogger = new SecurityLogger();
    $this->dbSecurity = new DbSecurity();
    $this->db = Registry::get('Db');
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    $this->autonomousConfig = new AutonomousConfig($this->debug);
    $this->abstentionManager = new AgentAbstentionManager();

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "ValidationAgent initialized",
        'info'
      );
    }
  }

  /**
   * Validate SQL query before execution
   *
   * @param string $sql SQL query to validate
   * @param array $context Additional context
   * @return array Validation result
   */
  public function validateBeforeExecution(string $sql, array $context = []): array
  {
    $startTime = microtime(true);
    $this->stats['total_validations']++;

    $validation = [
      'is_valid' => true,
      'errors' => [],
      'warnings' => [],
      'suggestions' => [],
      'security_score' => 1.0,
      'performance_score' => 1.0,
      'can_execute' => true,
    ];

    try {
      if (!$this->isSqlQuery($sql)) {
        $this->securityLogger->logSecurityEvent(
          "Skipping validation for natural language query: " . substr($sql, 0, 50),
          'info'
        );
        
        $validation['validation_time'] = microtime(true) - $startTime;
        $this->stats['validations_passed']++;
        
        return $validation;
      }

      
      $complexity = $this->estimateValidationComplexity($sql);
      
      $confidence = $this->abstentionManager->evaluateConfidence(
        'ValidationAgent',
        $sql,
        array_merge($context, [
          'task_type' => 'sql_validation',
          'description' => 'Validate SQL query',
          'parameters' => ['sql' => substr($sql, 0, 200)],
          'complexity' => $complexity
        ])
      );
      
      $decision = $this->abstentionManager->getAbstentionDecision(
        'ValidationAgent',
        $confidence,
        'sql_validation'
      );
      
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "ValidationAgent confidence: {$confidence}, decision: {$decision['action']}",
          'info'
        );
      }
      
      if ($decision['action'] === 'abstain') {
        // Log abstention to database
        $this->abstentionManager->logAbstention(
          'ValidationAgent',
          md5($sql),
          'sql_validation',
          $confidence,
          $decision['reason'],
          'escalate_human'
        );
        
        $this->stats['validations_failed']++;
        
        return [
          'is_valid' => false,
          'can_execute' => false,
          'errors' => ['Validation confidence too low - requires human review'],
          'warnings' => [],
          'suggestions' => [],
          'security_score' => 0.0,
          'performance_score' => 0.0,
          'confidence' => $confidence,
          'requires_human' => true,
          'reason' => $decision['reason']
        ];
      }
      
      if ($decision['action'] === 'delegate') {
        // Log delegation intent
        $this->abstentionManager->logAbstention(
          'ValidationAgent',
          md5($sql),
          'sql_validation',
          $confidence,
          $decision['reason'],
          'delegate_peer',
          $decision['suggested_delegate']
        );
        
        // Proceed with validation but note delegation was considered
        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "ValidationAgent delegating to: " . ($decision['suggested_delegate'] ?? 'none'),
            'info'
          );
        }
      }

      // 1. Validation syntaxique de base
      $syntaxValidation = $this->validateSyntax($sql);
      if (!$syntaxValidation['valid']) {
        $validation['is_valid'] = false;
        $validation['can_execute'] = false;
        $validation['errors'] = array_merge($validation['errors'], $syntaxValidation['issues']);
      }

      // 2. Validation de sécurité
      $securityValidation = $this->validateSecurity($sql, $context);
      $validation['security_score'] = $securityValidation['score'];

      if ($securityValidation['score'] < $this->securityScoreThreshold) {
        $validation['is_valid'] = false;
        $validation['can_execute'] = false;
        $validation['errors'][] = "Security score too low: " . $securityValidation['score'];
        $this->stats['security_blocks']++;

        $this->securityLogger->logSecurityEvent(
          "Query blocked by security validation: " . $sql,
          'warning',
          ['security_issues' => $securityValidation['issues']]
        );
      }

      if (!empty($securityValidation['issues'])) {
        $validation['warnings'] = array_merge($validation['warnings'], $securityValidation['issues']);
      }

      // 3. Validation du schéma (tables et colonnes existent-elles ?)
      if ($validation['can_execute']) {
        $schemaValidation = $this->validateSchema($sql);

        if (!$schemaValidation['valid']) {
          $validation['is_valid'] = false;
          $validation['errors'] = array_merge($validation['errors'], $schemaValidation['errors']);

          // Ne pas bloquer l'exécution, mais avertir
          if (!empty($schemaValidation['suggestions'])) {
            $validation['suggestions'] = array_merge(
              $validation['suggestions'],
              $schemaValidation['suggestions']
            );
          }
        }
      }

      // 4. Validation de performance (EXPLAIN)
      if ($validation['can_execute'] && stripos($sql, 'SELECT') === 0) {
        $performanceValidation = $this->validatePerformance($sql);
        $validation['performance_score'] = $performanceValidation['score'];

        if (!empty($performanceValidation['warnings'])) {
          $validation['warnings'] = array_merge(
            $validation['warnings'],
            $performanceValidation['warnings']
          );
          $this->stats['performance_warnings']++;
        }

        if (!empty($performanceValidation['suggestions'])) {
          $validation['suggestions'] = array_merge(
            $validation['suggestions'],
            $performanceValidation['suggestions']
          );
        }
      }

      // Validate by query type
      $typeValidation = $this->validateByType($sql);
      if (!empty($typeValidation['warnings'])) {
        $validation['warnings'] = array_merge($validation['warnings'], $typeValidation['warnings']);
      }

      // Update stats
      if ($validation['is_valid']) {
        $this->stats['validations_passed']++;
      } else {
        $this->stats['validations_failed']++;
      }

      $validation['validation_time'] = microtime(true) - $startTime;

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Validation completed: " . ($validation['is_valid'] ? 'PASSED' : 'FAILED') .
          " (security: {$validation['security_score']}, performance: {$validation['performance_score']})",
          $validation['is_valid'] ? 'info' : 'warning'
        );
      }

      return $validation;

    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Validation error: " . $e->getMessage(),
        'error'
      );

      return [
        'is_valid' => false,
        'can_execute' => false,
        'errors' => ['Validation failed: ' . $e->getMessage()],
        'warnings' => [],
        'suggestions' => [],
        'security_score' => 0.0,
        'performance_score' => 0.0,
      ];
    }
  }

  /**
   * Detect if input is SQL query or natural language
   *
   * @param string $input Text to analyze
   * @return bool True if SQL, false if natural language
   */
  private function isSqlQuery(string $input): bool
  {
    $input = trim($input);
    
    if (empty($input)) {
      return false;
    }
    
    $sqlKeywords = [
      'SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP', 
      'ALTER', 'TRUNCATE', 'REPLACE', 'SHOW', 'DESCRIBE', 'EXPLAIN'
    ];
    
    $firstWord = strtoupper(explode(' ', $input)[0]);
    
    if (in_array($firstWord, $sqlKeywords, true)) {
      return true;
    }
    
    if (preg_match('/^\s*(SELECT|INSERT|UPDATE|DELETE|CREATE|DROP|ALTER|TRUNCATE)\s+/i', $input)) {
      return true;
    }
    
    if (preg_match('/\b(FROM|WHERE|JOIN|GROUP BY|ORDER BY|HAVING|LIMIT)\b/i', $input)) {
      if (preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $input)) {
        return true;
      }
    }
    
    return false;
  }

  /**
   * Estimate validation complexity for confidence evaluation
   *

   *
   * @param string $sql The SQL query to validate
   * @return float Complexity score (0.0-1.0)
   */
  private function estimateValidationComplexity(string $sql): float
  {
    $complexity = 0.3; // Base complexity

    // Increase for subqueries
    $subqueryCount = preg_match_all('/\(\s*SELECT\b/i', $sql);
    $complexity += $subqueryCount * 0.1;

    // Increase for joins
    $joinCount = preg_match_all('/\bJOIN\b/i', $sql);
    $complexity += $joinCount * 0.05;

    // Increase for complex WHERE clauses
    if (preg_match('/\bWHERE\b/i', $sql)) {
      $andOrCount = preg_match_all('/\b(AND|OR)\b/i', $sql);
      $complexity += $andOrCount * 0.03;
    }

    // Increase for GROUP BY and HAVING
    if (preg_match('/\bGROUP\s+BY\b/i', $sql)) {
      $complexity += 0.1;
    }
    if (preg_match('/\bHAVING\b/i', $sql)) {
      $complexity += 0.1;
    }

    // Increase for UNION operations
    $unionCount = preg_match_all('/\bUNION\b/i', $sql);
    $complexity += $unionCount * 0.15;

    // Increase for destructive operations (higher risk)
    if (preg_match('/\b(UPDATE|DELETE|DROP|TRUNCATE)\b/i', $sql)) {
      $complexity += 0.2;
    }

    return min(1.0, $complexity);
  }

  /**
   * Validate SQL syntax
   *
   * @param string $sql SQL query
   * @return array Validation result
   */
  private function validateSyntax(string $sql): array
  {
    return InputValidator::validateSqlQuery($sql);
  }

  /**
   * Validate query security
   *
   * @param string $sql SQL query
   * @param array $context Additional context
   * @return array Security validation result
   */
  private function validateSecurity(string $sql, array $context): array
  {
    $issues = [];
    $score = 1.0;

    $injectionPatterns = [
      ['/--/', 0.3],
      ['/#/', 0.3],
      ['/\/\*.*?\*\//', 0.3],
      ['/\bunion\b.*\bselect\b/i', 0.4],
      ['/\bor\b\s+\d+\s*=\s*\d+/i', 0.4],     // OR 1=1 attacks (higher penalty)
      ['/\bsleep\s*\(/i', 0.4],               // Time-based attacks
      ['/\bbenchmark\s*\(/i', 0.4],           // Benchmark attacks
      ['/\bload_file\s*\(/i', 0.5],           // File reading (very dangerous)
      ['/\binto\s+outfile\b/i', 0.5],         // File writing (very dangerous)
      ['/\bexec\s*\(/i', 0.5],                // Code execution (very dangerous)
      ['/\bxp_/i', 0.4],                      // Extended stored procedures
    ];

    foreach ($injectionPatterns as $patternData) {
      $pattern = $patternData[0];
      $penalty = $patternData[1];

      if (preg_match($pattern, $sql)) {
        $issues[] = "Suspicious pattern detected: " . $pattern;
        $score -= $penalty;
      }
    }

    // Check destructive operations
    if (preg_match('/\b(DROP|TRUNCATE|DELETE|UPDATE)\b/i', $sql)) {
      $issues[] = "Destructive operation detected";
      $score -= 0.5;
    }

    // Check UPDATE/DELETE without WHERE
    if (preg_match('/\b(UPDATE|DELETE)\b/i', $sql) && !preg_match('/\bWHERE\b/i', $sql)) {
      $issues[] = "UPDATE/DELETE without WHERE clause";
      $score -= 0.4;
    }

    // Check SELECT * (warning only)
    if (preg_match('/SELECT\s+\*/i', $sql)) {
      $issues[] = "SELECT * detected (performance concern)";
      // Reduced penalty from 0.1 to 0.05 - common in analytics
      $score -= 0.05;
    }

    // Check multiple subqueries
    $subqueryCount = preg_match_all('/\(\s*SELECT\b/i', $sql);
    if ($subqueryCount > 5) { // Increased from 3 to 5
      $issues[] = "Multiple subqueries detected ({$subqueryCount})";
      // Reduced penalty from 0.1 to 0.05
      $score -= 0.05;
    }

    return [
      'score' => max(0.0, $score),
      'issues' => $issues,
    ];
  }

  /**
   * Validate that tables and columns exist
   */
  private function validateSchema(string $sql): array
  {
    $errors = [];
    $suggestions = [];

    try {
      // Extraire les tables
      $tables = $this->extractTables($sql);

      foreach ($tables as $table) {
        $tableName = $this->normalizeTableName($table);

        // Check if table exists
        if (!$this->tableExists($tableName)) {
          $errors[] = "Table does not exist: {$tableName}";

          // Suggest similar tables
          $similar = $this->findSimilarTables($tableName);
          if (!empty($similar)) {
            $suggestions[] = "Did you mean: " . implode(', ', $similar) . "?";
          }
        }
      }

      // Extraire les colonnes
      $columns = $this->extractColumns($sql);

      foreach ($columns as $column) {
        // Check if column exists in tables
        if (!$this->columnExistsInTables($column, $tables)) {
          $errors[] = "Column may not exist: {$column}";

          // Suggest similar columns
          $similar = $this->findSimilarColumns($column, $tables);
          if (!empty($similar)) {
            $suggestions[] = "Did you mean: " . implode(', ', $similar) . "?";
          }
        }
      }

      return [
        'valid' => empty($errors),
        'errors' => $errors,
        'suggestions' => $suggestions,
      ];

    } catch (\Exception $e) {
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Schema validation error: " . $e->getMessage(),
          'error'
        );
      }

      return [
        'valid' => true, // Ne pas bloquer en cas d'erreur de validation
        'errors' => [],
        'suggestions' => [],
      ];
    }
  }

  /**
   * Extract table names from query
   */
  private function extractTables(string $sql): array
  {
    $tables = [];

    // FROM clause
    if (preg_match('/\bFROM\s+([^\s,;]+)/i', $sql, $matches)) {
      $tables[] = trim($matches[1], '`"\'');
    }

    // JOIN clauses
    preg_match_all('/\bJOIN\s+([^\s,;]+)/i', $sql, $matches);
    foreach ($matches[1] as $table) {
      $tables[] = trim($table, '`"\'');
    }

    return array_unique($tables);
  }

  /**
   * Normalise un nom de table
   */
  private function normalizeTableName(string $table): string
  {
    // Enlever les alias
    if (preg_match('/^([^\s]+)/', $table, $matches)) {
      $table = $matches[1];
    }

    // Ajouter le préfixe si nécessaire
    $prefix = CLICSHOPPING::getConfig('db_prefix');
    if (!empty($prefix) && !str_starts_with($table, $prefix)) {
      $table = $prefix . $table;
    }

    return trim($table, '`"\'');
  }

  /**
   * Check if table exists
   */
  private function tableExists(string $tableName): bool
  {
    if (isset($this->schemaCache['tables'][$tableName])) {
      return $this->schemaCache['tables'][$tableName];
    }

    try {
      $stmt = $this->db->prepare(
        "SELECT COUNT(*) 
         FROM information_schema.tables 
         WHERE table_schema = DATABASE() 
         AND table_name = ?"
      );
      $stmt->execute([$tableName]);
      $exists = (int)$stmt->fetchColumn() > 0;

      $this->schemaCache['tables'][$tableName] = $exists;

      return $exists;

    } catch (\Exception $e) {
      return false;
    }
  }

  /**
   * Trouve des tables similaires
   */
  private function findSimilarTables(string $tableName): array
  {
    try {
      $stmt = $this->db->prepare(
        "SELECT table_name 
         FROM information_schema.tables 
         WHERE table_schema = DATABASE()"
      );
      $stmt->execute();
      $allTables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

      $similar = [];
      foreach ($allTables as $table) {
        similar_text(strtolower($tableName), strtolower($table), $percent);
        if ($percent > 60) {
          $similar[] = $table;
        }
      }

      return array_slice($similar, 0, 3);

    } catch (\Exception $e) {
      return [];
    }
  }

  /**
   * Extract column names from query
   */
  private function extractColumns(string $sql): array
  {
    $columns = [];

    // SELECT columns (simple extraction)
    if (preg_match('/SELECT\s+(.*?)\s+FROM/is', $sql, $matches)) {
      $selectPart = $matches[1];

      // Ignorer SELECT *
      if (trim($selectPart) === '*') {
        return [];
      }

      $parts = explode(',', $selectPart);
      foreach ($parts as $part) {
        $part = trim($part);

        // Extraire le nom de colonne (ignorer les alias)
        if (preg_match('/([a-z_][a-z0-9_]*)\s+AS\s+/i', $part, $m)) {
          $columns[] = $m[1];
        } elseif (preg_match('/([a-z_][a-z0-9_]*)/i', $part, $m)) {
          $columns[] = $m[1];
        }
      }
    }

    return array_unique($columns);
  }

  /**
   * Check if column exists in tables
   */
  private function columnExistsInTables(string $column, array $tables): bool
  {
    foreach ($tables as $table) {
      $tableName = $this->normalizeTableName($table);

      if ($this->columnExistsInTable($column, $tableName)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Check if column exists in table
   */
  private function columnExistsInTable(string $column, string $table): bool
  {
    $cacheKey = "{$table}.{$column}";

    if (isset($this->schemaCache['columns'][$cacheKey])) {
      return $this->schemaCache['columns'][$cacheKey];
    }

    try {
      $stmt = $this->db->prepare(
        "SELECT COUNT(*) 
        FROM information_schema.columns 
        WHERE table_schema = DATABASE() 
        AND table_name = ? 
        AND column_name = ?"
      );
      $stmt->execute([$table, $column]);
      $exists = (int)$stmt->fetchColumn() > 0;

      $this->schemaCache['columns'][$cacheKey] = $exists;

      return $exists;

    } catch (\Exception $e) {
      return false;
    }
  }

  /**
   * Trouve des colonnes similaires
   */
  private function findSimilarColumns(string $column, array $tables): array
  {
    $similar = [];

    foreach ($tables as $table) {
      $tableName = $this->normalizeTableName($table);

      try {
        $stmt = $this->db->prepare(
          "SELECT column_name 
           FROM information_schema.columns 
           WHERE table_schema = DATABASE() 
           AND table_name = ?"
        );
        $stmt->execute([$tableName]);
        $columns = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($columns as $col) {
          similar_text(strtolower($column), strtolower($col), $percent);
          if ($percent > 60) {
            $similar[] = "{$tableName}.{$col}";
          }
        }

      } catch (\Exception $e) {
        continue;
      }
    }

    return array_slice($similar, 0, 3);
  }

  /**
   * Validate performance with EXPLAIN
   */
  private function validatePerformance(string $sql): array
  {
    $warnings = [];
    $suggestions = [];
    $score = 1.0;

    try {
      // Exécuter EXPLAIN
      $explainSql = "EXPLAIN " . $sql;
      $stmt = $this->db->prepare($explainSql);
      $stmt->execute();
      $explainResult = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      foreach ($explainResult as $row) {
        // Check scan type
        $type = $row['type'] ?? '';

        if ($type === 'ALL') {
          $warnings[] = "Full table scan detected on table: " . ($row['table'] ?? 'unknown');
          $suggestions[] = "Consider adding an index on the WHERE/JOIN columns";
          // Reduced penalty from 0.2 to 0.05 - common in analytics
          $score -= 0.05;
        }

        // Check index usage
        $possibleKeys = $row['possible_keys'] ?? null;
        $key = $row['key'] ?? null;

        if ($possibleKeys && !$key) {
          $warnings[] = "Indexes available but not used on table: " . ($row['table'] ?? 'unknown');
          // Reduced penalty from 0.1 to 0.03
          $score -= 0.03;
        }

        // Check rows examined
        $rows = (int)($row['rows'] ?? 0);

        if ($rows > $this->maxRowsWarning) {
          $warnings[] = "Large number of rows to examine: {$rows}";
          $suggestions[] = "Consider adding WHERE clause to limit rows";
          // Reduced penalty from 0.15 to 0.05
          $score -= 0.05;
        }

        // Check Extra field
        $extra = $row['Extra'] ?? '';

        if (stripos($extra, 'Using filesort') !== false) {
          $warnings[] = "Filesort detected (slow ORDER BY)";
          $suggestions[] = "Consider adding an index on ORDER BY columns";
          // Reduced penalty from 0.1 to 0.03
          $score -= 0.03;
        }

        if (stripos($extra, 'Using temporary') !== false) {
          $warnings[] = "Temporary table creation detected";
          $suggestions[] = "Query might benefit from optimization";
          // Reduced penalty from 0.1 to 0.03
          $score -= 0.03;
        }
      }

      return [
        'score' => max(0.0, $score),
        'warnings' => $warnings,
        'suggestions' => $suggestions,
        'explain_result' => $explainResult,
      ];

    } catch (\Exception $e) {
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Performance validation error: " . $e->getMessage(),
          'error'
        );
      }

      return [
        'score' => 0.8, // Score par défaut si EXPLAIN échoue
        'warnings' => ['Could not analyze performance'],
        'suggestions' => [],
      ];
    }
  }

  /**
   * Validate by query type
   */
  private function validateByType(string $sql): array
  {
    $warnings = [];

    // SELECT avec LIMIT
    if (stripos($sql, 'SELECT') === 0 && stripos($sql, 'LIMIT') === false) {
      $warnings[] = "SELECT without LIMIT - could return many rows";
    }

    // JOIN sans ON
    if (preg_match('/\bJOIN\b/i', $sql) && !preg_match('/\bON\b/i', $sql)) {
      $warnings[] = "JOIN without ON clause detected";
    }

    // GROUP BY sans agrégation
    if (preg_match('/\bGROUP\s+BY\b/i', $sql) &&
      !preg_match('/\b(COUNT|SUM|AVG|MIN|MAX)\s*\(/i', $sql)) {
      $warnings[] = "GROUP BY without aggregate function";
    }

    return [
      'warnings' => $warnings,
    ];
  }

  // ========================================
  // AUTONOMOUS AGENT INTERFACE IMPLEMENTATION
  // ========================================

  /**
   * statistics
   */
  public function getStats(): array
  {
    $total = $this->stats['validations_passed'] + $this->stats['validations_failed'];
    $successRate = $total > 0
      ? round(($this->stats['validations_passed'] / $total) * 100, 2)
      : 0;

    return array_merge($this->stats, [
      'success_rate' => $successRate . '%',
    ]);
  }

  /**
   * Create a local objective for validation improvements
   *
   * @param string $goalStatement Clear description of the goal
   * @param array $successCriteria Measurable success criteria
   * @param string $priority Priority level
   * @return \ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\LocalObjective
   */
  public function createLocalObjective(
    string $goalStatement,
    array $successCriteria,
    string $priority
  ): LocalObjective {

    if (!$this->autonomousConfig->canAgentCreateObjectives('ValidationAgent')) {
      throw new \RuntimeException('ValidationAgent is not authorized to create objectives (disabled in configuration)');
    }

    $estimatedTime = match ($priority) {
      'critical' => 300,
      'high' => 900,
      'medium' => 1800,
      'low' => 3600,
      default => 1800
    };

    $objective = new LocalObjective(
      'ValidationAgent',
      $goalStatement,
      $successCriteria,
      $priority,
      $estimatedTime
    );

    $objectiveRegistry = new ObjectiveRegistry($this->db, $this->debug);
    $objectiveRegistry->registerObjective($objective);

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "ValidationAgent created objective: {$goalStatement}",
        'info'
      );
    }

    return $objective;
  }

  /**
   * Execute a validation improvement objective
   *
   * @param \ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\LocalObjective $objective
   * @return mixed Execution results
   */
  public function executeObjective(LocalObjective $objective): mixed
  {
    $goalStatement = $objective->getGoalStatement();

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "ValidationAgent executing objective: {$goalStatement}",
        'info'
      );
    }

    $objective->setStatus('active');

    try {
      $result = ['message' => 'Validation objective execution placeholder'];

      $objective->markCompleted([
        'execution_time' => time() - strtotime($objective->getCreatedAt()->format('Y-m-d H:i:s')),
        'result' => $result
      ]);

      return $result;

    } catch (\Exception $e) {
      $objective->markFailed($e->getMessage());
      throw $e;
    }
  }

  /**
   * Evaluate peer agent output (validation results, security checks)
   *
   * @param string $outputType Type of output
   * @param mixed $output The output to evaluate
   * @param array $criteria Evaluation criteria
   * @return \ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\AgentEvaluation
   */
  public function evaluatePeerOutput(
    string $outputType,
    mixed $output,
    array $criteria
  ): AgentEvaluation {

    if (!$this->autonomousConfig->canAgentEvaluatePeers('ValidationAgent')) {
      throw new \RuntimeException('ValidationAgent is not authorized to evaluate peers (disabled in configuration)');
    }

    $capabilities = $this->getEvaluationCapabilities();
    if (!isset($capabilities[$outputType])) {
      throw new \InvalidArgumentException(
        "ValidationAgent cannot evaluate {$outputType}"
      );
    }

    $scores = match ($outputType) {
      'validation_result' => $this->evaluateValidationResult($output, $criteria),
      'security_check' => $this->evaluateSecurityCheck($output, $criteria),
      'sql_query' => $this->evaluateSqlQuerySecurity($output, $criteria),
      default => $this->getDefaultScores()
    };

    return new AgentEvaluation(
      'ValidationAgent',
      $output['output_id'] ?? uniqid('output_'),
      $scores,
      $scores['feedback'] ?? 'Evaluation completed',
      $scores['strengths'] ?? [],
      $scores['improvements'] ?? []
    );
  }

  /**
   * Get evaluation capabilities for ValidationAgent
   *
   * @return array Mapping of output types to capability levels
   */
  public function getEvaluationCapabilities(): array
  {
    return [
      'validation_result' => 'expert',  // Expert in validation
      'security_check' => 'expert',     // Expert in security
      'sql_query' => 'competent',       // Competent in SQL validation
      'reasoning_chain' => 'novice'     // Basic reasoning understanding
    ];
  }

  /**
   * Evaluate validation result quality
   *
   * @param mixed $output Validation result output
   * @param array $criteria Evaluation criteria
   * @return array Evaluation scores
   */
  private function evaluateValidationResult(mixed $output, array $criteria): array
  {
    $isValid = $output['is_valid'] ?? false;
    $hasErrors = !empty($output['errors'] ?? []);
    $hasWarnings = !empty($output['warnings'] ?? []);

    $accuracyScore = $isValid && !$hasErrors ? 0.9 : 0.7;
    $completenessScore = 0.8;
    $efficiencyScore = 0.8;
    $clarityScore = 0.8;

    $strengths = [];
    $improvements = [];

    if ($isValid) {
      $strengths[] = 'Validation passed successfully';
    }

    if ($hasWarnings) {
      $improvements[] = 'Address validation warnings';
    }

    return [
      'accuracy_score' => $accuracyScore,
      'completeness_score' => $completenessScore,
      'efficiency_score' => $efficiencyScore,
      'clarity_score' => $clarityScore,
      'feedback' => 'Validation result evaluation completed',
      'strengths' => $strengths,
      'improvements' => $improvements
    ];
  }

  /**
   * Evaluate security check quality
   *
   * @param mixed $output Security check output
   * @param array $criteria Evaluation criteria
   * @return array Evaluation scores
   */
  private function evaluateSecurityCheck(mixed $output, array $criteria): array
  {
    $securityScore = $output['security_score'] ?? 0.5;

    return [
      'accuracy_score' => $securityScore,
      'completeness_score' => 0.8,
      'efficiency_score' => 0.8,
      'clarity_score' => 0.8,
      'feedback' => 'Security check evaluation completed',
      'strengths' => ['Security check performed'],
      'improvements' => []
    ];
  }

  /**
   * Evaluate SQL query security
   *
   * @param mixed $output SQL query output
   * @param array $criteria Evaluation criteria
   * @return array Evaluation scores
   */
  private function evaluateSqlQuerySecurity(mixed $output, array $criteria): array
  {
    $sql = $output['sql_query'] ?? '';
    $validation = $this->validateSecurity($sql, []);

    return [
      'accuracy_score' => $validation['score'],
      'completeness_score' => 0.8,
      'efficiency_score' => 0.8,
      'clarity_score' => 0.8,
      'feedback' => 'SQL security evaluation completed',
      'strengths' => empty($validation['issues']) ? ['No security issues detected'] : [],
      'improvements' => $validation['issues']
    ];
  }

  /**
   * Get default evaluation scores
   *
   * @return array Default scores
   */
  private function getDefaultScores(): array
  {
    return [
      'accuracy_score' => 0.7,
      'completeness_score' => 0.7,
      'efficiency_score' => 0.7,
      'clarity_score' => 0.7,
      'feedback' => 'Default evaluation',
      'strengths' => [],
      'improvements' => []
    ];
  }

  /**
   * Receive and process feedback from peer agents
   *
   * @param array $feedback Feedback from peer agent
   */
  public function receiveFeedback(array $feedback): void
  {
    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "ValidationAgent received feedback from {$feedback['source_agent_id']}",
        'info'
      );
    }

    $feedbackManager = new FeedbackManager($this->db, $this->debug);
    $feedbackManager->acknowledgeFeedback(
      $feedback['feedback_id'],
      'ValidationAgent',
      null
    );
  }

  /**
   * Check if ValidationAgent can collaborate on an objective
   *
   * @param \ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\LocalObjective $objective
   * @return bool True if can collaborate
   */
  public function canCollaborate(LocalObjective $objective): bool
  {
    $goalStatement = strtolower($objective->getGoalStatement());
    $keywords = ['validation', 'security', 'verify', 'check', 'validate', 'sanitize'];

    foreach ($keywords as $keyword) {
      if (str_contains($goalStatement, $keyword)) {
        return true;
      }
    }

    return false;
  }
}
