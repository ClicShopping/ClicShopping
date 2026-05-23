<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\OrderTotal\TotalTax\Module\ClicShoppingAdmin\Config\TX\Params;

class sort_order extends \ClicShopping\Apps\OrderTotal\TotalTax\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{

//    public int|null $sort_order = 1000;
  public $default = '900';
  public bool $app_configured = false;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_order_total_tax_sort_order_title');
    $this->description = $this->app->getDef('cfg_order_total_tax_sort_order_description');
  }
}
