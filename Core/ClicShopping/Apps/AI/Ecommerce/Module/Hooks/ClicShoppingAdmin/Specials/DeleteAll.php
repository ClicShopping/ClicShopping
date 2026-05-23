<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\Specials;

use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\CockpitAIOrchestrator;


class DeleteAll extends \ClicShopping\OM\Domains\PagesActionsAbstract
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
    // On tente d'utiliser l'Orchestrateur si disponible
    if ($this->CockpitAIOrchestrator !== null) {
      $this->CockpitAIOrchestrator->clearCockpitCache($productId);
    } else {
      // Fallback : écriture directe du flag dans la table de log
      $this->app->db->save('products_cockpit_ai_action_log', [
        'product_id' => $productId,
        'action_type' => 'system_update_flag',
        'status' => 'executed',
        'validation_reason' => 'Mass update via DeleteAll hook (Favorites)',
        'date_created' => 'now()'
      ]);
    }

    if (defined('CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG') && CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG === 'True') {
      error_log("[CockpitAI] Refresh flag set for product $productId via DeleteAll Hook.");
    }
  }
}