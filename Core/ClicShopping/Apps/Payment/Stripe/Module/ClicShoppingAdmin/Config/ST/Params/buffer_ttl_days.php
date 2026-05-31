<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\Stripe\Module\ClicShoppingAdmin\Config\ST\Params;

class buffer_ttl_days extends \ClicShopping\Apps\Payment\Stripe\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{

  public int|null $sort_order = 1010;
  public $default = '30';
  public bool $app_configured = false;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_stripe_buffer_ttl_days_title');
    $this->description = $this->app->getDef('cfg_stripe_buffer_ttl_days_description');
  }
}
