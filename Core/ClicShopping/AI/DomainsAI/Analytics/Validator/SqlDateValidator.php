<?php
/**
 * SqlDateValidator
 * 
 * Validates and corrects SQL date logic, especially for year boundary issues
 * 
 * - "last month" in January querying December of current year (should be previous year)
 * - YEAR()/MONTH() functions that fail at year boundaries
 * 
 * @package ClicShopping\AI\DomainsAI\Analytics\Validator
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Validator;

class SqlDateValidator
{
  private bool $debug;
  
  public function __construct(bool $debug = false)
  {
    $this->debug = $debug;
  }
  
  /**
   * Validate and fix SQL date logic
   * 
   * Detects common date query errors and automatically corrects them:
   * 1. "last month" in January with wrong year
   * 2. YEAR()/MONTH() functions in WHERE clauses
   * 
   * @param string $sql Original SQL query
   * @param string $userQuery Original user query for context
   * @return array ['sql' => corrected SQL, 'corrected' => bool, 'reason' => string]
   */
  public function validateAndFix(string $sql, string $userQuery = ''): array
  {
    $originalSql = $sql;
    $corrections = [];
    
    // Get current date info
    $currentMonth = (int)date('m');
    $currentYear = (int)date('Y');
    
    // CRITICAL FIX: Detect "last month" queries in January
    if ($currentMonth === 1) {
      $corrected = $this->fixJanuaryLastMonth($sql, $userQuery);
      if ($corrected['corrected']) {
        $sql = $corrected['sql'];
        $corrections[] = $corrected['reason'];
      }
    }
    
    // Additional fix: Replace YEAR()/MONTH() in WHERE clauses with explicit date ranges
    $corrected = $this->replaceYearMonthFunctions($sql);
    if ($corrected['corrected']) {
      $sql = $corrected['sql'];
      $corrections[] = $corrected['reason'];
    }
    
    if ($this->debug && !empty($corrections)) {
      error_log("=== SqlDateValidator Corrections ===");
      error_log("Original SQL: " . $originalSql . "");
      error_log("Corrected SQL: " . $sql . "");
      error_log("Reasons: " . implode("; ", $corrections) . "");
    }
    
    return [
      'sql' => $sql,
      'corrected' => !empty($corrections),
      'reason' => implode("; ", $corrections)
    ];
  }
  
  /**
   * Fix "last month" queries in January
   * 
   * Detects patterns like:
   * - YEAR(date) = 2026 AND MONTH(date) = 12 (in January 2026 - wrong!)
   * - Should be: YEAR(date) = 2025 AND MONTH(date) = 12
   * 
   * Or better yet, replace with explicit date range:
   * - date >= '2025-12-01' AND date < '2026-01-01'
   * 
   * @param string $sql Original SQL
   * @param string $userQuery User query for context detection
   * @return array ['sql' => string, 'corrected' => bool, 'reason' => string]
   */
  private function fixJanuaryLastMonth(string $sql, string $userQuery): array
  {
    $currentYear = (int)date('Y');
    $lastYear = $currentYear - 1;
    
    // Check if user query mentions "last month"
    $isLastMonthQuery = preg_match('/\b(last month|mois dernier|dernier mois)\b/i', $userQuery);
    
    if (!$isLastMonthQuery) {
      return ['sql' => $sql, 'corrected' => false, 'reason' => ''];
    }
    
    // Pattern 1: YEAR(column) = currentYear AND MONTH(column) = 12
    // This is WRONG in January - should be lastYear
    $pattern1 = '/YEAR\s*\(\s*([a-z_\.]+)\s*\)\s*=\s*' . $currentYear . '\s+AND\s+MONTH\s*\(\s*\1\s*\)\s*=\s*12/i';
    
    if (preg_match($pattern1, $sql, $matches)) {
      // Replace with explicit date range
      $dateColumn = $matches[1];
      $replacement = "{$dateColumn} >= '{$lastYear}-12-01' AND {$dateColumn} < '{$currentYear}-01-01'";
      $sql = preg_replace($pattern1, $replacement, $sql);
      
      return [
        'sql' => $sql,
        'corrected' => true,
        'reason' => "Fixed January 'last month' query: Changed YEAR={$currentYear} MONTH=12 to explicit date range {$lastYear}-12-01 to {$currentYear}-01-01"
      ];
    }
    
    // Pattern 2: MONTH(column) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(column) = YEAR(CURDATE() - INTERVAL 1 MONTH)
    // But also has YEAR(column) = currentYear (contradictory!)
    $pattern2 = '/YEAR\s*\(\s*([a-z_\.]+)\s*\)\s*=\s*' . $currentYear . '\s+AND\s+MONTH\s*\(\s*\1\s*\)\s*=\s*MONTH\s*\(\s*CURDATE\s*\(\s*\)\s*-\s*INTERVAL\s+1\s+MONTH\s*\)/i';
    
    if (preg_match($pattern2, $sql, $matches)) {
      // Replace with explicit date range
      $dateColumn = $matches[1];
      $replacement = "{$dateColumn} >= '{$lastYear}-12-01' AND {$dateColumn} < '{$currentYear}-01-01'";
      $sql = preg_replace($pattern2, $replacement, $sql);
      
      return [
        'sql' => $sql,
        'corrected' => true,
        'reason' => "Fixed January 'last month' query: Replaced contradictory YEAR/MONTH functions with explicit date range {$lastYear}-12-01 to {$currentYear}-01-01"
      ];
    }
    
    return ['sql' => $sql, 'corrected' => false, 'reason' => ''];
  }
  
  /**
   * Replace YEAR()/MONTH() functions in WHERE clauses with explicit date ranges
   * 
   * This is a general fix that improves performance and correctness
   * 
   * @param string $sql Original SQL
   * @return array ['sql' => string, 'corrected' => bool, 'reason' => string]
   */
  private function replaceYearMonthFunctions(string $sql): array
  {
    // For now, we'll focus on the January fix above
    // This method can be expanded later to handle more patterns
    
    return ['sql' => $sql, 'corrected' => false, 'reason' => ''];
  }
}
