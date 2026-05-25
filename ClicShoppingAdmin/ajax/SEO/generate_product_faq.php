<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\HTTP;
  use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Faq\SeoFaqPipeline;

  // Phase 3 (FAQ generation + grounding + per-locale translation) runs every
  // grounded retry sequentially and re-verifies after translation, so it can
  // take several minutes on shops with many enabled languages.  Keep the
  // process alive until the orchestrator returns.
  set_time_limit(0);
  ini_set('max_execution_time', '0');
  ignore_user_abort(true);

  define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../Core/ClicShopping/') . DIRECTORY_SEPARATOR);

  require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
  spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

  CLICSHOPPING::initialize();
  CLICSHOPPING::loadSite('ClicShoppingAdmin');
AdministratorAdmin::hasUserAccess();

  header('Content-Type: application/json; charset=utf-8');

  if (!Gpt::checkGptStatus()) {
    echo json_encode(['success' => false, 'error' => 'ChatGPT is not enabled.']);
    exit;
  }

  if (!isset($_POST['seo_run_faq'])) {
    echo json_encode(['success' => false, 'error' => 'Missing FAQ generation action.']);
    exit;
  }

  $productId = (int)($_POST['seo_product_id'] ?? 0);

  if ($productId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid product id.']);
    exit;
  }

  try {
    $pipeline = new SeoFaqPipeline('product');

    $result = $pipeline->run(
      entityId:    $productId,
      baseUrl:     HTTP::getShopUrlDomain(),
      triggeredBy: 'ajax'
    );

    echo json_encode($result);
  } catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  }
  exit;
