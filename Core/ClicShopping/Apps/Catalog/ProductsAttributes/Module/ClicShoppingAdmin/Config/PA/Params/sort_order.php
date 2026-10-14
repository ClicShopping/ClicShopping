<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\ProductsAttributes\Module\ClicShoppingAdmin\Config\PA\Params;

class sort_order extends \ClicShopping\Apps\Catalog\ProductsAttributes\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{

  public $default = '300';
  public bool $app_configured = false;
  public int|null $sort_order = 300;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_products_attributes_sort_order_title');
    $this->description = $this->app->getDef('cfg_products_attributes_sort_order_description');
  }
}
