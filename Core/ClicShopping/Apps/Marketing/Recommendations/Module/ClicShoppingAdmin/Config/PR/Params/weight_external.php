<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Recommendations\Module\ClicShoppingAdmin\Config\PR\Params;

class weight_external extends \ClicShopping\Apps\Marketing\Recommendations\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 0.3;
  public int|null $sort_order = 73;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_products_recommendations_weight_external_title');
    $this->description = $this->app->getDef('cfg_products_recommendations_weight_external_description');
  }
}

