<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoSerpReportRepository;

  define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../Core/ClicShopping/') . DIRECTORY_SEPARATOR);
  require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
  spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');
  CLICSHOPPING::initialize();
  CLICSHOPPING::loadSite('ClicShoppingAdmin');
  
  AdministratorAdmin::hasUserAccess();

  header('Content-Type: application/json; charset=utf-8');

  if (!isset($_POST['seo_accept'])) {
    echo json_encode(['success' => false, 'error' => 'Missing accept action.']);
    exit;
  }
  $productId = (int)($_POST['seo_product_id'] ?? 0);
  
  if ($productId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid product id.']);
    exit;
  }

  try {
    (new SeoSerpReportRepository())->markLatestStatus('product', $productId, 'accepted');
    echo json_encode(['success' => true, 'mode' => 'accepted']);
  } catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  }
  
  exit;
