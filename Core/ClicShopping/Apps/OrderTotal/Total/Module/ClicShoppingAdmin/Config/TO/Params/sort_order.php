<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\OrderTotal\Total\Module\ClicShoppingAdmin\Config\TO\Params;

class sort_order extends \ClicShopping\Apps\OrderTotal\Total\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '1500';
  public bool $app_configured = false;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_order_total_total_sort_order_title');
    $this->description = $this->app->getDef('cfg_order_total_total_sort_order_description');
  }
}
