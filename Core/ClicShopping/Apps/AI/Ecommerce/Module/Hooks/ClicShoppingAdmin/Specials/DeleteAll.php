<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Specials;

use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\CockpitAIOrchestrator;

class DeleteAll implements HooksInterface
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

  public function execute()
  {
    $requiredConstants = [
      'CLICSHOPPING_APP_ECOMMERCE_CAI_STATUS',
    ];

    foreach ($requiredConstants as $const) {
      if (!\defined($const) || \constant($const) !== 'True') {
        return false;
      }
    }

    if (!Gpt::checkGptStatus()) {
      return false;
    }

    if (isset($_POST['selected'], $_GET['Specials'])) {
      foreach ($_POST['selected'] as $id) {
        $this->clearCockpitCache($id);
      }
    }
  }

  private function clearCockpitCache(int $productId): void
  {
    $this->CockpitAIOrchestrator->clearCockpitCache($productId, 'delete');

    if (defined('CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG') && CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG === 'True') {
      error_log("[CockpitAI] Specials DeleteAll: refresh flag set for product $productId");
    }
  }
}