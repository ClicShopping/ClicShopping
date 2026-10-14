<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\ModulesHooks\Module\ClicShoppingAdmin\Config\MH\Params;

class sort_order extends \ClicShopping\Apps\Tools\ModulesHooks\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{

  public $default = '300';
  public bool $app_configured = true;
  public int|null $sort_order = 20;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_modules_hooks_sort_order_title');
    $this->description = $this->app->getDef('cfg_modules_hooks_sort_order_description');
  }
}
