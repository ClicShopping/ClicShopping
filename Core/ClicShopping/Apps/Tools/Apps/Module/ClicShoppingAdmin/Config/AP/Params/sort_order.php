<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Apps\Module\ClicShoppingAdmin\Config\AP\Params;

class sort_order extends \ClicShopping\Apps\Tools\Apps\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{

  public $default = '300';
  public bool $app_configured = true;
  public int|null $sort_order = 20;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_apps_sort_order_title');
    $this->description = $this->app->getDef('cfg_apps_sort_order_description');
  }
}
