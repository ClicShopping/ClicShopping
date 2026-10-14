<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Langues\Module\ClicShoppingAdmin\Config\LG\Params;

class sort_order extends \ClicShopping\Apps\Configuration\Langues\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{

  public $default = '30';
  public int|null $sort_order = 300;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_languages_sort_order_title');
    $this->description = $this->app->getDef('cfg_languages_sort_order_description');
  }
}
