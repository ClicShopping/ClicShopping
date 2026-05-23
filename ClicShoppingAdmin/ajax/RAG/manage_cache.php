<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Infrastructure\Cache\QueryCache;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../Core/ClicShopping/') . DIRECTORY_SEPARATOR);

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('ClicShoppingAdmin');
AdministratorAdmin::hasUserAccess();

header('Content-Type: application/json');

try {
  $action = $_POST['action'] ?? $_GET['action'] ?? '';
  $db = Registry::get('Db');
  $queryCache = new QueryCache();
  
  switch ($action) {
    case 'flush':
    case 'flush_query_cache':
      $result = $queryCache->flush();
      
      // Count deleted entries
      $entriesDeleted = 0;
      if ($result) {
        $countQuery = $db->prepare("SELECT COUNT(*) as count FROM :table_rag_query_cache");
        $countQuery->execute();
        $countQuery->fetch();
        $entriesDeleted = $countQuery->valueInt('count');
      }
      
      echo json_encode([
        'success' => $result,
        'message' => $result ? 'Cache cleared successfully' : 'Error clearing cache',
        'entries_deleted' => $entriesDeleted
      ]);
      break;
      
    case 'stats':
      $stats = $queryCache->getStats();
      echo json_encode([
        'success' => true,
        'stats' => $stats
      ]);
      break;
      
    case 'invalidate':
      $userQuery = $_POST['query'] ?? '';
      if (empty($userQuery)) {
        echo json_encode([
          'success' => false,
          'error' => 'Query parameter required'
        ]);
        break;
      }
      
      $result = $queryCache->invalidate($userQuery);
      echo json_encode([
        'success' => $result,
        'message' => $result ? 'Cache entry invalidated' : 'Error invalidating cache'
      ]);
      break;
      
    default:
      echo json_encode([
        'success' => false,
        'error' => 'Invalid action. Use: flush, stats, or invalidate'
      ]);
  }
  
} catch (Exception $e) {
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage()
  ]);
}
