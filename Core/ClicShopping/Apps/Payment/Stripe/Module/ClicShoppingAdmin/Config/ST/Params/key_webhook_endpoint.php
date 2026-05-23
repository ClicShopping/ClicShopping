<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\Stripe\Module\ClicShoppingAdmin\Config\ST\Params;

class key_webhook_endpoint extends \ClicShopping\Apps\Payment\Stripe\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '';
  public int|null $sort_order = 48;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_stripe_key_webhook_endpoint_title');
    $this->description = $this->app->getDef('cfg_stripe_key_webhook_endpoint_desc');
  }
}
