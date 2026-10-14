<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\CAI\Params;

class promo_p1 extends \ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '5';
  public int|null $sort_order = 130;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_ecommerce_cockpit_ai_promo_p1_title');
    $this->description = $this->app->getDef('cfg_ecommerce_cockpit_ai_promo_p1_description');
  }
}
