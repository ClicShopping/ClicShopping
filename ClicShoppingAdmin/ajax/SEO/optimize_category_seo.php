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
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoAgenticPipeline;

  // Increase PHP execution time for long-running SEO optimization
  set_time_limit(300); // 5 minutes
  ini_set('max_execution_time', '300');

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

  $categoryId = (int)($_POST['seo_category_id'] ?? 0);

  if ($categoryId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid category id.']);
    exit;
  }

  $languageId = (int)($_POST['language_id'] ?? 0);
  if ($languageId <= 0) {
    $languageId = (int)Registry::get('Language')->getId();
  }
  $languageCode = Registry::get('Language')->getLanguageCodeById($languageId);
  $linkUrl = HTTP::getShopUrlDomain() . 'index.php?cPath=' . $categoryId . '&language=' . urlencode($languageCode);
  $baseUrl = HTTP::getShopUrlDomain();

  try {
    $pipeline = new SeoAgenticPipeline('category');

    $result = $pipeline->optimize(
      entityId: $categoryId,
      languageId: $languageId,
      url: $linkUrl,
      baseUrl: $baseUrl,
      triggeredBy: 'ajax'
    );

    echo json_encode($result);
  } catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  }
exit;
