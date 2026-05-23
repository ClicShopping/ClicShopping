<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Shipping\Item\Module\ClicShoppingAdmin\Config\IT\Params;

class sort_order extends \ClicShopping\Apps\Shipping\Item\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{

  public $default = '300';
  public bool $app_configured = false;
  public int|null $sort_order = 600;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_item_sort_order_title');
    $this->description = $this->app->getDef('cfg_item_sort_order_description');
  }
}
