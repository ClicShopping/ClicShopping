<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\OM\HTML;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\CockpitAIOrchestrator;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../Core/ClicShopping/') . DIRECTORY_SEPARATOR);

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();

CLICSHOPPING::loadSite('ClicShoppingAdmin');

header('Content-Type: application/json; charset=utf-8');

AdministratorAdmin::hasUserAccess();

// Initialize orchestrator
$orchestrator = new CockpitAIOrchestrator();

try {
  // Check if module is enabled
  if ($orchestrator->checkStatus() === false) {
    echo json_encode([
      'success' => false,
      'error' => 'CockpitAI module is not enabled',
      'error_code' => 'MODULE_DISABLED'
    ]);
    exit;
  }

  // Check if GPT and RAG are enabled
  if (Gpt::checkGptStatus() === false || CLICSHOPPING_APP_CHATGPT_RA_STATUS  == 'False' || CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING == 'False') {
    echo json_encode([
      'success' => false,
      'error' => 'GPT or RAG is not enabled. Please enable them in the configuration.',
      'error_code' => 'GPT_RAG_DISABLED'
    ]);
    exit;
  }

  // Validate product ID
  if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
    echo json_encode([
      'success' => false,
      'error' => 'Product ID is required',
      'error_code' => 'INVALID_PRODUCT_ID'
    ]);
    exit;
  }

  $productId = (int)HTML::sanitize($_POST['product_id']);

  if ($productId <= 0) {
    echo json_encode([
      'success' => false,
      'error' => 'Invalid product ID',
      'error_code' => 'INVALID_PRODUCT_ID'
    ]);
    exit;
  }

  // Get language ID from POST parameter or session
  $languageId = Registry::get('Language')->getId();

  $user_id = AdministratorAdmin::getUserAdminId();

  // Execute analysis
  $result = $orchestrator->executeAnalysis($productId, $languageId, $user_id);

  // Return success response
  echo json_encode([
    'success' => true,
    'data' => $result
  ]);

} catch (\Throwable $e) {
  // Log error
    error_log('CockpitAI AJAX Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

  // Return error response
  echo json_encode([
    'success' => false,
    'error' => 'An error occurred during analysis: ' . $e->getMessage(),
    'error_code' => 'ANALYSIS_ERROR',
    'debug' => $orchestrator->checkStatus() ? [
      'message' => $e->getMessage(),
      'file' => $e->getFile(),
      'line' => $e->getLine()
    ] : null
  ]);
}
