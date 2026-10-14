<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Suppliers\Module\ClicShoppingAdmin\Config\CS\Params;

class sort_order extends \ClicShopping\Apps\Catalog\Suppliers\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{

  public $default = '300';
  public bool $app_configured = false;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_suppliers_sort_order_title');
    $this->description = $this->app->getDef('cfg_suppliers_sort_order_description');
  }
}
