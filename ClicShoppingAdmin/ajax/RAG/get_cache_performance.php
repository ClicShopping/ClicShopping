<?php
/**
 * AJAX endpoint to get cache performance metrics over time
 * Returns data for performance charts in the Cache tab
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../Core/ClicShopping/') . DIRECTORY_SEPARATOR);

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('ClicShoppingAdmin');
AdministratorAdmin::hasUserAccess();

header('Content-Type: application/json');

try {
  $db = Registry::get('Db');
  $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
  
  // ============================================================================
  // 1. HIT/MISS RATE OVER TIME
  // ============================================================================
  $hitMissQuery = $db->prepare('
    SELECT 
      DATE(date_added) as date,
      SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) as hits,
      SUM(CASE WHEN cache_hit = 0 THEN 1 ELSE 0 END) as misses,
      COUNT(*) as total,
      ROUND((SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as hit_rate
    FROM :table_rag_statistics
    WHERE date_added >= DATE_SUB(NOW(), INTERVAL :days DAY)
    GROUP BY DATE(date_added)
    ORDER BY date ASC
  ');
  $hitMissQuery->bindInt(':days', $days);
  $hitMissQuery->execute();
  
  $hitMissData = [
    'labels' => [],
    'hits' => [],
    'misses' => [],
    'hit_rate' => []
  ];
  
  while ($row = $hitMissQuery->fetch()) {
    $hitMissData['labels'][] = date('M d', strtotime($row['date']));
    $hitMissData['hits'][] = (int)$row['hits'];
    $hitMissData['misses'][] = (int)$row['misses'];
    $hitMissData['hit_rate'][] = (float)$row['hit_rate'];
  }
  
  // ============================================================================
  // 2. API COST SAVINGS OVER TIME
  // ============================================================================
  $costSavingsQuery = $db->prepare('
    SELECT 
      DATE(date_added) as date,
      SUM(CASE WHEN cache_hit = 1 THEN api_cost_usd ELSE 0 END) as cost_saved,
      SUM(CASE WHEN cache_hit = 0 THEN api_cost_usd ELSE 0 END) as cost_spent,
      SUM(api_cost_usd) as total_cost
    FROM :table_rag_statistics
    WHERE date_added >= DATE_SUB(NOW(), INTERVAL :days DAY)
      AND api_cost_usd IS NOT NULL
    GROUP BY DATE(date_added)
    ORDER BY date ASC
  ');
  $costSavingsQuery->bindInt(':days', $days);
  $costSavingsQuery->execute();
  
  $costSavingsData = [
    'labels' => [],
    'cost_saved' => [],
    'cost_spent' => [],
    'total_cost' => []
  ];
  
  while ($row = $costSavingsQuery->fetch()) {
    $costSavingsData['labels'][] = date('M d', strtotime($row['date']));
    $costSavingsData['cost_saved'][] = (float)$row['cost_saved'];
    $costSavingsData['cost_spent'][] = (float)$row['cost_spent'];
    $costSavingsData['total_cost'][] = (float)$row['total_cost'];
  }
  
  // ============================================================================
  // 3. AVERAGE RESPONSE TIME OVER TIME
  // ============================================================================
  $responseTimeQuery = $db->prepare('
    SELECT 
      DATE(date_added) as date,
      AVG(CASE WHEN cache_hit = 1 THEN response_time_ms ELSE NULL END) as avg_cached_time,
      AVG(CASE WHEN cache_hit = 0 THEN response_time_ms ELSE NULL END) as avg_uncached_time,
      AVG(response_time_ms) as avg_total_time
    FROM :table_rag_statistics
    WHERE date_added >= DATE_SUB(NOW(), INTERVAL :days DAY)
      AND response_time_ms IS NOT NULL
    GROUP BY DATE(date_added)
    ORDER BY date ASC
  ');
  $responseTimeQuery->bindInt(':days', $days);
  $responseTimeQuery->execute();
  
  $responseTimeData = [
    'labels' => [],
    'cached' => [],
    'uncached' => [],
    'average' => []
  ];
  
  while ($row = $responseTimeQuery->fetch()) {
    $responseTimeData['labels'][] = date('M d', strtotime($row['date']));
    $responseTimeData['cached'][] = $row['avg_cached_time'] ? round((float)$row['avg_cached_time']) : null;
    $responseTimeData['uncached'][] = $row['avg_uncached_time'] ? round((float)$row['avg_uncached_time']) : null;
    $responseTimeData['average'][] = $row['avg_total_time'] ? round((float)$row['avg_total_time']) : null;
  }
  
  // ============================================================================
  // 4. CACHE SIZE BY TYPE
  // ============================================================================
  // Get file cache sizes from actual Rag cache folders
  $cacheDir = CLICSHOPPING::BASE_DIR . 'Work/Cache/Rag/';
  $cacheSizeData = [
    'labels' => [],
    'sizes' => [],
    'file_counts' => []
  ];
  
  $cacheTypes = [
    'Embeddings' => $cacheDir . 'Embeddings/',
    'Semantic' => $cacheDir . 'Semantic/',
    'SQL' => $cacheDir . 'SQL/',
    'Intent' => $cacheDir . 'Intent/',
    'Translation' => $cacheDir . 'Translation/',
    'Classification' => $cacheDir . 'Classification/',
    'Context' => $cacheDir . 'Context/',
    'Ambiguity' => $cacheDir . 'Ambiguity/',
    'SchemaQuery' => $cacheDir . 'SchemaQuery/',
    'Hybrid' => $cacheDir . 'Hybrid/',
    'Security' => $cacheDir . 'Security/'
  ];
  
  foreach ($cacheTypes as $label => $dir) {
    if (is_dir($dir)) {
      $files = glob($dir . '*.cache');
      $fileCount = count($files);
      $totalSize = 0;
      
      foreach ($files as $file) {
        if (file_exists($file)) {
          $totalSize += filesize($file);
        }
      }
      
      $cacheSizeData['labels'][] = $label;
      $cacheSizeData['sizes'][] = round($totalSize / 1024 / 1024, 2); // MB
      $cacheSizeData['file_counts'][] = $fileCount;
    }
  }
  
  // ============================================================================
  // RETURN RESPONSE
  // ============================================================================
  echo json_encode([
    'success' => true,
    'data' => [
      'hit_miss' => $hitMissData,
      'cost_savings' => $costSavingsData,
      'response_time' => $responseTimeData,
      'cache_size' => $cacheSizeData
    ]
  ], JSON_PRETTY_PRINT);

} catch (\Exception $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage()
  ], JSON_PRETTY_PRINT);
}
