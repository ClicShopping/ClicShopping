<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\Stripe\Module\ClicShoppingAdmin\Config\ST\Params;

class public_key extends \ClicShopping\Apps\Payment\Stripe\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '';
  public int|null $sort_order = 45;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_stripe_public_private_key_title');
    $this->description = $this->app->getDef('cfg_stripe_public_private_key_desc');
  }
}
