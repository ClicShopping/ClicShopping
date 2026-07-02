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

  if (!isset($_POST['seo_reject'])) {
    echo json_encode(['success' => false, 'error' => 'Missing reject action.']);
    exit;
  }
  $productId = (int)($_POST['seo_product_id'] ?? 0);
  if ($productId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid product id.']);
    exit;
  }

  try {
    $adapter = new SeoEntityAdapter('product');
    $repo    = new SeoOriginalSnapshotRepository();

    if (!$repo->exists('product', $productId)) {
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

    $repo->restoreAllLanguages($adapter, 'product', $productId, $languageIds);
    (new SeoSerpReportRepository())->markLatestStatus('product', $productId, 'rejected');

    // Best-effort audit trail (never fails the action).
    try {
      (new SeoActionLogRepository())->record(
        'product',
        $productId,
        0,
        'rejected',
        AdministratorAdmin::getUserAdminId(),
        (string)($_SESSION['admin']['username'] ?? '')
      );
    } catch (\Throwable $ignored) {
    }

    echo json_encode(['success' => true, 'mode' => 'rejected']);
  } catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  }
  
  exit;
