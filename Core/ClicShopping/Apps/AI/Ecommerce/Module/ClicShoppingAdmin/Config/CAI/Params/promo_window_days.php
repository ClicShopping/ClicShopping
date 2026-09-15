<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\CAI\Params;

use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\PromotionWindow;

/**
 * Display window of an automated marketing action, in days, at the smallest discount tier.
 * Deeper tiers are displayed proportionally longer.
 */
class promo_window_days extends \ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = PromotionWindow::DEFAULT_WINDOW_DAYS;
  public int|null $sort_order = 28;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_ecommerce_cockpit_ai_promo_window_days_title');
    $this->description = $this->app->getDef('cfg_ecommerce_cockpit_ai_promo_window_days_description');
  }
}
