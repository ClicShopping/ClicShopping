<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Recommendations\Module\ClicShoppingAdmin\Config\PR\Params;

class favorites_min_score extends \ClicShopping\Apps\Marketing\Recommendations\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 2.1;
  public int|null $sort_order = 80;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_products_recommendations_favorites_min_score_title');
    $this->description = $this->app->getDef('cfg_products_recommendations_favorites_min_score_description');
  }
}
