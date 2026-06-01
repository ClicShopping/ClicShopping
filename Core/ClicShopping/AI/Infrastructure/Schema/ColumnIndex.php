<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Schema;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * ColumnIndex
 * 
 * Builds and maintains an inverted index of column names and comments
 * for dynamic table selection based on query keywords
 * 
 * Pure LLM Mode - NO synonym expansion, NO pattern matching
 * The LLM handles understanding - this class only provides raw data
 * 
 * @package ClicShopping\AI\Infrastructure\Schema
 */
class ColumnIndex
{
  private mixed $db;
  private array $columnToTables = [];
  private bool $debug;
  
  // Statistics
  private int $totalTables = 0;
  private int $totalColumns = 0;
  private int $columnsWithComments = 0;
  
  /**
   * Constructor
   * 
   * @param bool $debug Debug mode flag
   */
  public function __construct(bool $debug = false)
  {
    $this->db = Registry::get('Db');
    $this->debug = $debug;
  }
  
  /**
   * Build the column index from database schema
   * 
   * @return void
   */
  public function build(): void
  {
    $startTime = microtime(true);
    
    if ($this->debug) {
      error_log("[ColumnIndex] Building column index...");
    }
    
    $this->columnToTables = [];
    $this->totalTables = 0;
    $this->totalColumns = 0;
    $this->columnsWithComments = 0;

    // Get all clic_ tables
    $prefix = CLICSHOPPING::getConfig('prefix_table');
    $Qtables = $this->db->query("SHOW TABLES LIKE $prefix'%'");
    
    $tablesList = [];

    while ($Qtables->fetch()) {
      // Get first column value (table name)
      $row = $Qtables->toArray();
      if (!empty($row)) {
        $tablesList[] = reset($row); // Get first value
      }
    }
    
    foreach ($tablesList as $tableName) {
      // Skip technical tables
      if (str_contains($tableName, '_embedding') || str_starts_with($tableName, $prefix . 'rag_')) {
        continue;
      }
      
      $this->totalTables++;
      
      // Get columns with comments
      $Qcolumns = $this->db->query("SHOW FULL COLUMNS FROM {$tableName}");
      
      while ($Qcolumns->fetch()) {
        $this->totalColumns++;
        $columnName = $Qcolumns->value('Field');
        $comment = $Qcolumns->value('Comment');
        
        if (!empty($comment)) {
          $this->columnsWithComments++;
        }
        
        // Extract keywords from column name and comment
        $text = $columnName . ' ' . $comment;
        $keywords = $this->extractKeywords($text);
        
        foreach ($keywords as $keyword) {
          if (!isset($this->columnToTables[$keyword])) {
            $this->columnToTables[$keyword] = [];
          }
          $this->columnToTables[$keyword][] = [
            'table' => $tableName,
            'column' => $columnName
          ];
        }
      }
    }
    
    $duration = round((microtime(true) - $startTime) * 1000, 2);
    
    if ($this->debug) {
      error_log("[ColumnIndex] Index built with " . count($this->columnToTables) . " keywords in {$duration}ms");
    }
  }
  
  /**
   * Find tables that match a keyword
   * 
   * @param string $keyword Keyword to search
   * @return array Array of table/column matches
   */
  public function find(string $keyword): array
  {
    $keyword = strtolower($keyword);
    return $this->columnToTables[$keyword] ?? [];
  }
  
  /**
   * Extract keywords from text (Pure LLM mode - simplified)
   * 
   * @param string $text Text to extract keywords from
   * @return array Array of keywords
   */
  private function extractKeywords(string $text): array
  {
    // Pure LLM Mode - NO synonym expansion
    $text = strtolower($text);
    
    // Remove special chars and split
    $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
    $words = preg_split('/\s+/', $text);
    
    // Filter words longer than 3 characters
    $keywords = [];
    foreach ($words as $word) {
      if (strlen($word) > 3) {
        $keywords[] = $word;
      }
    }
    
    return array_unique($keywords);
  }
  
  /**
   * Get all indexed keywords
   * 
   * @return array Array of keywords
   */
  public function getKeywords(): array
  {
    return array_keys($this->columnToTables);
  }
  
  /**
   * Get index statistics
   * 
   * @return array Statistics
   */
  public function getStats(): array
  {
    $totalMatches = 0;
    foreach ($this->columnToTables as $matches) {
      $totalMatches += count($matches);
    }
    
    return [
      'total_tables' => $this->totalTables,
      'total_columns' => $this->totalColumns,
      'columns_with_comments' => $this->columnsWithComments,
      'total_keywords' => count($this->columnToTables),
      'total_matches' => $totalMatches
    ];
  }
}
