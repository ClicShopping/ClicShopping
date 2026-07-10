<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\Registry;
  use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoEntityAdapter;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoOriginalSnapshotRepository;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoSerpReportRepository;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoActionLogRepository;

  define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../Core/ClicShopping/') . DIRECTORY_SEPARATOR);
  require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
  spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');
  CLICSHOPPING::initialize();
  CLICSHOPPING::loadSite('ClicShoppingAdmin');

  AdministratorAdmin::hasUserAccess();

  header('Content-Type: application/json; charset=utf-8');

  if (!isset($_POST['seo_revert_initial'])) {
    echo json_encode(['success' => false, 'error' => 'Missing revert action.']);
    exit;
  }
  $categoryId = (int)($_POST['seo_category_id'] ?? 0);
  if ($categoryId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid category id.']);
    exit;
  }

  try {
    $adapter = new SeoEntityAdapter('category');
    $repo    = new SeoOriginalSnapshotRepository();

    if (!$repo->exists('category', $categoryId)) {
      echo json_encode(['success' => false, 'error' => 'No original snapshot to restore.']);
      exit;
    }

    $languageIds = [];
    foreach (Registry::get('Language')->getAll() as $row) {
      $lid = (int)($row['id'] ?? 0);
      if ($lid > 0) {
        $languageIds[] = $lid;
      }
    }

    // 1) Restore the original content (verbatim, all languages). Snapshot is KEPT.
    $repo->restoreAllLanguages($adapter, 'category', $categoryId, $languageIds);

    // 2) Purge the SEO analysis: reports + benchmark log (benchmark table may not exist).
    (new SeoSerpReportRepository())->deleteForEntity('category', $categoryId);

    try {
      $db = Registry::get('Db');
      $stmt = $db->prepare('DELETE FROM :table_seo_quality_benchmark_log
                            WHERE entity_type = :entity_type AND entity_id = :entity_id');
      $stmt->bindValue(':entity_type', 'category');
      $stmt->bindInt(':entity_id', $categoryId);
      $stmt->execute();
    } catch (\Throwable $ignored) {
    }

    try {
      (new SeoActionLogRepository())->record(
        'category',
        $categoryId,
        0,
        'reverted',
        AdministratorAdmin::getUserAdminId(),
        (string)($_SESSION['admin']['username'] ?? '')
      );
    } catch (\Throwable $ignored) {
    }

    echo json_encode(['success' => true, 'mode' => 'reset']);
  } catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  }

  exit;
