<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Api\Module\ClicShoppingAdmin\Config\AI\Params;

class max_request_per_window extends \ClicShopping\Apps\Configuration\Api\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{

  public $default = '20';
  public int|null $sort_order = 60;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_products_api_max_request_per_window_title');
    $this->description = $this->app->getDef('cfg_products_api_max_request_per_window_description');
  }
}
