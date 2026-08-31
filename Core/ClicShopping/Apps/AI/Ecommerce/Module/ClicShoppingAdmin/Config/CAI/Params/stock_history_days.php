<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\CAI\Params;

/**
 * Length of the sales history the daily demand series is built from, in days.
 * A short window follows a trend faster; a long one is steadier on seasonal products.
 */
class stock_history_days extends \ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '90';
  public int|null $sort_order = 141;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_ecommerce_cockpit_ai_stock_history_days_title');
    $this->description = $this->app->getDef('cfg_ecommerce_cockpit_ai_stock_history_days_description');
  }
}
