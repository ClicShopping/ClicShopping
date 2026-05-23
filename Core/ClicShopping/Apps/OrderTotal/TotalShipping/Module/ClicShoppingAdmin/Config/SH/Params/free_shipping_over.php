<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\OrderTotal\TotalShipping\Module\ClicShoppingAdmin\Config\SH\Params;

class free_shipping_over extends \ClicShopping\Apps\OrderTotal\TotalShipping\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{

  public int|null $sort_order = 30;
  public $default = '50';
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_order_total_free_shipping_over_title');
    $this->description = $this->app->getDef('cfg_order_total_free_shipping_over_description');
  }
}
