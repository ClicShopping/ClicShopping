<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\CAI\Params;

/**
 * Service level of the safety-stock computation, as a probability in [0.50 .. 0.999].
 * Raising it raises the buffer: it is the share of demand the stock is meant to absorb.
 */
class stock_service_level extends \ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '0.95';
  public int|null $sort_order = 140;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_ecommerce_cockpit_ai_stock_service_level_title');
    $this->description = $this->app->getDef('cfg_ecommerce_cockpit_ai_stock_service_level_description');
  }
}
