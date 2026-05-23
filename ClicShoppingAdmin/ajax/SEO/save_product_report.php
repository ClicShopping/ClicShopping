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

  set_time_limit(0);
  ini_set('max_execution_time', '0');
  ignore_user_abort(true);

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

  $baseUrl    = HTTP::getShopUrlDomain();
  $repository = new SeoEmbedding('products_seo_embedding');

  try {
    $languages = Registry::get('Language')->getAll();
  } catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Failed to read languages: ' . $e->getMessage()]);
    exit;
  }

  $perLanguage = [];
  $atLeastOneSuccess = false;

  foreach ($languages as $code => $info) {
    if ((int)($info['status'] ?? 1) === 0) {
      continue;
    }
    $languageId = (int)($info['id'] ?? 0);
    if ($languageId <= 0) {
      continue;
    }

    $linkUrl = $baseUrl . 'index.php?Products&Description&products_id=' . $productId
             . '&language=' . urlencode((string)$code);

    try {
      $result = $repository->process(
        entityId:    $productId,
        languageId:  $languageId,
        url:         $linkUrl,
        baseUrl:     $baseUrl,
        pageType:    'product',
        triggeredBy: 'ajax'
      );
      $perLanguage[$code] = [
        'language_id' => $languageId,
        'status'      => ($result['success'] ?? false) ? 'applied' : 'failed',
        'mode'        => $result['mode']      ?? null,
        'seo_score'   => $result['seo_score'] ?? $result['seo_score_now'] ?? null,
        'message'     => $result['message']   ?? ($result['error'] ?? ''),
      ];
      if ($result['success'] ?? false) {
        $atLeastOneSuccess = true;
      }
    } catch (\Throwable $e) {
      $perLanguage[$code] = [
        'language_id' => $languageId,
        'status'      => 'failed',
        'message'     => $e->getMessage(),
      ];
    }
  }

  echo json_encode([
    'success'   => $atLeastOneSuccess,
    'mode'      => 'initial_multilingual',
    'languages' => $perLanguage,
    'message'   => $atLeastOneSuccess
      ? 'Initial SEO audit applied across enabled locales.'
      : 'Initial SEO audit failed for every enabled locale.',
  ]);
  exit;
