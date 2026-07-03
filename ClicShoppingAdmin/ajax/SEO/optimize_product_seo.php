<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\HTTP;
  use ClicShopping\OM\Registry;
  use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
  use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoMultilingualOrchestrator;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoEntityAdapter;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoOriginalSnapshotRepository;

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

  if (!isset($_POST['seo_run_optimize'])) {
    echo json_encode(['success' => false, 'error' => 'Missing optimize action.']);
    exit;
  }

  $productId = (int)($_POST['seo_product_id'] ?? 0);

  if ($productId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid product id.']);
    exit;
  }

  // HARD PRECONDITION: preserve the genuine original (write-once) for EVERY enabled
  // language BEFORE the orchestrator overwrites anything. If we cannot save the
  // original, abort — never optimize a description we could not roll back.
  try {
    $snapshotAdapter = new SeoEntityAdapter('product');
    $snapshotRepo    = new SeoOriginalSnapshotRepository();

    foreach (Registry::get('Language')->getAll() as $languageRow) {
      $lid = (int)($languageRow['id'] ?? 0);
      if ($lid <= 0) {
        continue;
      }
      $currentContent = $snapshotAdapter->getCurrentData($productId, $lid);
      if ($currentContent !== null) {
        $snapshotRepo->captureIfAbsent('product', $productId, $lid, $currentContent);
      }
    }
    
    //    SeoOriginalSnapshotRepository::captureEntityOriginals('product', $productId);

  } catch (\Throwable $e) {
    echo json_encode([
      'success' => false,
      'error'   => 'Could not preserve the original content; optimization aborted. ' . $e->getMessage(),
    ]);
    exit;
  }

  // The new multilingual workflow no longer reads $_POST['language_id']:
  // the orchestrator iterates every enabled language itself, starting from
  // English (code = 'en') as the canonical source and propagating translations.
  $baseUrl = HTTP::getShopUrlDomain();

  try {
    $orchestrator = new SeoMultilingualOrchestrator('product');

    $result = $orchestrator->run(
      entityId:    $productId,
      baseUrl:     $baseUrl,
      triggeredBy: 'ajax'
    );

    echo json_encode($result);
  } catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  }
  exit;
