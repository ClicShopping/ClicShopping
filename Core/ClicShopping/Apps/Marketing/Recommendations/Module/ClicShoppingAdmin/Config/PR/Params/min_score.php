<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Recommendations\Module\ClicShoppingAdmin\Config\PR\Params;

class min_score extends \ClicShopping\Apps\Marketing\Recommendations\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 0.5;
  public int|null $sort_order = 55;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_products_recommendations_min_score_title');
    $this->description = $this->app->getDef('cfg_products_recommendations_min_score_description');
  }
}
