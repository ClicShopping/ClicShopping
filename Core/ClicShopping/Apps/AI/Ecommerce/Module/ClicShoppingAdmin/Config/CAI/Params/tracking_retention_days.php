<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\CAI\Params;

use ClicShopping\Apps\AI\Ecommerce\Classes\Shop\CockpitAI\ProductsTracking;

/**
 * Retention of the product impression rows, in days.
 * Must stay above the widest window read from them, today 30 days (views_30d).
 */
class tracking_retention_days extends \ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = ProductsTracking::DEFAULT_RETENTION_DAYS;
  public int|null $sort_order = 26;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_ecommerce_cockpit_ai_tracking_retention_days_title');
    $this->description = $this->app->getDef('cfg_ecommerce_cockpit_ai_tracking_retention_days_description');
  }
}
