<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Specials;

use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\CockpitAIOrchestrator;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

/**
 * Class Insert
 *
 * This class handles the insertion of product data into the database.
 * It generates SEO metadata, summaries, and translations based on product information,
 * and also creates categories-related images if specified.
 */

class Update implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  public mixed $lang;
  private mixed $CockpitAIOrchestrator;

  /**
   * Class constructor.
   * Initializes the ChatGptApp instance in the Registry if it doesn't already exist,
   * and loads the necessary definitions for the application.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Ecommerce')) {
      Registry::set('Ecommerce', new EcommerceApp());
    }

    $this->app = Registry::get('Ecommerce');
    $this->lang = Registry::get('Language');

    Registry::set('CockpitAIOrchestrator', new CockpitAIOrchestrator());
    $this->CockpitAIOrchestrator = Registry::get('CockpitAIOrchestrator');
  }

  /**
   * Executes the necessary processes based on the provided GET and POST parameters related to category handling.
   *
   * Checks if GPT functionality is enabled and processes category-related inputs to update database records
   * such as descriptions, SEO data (title, description, keywords), and optionally images.
   *
   * @return bool Returns false if GPT functionality is disabled or not applicable; otherwise, performs the operations without returning a value.
   */
  public function execute()
  {
    $requiredConstants = [
      'CLICSHOPPING_APP_ECOMMERCE_EC_STATUS',
      'CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING',
      'CLICSHOPPING_APP_CHATGPT_RA_STATUS',
    ];

    CLICSHOPPING::checkAppsIsActivated($requiredConstants);

    if (!Gpt::checkGptStatus()) {
      return false;
    }

    if (isset($_GET['Update'], $_GET['Specials'])) {
      $products_id = HTML::sanitize($_POST['products_id']);
      
      // Si on insère ou met à jour une promo
      if (isset($products_id)) {
        $this->clearCockpitCache((int)$products_id);
      }
    }
  }

  /**
   * Supprime le score mis en cache pour forcer le recalcul
   */
  private function clearCockpitCache(int $productId): void
  {
    if ($this->CockpitAIOrchestrator !== null) {
      $this->CockpitAIOrchestrator->clearCockpitCache($productId);
    } else {
      $this->app->db->save('products_cockpit_ai_action_log', [
        'product_id' => (int)$productId,
        'action_type' => 'system_update_flag',
        'status' => 'executed',
        'validation_reason' => 'Promotion update via Specials Hook (Update)',
        'date_created' => 'now()'
      ]);
    }

    if (\defined('CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG') && CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG === 'True') {
      error_log("[CockpitAI] Specials update: Refresh flag set for product $productId");
    }
  }
}