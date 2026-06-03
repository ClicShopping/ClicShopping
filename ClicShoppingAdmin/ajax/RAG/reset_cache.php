<?php
/**
 * AJAX Endpoint: Reset Cache
 * Handles cache reset requests from the Dashboard.
 *
 * Accepts three logical reset scopes (cache_types):
 *   - db    : truncate the RAG cache tables (query cache + web embedding cache)
 *   - disk  : purge every AI/RAG file cache under Work/Cache/Rag/
 *   - logs  : delete the *.log files under Work/Log/
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 * @date 2025-11-17
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

// Bootstrap
define('PAGE_PARSE_START_TIME', microtime());
define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../Core/ClicShopping/') . '/');

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');

spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('ClicShoppingAdmin');
//AdministratorAdmin::hasUserAccess();

// Set JSON header
header('Content-Type: application/json');

try {
  // Get request data
  $input = file_get_contents('php://input');
  $data = json_decode($input, true);

  if (!$data || !isset($data['cache_types']) || !is_array($data['cache_types'])) {
    echo json_encode([
      'success' => false,
      'message' => 'Invalid request data'
    ]);
    exit;
  }

  $cacheTypes = $data['cache_types'];
  $results = [];
  $errors = [];

  $shopRoot = CLICSHOPPING::getConfig('dir_root', 'Shop');

  /**
   * Recursively delete every file under a directory (the directory tree itself is kept).
   * Returns the number of files removed.
   */
  $purgeFiles = static function (string $dir): int {
    if (!is_dir($dir)) {
      return 0;
    }

    $deleted = 0;
    $items = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
      if (($item->isFile() || $item->isLink()) && @unlink($item->getPathname())) {
        $deleted++;
      }
    }

    return $deleted;
  };

  // ============================================================================
  // 1. DB cache — RAG cache tables (query cache + web search embedding cache)
  //    "Réinitialiser le cache DB (supprime tous les cache AI, rag)"
  // ============================================================================
  if (in_array('db', $cacheTypes, true)) {
    try {
      $db = Registry::get('Db');
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
      $deleted = 0;

      foreach (['rag_query_cache', 'rag_web_cache_embedding'] as $table) {
        $fullTable = $prefix . $table;

        // Only touch tables that actually exist (graceful on partial installs)
        $check = $db->query("SHOW TABLES LIKE '" . $fullTable . "'");
        if ($check === false || $check->fetch() === false) {
          continue;
        }

        $count = $db->query("SELECT COUNT(*) AS count FROM `" . $fullTable . "`")->fetch()['count'] ?? 0;
        $db->query("TRUNCATE TABLE `" . $fullTable . "`");
        $deleted += (int)$count;
      }

      $results['db'] = $deleted;
      error_log("Cache Reset: DB cache flushed - {$results['db']} entries deleted");
    } catch (Exception $e) {
      $errors[] = "DB cache: " . $e->getMessage();
      error_log("Cache Reset Error (db): " . $e->getMessage());
    }
  }

  // ============================================================================
  // 2. Disk cache — all AI/RAG file caches under Work/Cache/Rag/
  //    "Réinitialiser le cache disque AI (Rag)"
  // ============================================================================
  if (in_array('disk', $cacheTypes, true)) {
    try {
      $results['disk'] = $purgeFiles($shopRoot . 'Work/Cache/Rag/');
      error_log("Cache Reset: Disk AI cache flushed - {$results['disk']} files deleted");
    } catch (Exception $e) {
      $errors[] = "Disk cache: " . $e->getMessage();
      error_log("Cache Reset Error (disk): " . $e->getMessage());
    }
  }

  // ============================================================================
  // 3. Logs — *.log files under Work/Log/
  //    "Réinitialiser les logs"
  // ============================================================================
  if (in_array('logs', $cacheTypes, true)) {
    try {
      $logDir = $shopRoot . 'Work/Log/';
      $deleted = 0;

      if (is_dir($logDir)) {
        $items = new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator($logDir, RecursiveDirectoryIterator::SKIP_DOTS),
          RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
          if ($item->isFile() && strtolower($item->getExtension()) === 'log' && @unlink($item->getPathname())) {
            $deleted++;
          }
        }
      }

      $results['logs'] = $deleted;
      error_log("Cache Reset: Logs flushed - {$results['logs']} files deleted");
    } catch (Exception $e) {
      $errors[] = "Logs: " . $e->getMessage();
      error_log("Cache Reset Error (logs): " . $e->getMessage());
    }
  }

  // ============================================================================
  // Return Response
  // ============================================================================
  if (empty($errors)) {
    echo json_encode([
      'success' => true,
      'message' => 'Cache reset successfully',
      'details' => $results
    ]);
  } else {
    echo json_encode([
      'success' => false,
      'message' => 'Some caches could not be reset',
      'details' => $results,
      'errors' => $errors
    ]);
  }

} catch (Exception $e) {
  error_log("Cache Reset Fatal Error: " . $e->getMessage());
  echo json_encode([
    'success' => false,
    'message' => 'Fatal error: ' . $e->getMessage()
  ]);
}
