<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\HTTP;
  use ClicShopping\OM\Registry;
  use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoEmbedding;

  define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../Core/ClicShopping/') . DIRECTORY_SEPARATOR);

  require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
  spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

  CLICSHOPPING::initialize();
  CLICSHOPPING::loadSite('ClicShoppingAdmin');

  header('Content-Type: application/json; charset=utf-8');
  AdministratorAdmin::hasUserAccess();
  
  if (!Gpt::checkGptStatus()) {
    echo json_encode(['success' => false, 'error' => 'ChatGPT is not enabled.']);
    exit;
  }

  $productId = (int)($_POST['seo_product_id'] ?? 0);

  if ($productId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid product id.']);
    exit;
  }

  $languageId = (int)($_POST['language_id'] ?? 0);
  if ($languageId <= 0) {
    $languageId = (int)Registry::get('Language')->getId();
  }

  $languageCode = Registry::get('Language')->getLanguageCodeById($languageId);
  $linkUrl = HTTP::getShopUrlDomain() . 'index.php?Products&Description&products_id=' . $productId . '&language=' . urlencode($languageCode);
  $baseUrl = HTTP::getShopUrlDomain();

  try {
    $repository = new SeoEmbedding('products_seo_embedding');

    $result = $repository->process(
      entityId: $productId,
      languageId: $languageId,
      url: $linkUrl,
      baseUrl: $baseUrl,
      pageType: 'product',
      triggeredBy: 'ajax'
    );

    echo json_encode($result);
  } catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  }
  exit;
