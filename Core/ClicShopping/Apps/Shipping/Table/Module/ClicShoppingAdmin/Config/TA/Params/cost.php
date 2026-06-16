<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Shipping\Table\Module\ClicShoppingAdmin\Config\TA\Params;

class cost extends \ClicShopping\Apps\Shipping\Table\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '25:8.50,50:5.50,10000:0.00';
  public int|null $sort_order = 40;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_table_cost_title');
    $this->description = $this->app->getDef('cfg_table_cost_desc');
  }
}
