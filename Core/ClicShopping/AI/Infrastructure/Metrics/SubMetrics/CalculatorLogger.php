<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Metrics\SubMetrics;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

/**
 * CalculatorLogger
 *
 * DB-backed calculation audit log for CalculatorTool. Extracted verbatim from
 * CalculatorTool (2026-06-20) — owns the logging concern only (record, query,
 * stats, prune). Dependencies injected by CalculatorTool.
 */
class CalculatorLogger
{
  private mixed $db;
  private SecurityLogger $securityLogger;
  private bool $debug;
  private bool $enableLogging;

  public function __construct(mixed $db, SecurityLogger $securityLogger, bool $debug, bool $enableLogging)
  {
    $this->db = $db;
    $this->securityLogger = $securityLogger;
    $this->debug = $debug;
    $this->enableLogging = $enableLogging;
  }

  /**
   * Log calculation
   * 
   * @param string $expression Expression
   * @param float|null $result Result
   * @param bool $success Success status
   * @param string|null $errorMessage Error message
   * @param float $executionTime Execution time
   * @param string|null $stepId Step ID
   * @param string|null $planId Plan ID
   * @param array|null $metadata Metadata
   * @return bool Success
   */
  public function logCalculation(
    string $expression,
    ?float $result,
    bool $success,
    ?string $errorMessage,
    float $executionTime,
    ?string $stepId = null,
    ?string $planId = null,
    ?array $metadata = null
  ): bool {
    if (!$this->db || !$this->enableLogging) {
      return false;
    }

    try {
      $sql = "INSERT INTO :table_rag_calculator_logs 
              (user_id, expression, result, success, error_message, 
               execution_time, step_id, plan_id, metadata, created_at) 
              VALUES 
              (:user_id, :expression, :result, :success, :error, 
               :exec_time, :step_id, :plan_id, :metadata, NOW())";

      $stmt = $this->db->prepare($sql);

      return $stmt->execute([
        'user_id' => $this->getUserId(),
        'expression' => $expression,
        'result' => $result,
        'success' => $success ? 1 : 0,
        'error' => $errorMessage,
        'exec_time' => $executionTime,
        'step_id' => $stepId,
        'plan_id' => $planId,
        'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
      ]);
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Logging error: " . $e->getMessage(),
          'error'
        );
      }
      return false;
    }
  }

  /**
   * Get calculation logs
   * 
   * @param int $limit Number of entries
   * @param array $filters Filters
   * @return array Log entries
   */
  public function getLogs(int $limit = 100, array $filters = []): array
  {
    if (!$this->db) {
      return [];
    }

    try {
      $where = [];
      $params = [];

      if (isset($filters['success'])) {
        $where[] = "success = :success";
        $params['success'] = $filters['success'] ? 1 : 0;
      }

      if (isset($filters['user_id'])) {
        $where[] = "user_id = :user_id";
        $params['user_id'] = $filters['user_id'];
      }

      if (isset($filters['plan_id'])) {
        $where[] = "plan_id = :plan_id";
        $params['plan_id'] = $filters['plan_id'];
      }

      if (isset($filters['from_date'])) {
        $where[] = "created_at >= :from_date";
        $params['from_date'] = $filters['from_date'];
      }

      $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

      $sql = "SELECT * FROM :table_rag_calculator_logs 
              {$whereClause}
              ORDER BY created_at DESC 
              LIMIT :limit";

      $stmt = $this->db->prepare($sql);

      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);

      $stmt->execute();

      return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Log retrieval error: " . $e->getMessage(),
        'error'
      );
      return [];
    }
  }

  /**
   * Get log statistics
   * 
   * @param array $filters Filters
   * @return array Log statistics
   */
  public function getLogStats(array $filters = []): array
  {
    if (!$this->db) {
      return ['enabled' => false];
    }

    try {
      $where = [];
      $params = [];

      if (isset($filters['user_id'])) {
        $where[] = "user_id = :user_id";
        $params['user_id'] = $filters['user_id'];
      }

      if (isset($filters['from_date'])) {
        $where[] = "created_at >= :from_date";
        $params['from_date'] = $filters['from_date'];
      }

      $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

      $sql = "SELECT 
                COUNT(*) as total_calculations,
                SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed,
                AVG(execution_time) as avg_execution_time,
                MIN(execution_time) as min_execution_time,
                MAX(execution_time) as max_execution_time
              FROM calculator_logs
              {$whereClause}";

      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();

      $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

      if ($stats['total_calculations'] > 0) {
        $stats['success_rate'] = round(
          ($stats['successful'] / $stats['total_calculations']) * 100,
          2
        );
      } else {
        $stats['success_rate'] = 0;
      }

      $stats['enabled'] = true;

      return $stats;
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Log stats error: " . $e->getMessage(),
        'error'
      );
      return ['enabled' => true, 'error' => $e->getMessage()];
    }
  }

  /**
   * Clean old logs
   * 
   * @param int $daysToKeep Days to keep
   * @return int Number of deleted entries
   */
  public function cleanLogs(int $daysToKeep = 30): int
  {
    if (!$this->db) {
      return 0;
    }

    try {
      $sql = "DELETE FROM :table_rag_calculator_logs 
              WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";

      $stmt = $this->db->prepare($sql);
      $stmt->execute(['days' => $daysToKeep]);

      $deleted = $stmt->rowCount();

      if ($this->debug && $deleted > 0) {
        $this->securityLogger->logSecurityEvent(
          "Cleaned {$deleted} old log entries",
          'info'
        );
      }

      return $deleted;
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Log cleaning error: " . $e->getMessage(),
        'error'
      );
      return 0;
    }
  }

  /**
   * Get current user ID
   * 
   * @return string User ID
   */
  private function getUserId(): string
  {
    AdministratorAdmin::getUserAdminId();

/*
    // Try to get user ID from session
    if (isset($_SESSION['customer_id'])) {
      return (string)$_SESSION['customer_id'];
    }

    if (Registry::exists('Customer')) {
      $customer = Registry::get('Customer');
      if (method_exists($customer, 'getId')) {
        return (string)$customer->getId();
      }
    }
*/
    return 'system';
  }
}
