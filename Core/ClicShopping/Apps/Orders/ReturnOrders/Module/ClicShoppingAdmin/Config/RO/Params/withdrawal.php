<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\ReturnOrders\Module\ClicShoppingAdmin\Config\RO\Params;

class withdrawal extends \ClicShopping\Apps\Orders\ReturnOrders\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{

  public $default = '14';
  public int|null $sort_order = 50;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_products_return_orders_withdrawal_title');
    $this->description = $this->app->getDef('cfg_products_return_orders_withdrawal_description');
  }
}
