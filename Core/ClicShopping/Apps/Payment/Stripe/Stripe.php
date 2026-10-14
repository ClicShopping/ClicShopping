<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\Stripe;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

/**
 * Class Stripe is a part of the ClicShopping payment module.
 * It handles the configuration modules and provides access to relevant information regarding payment configurations.
 */
class Stripe extends ConfigurableAppAbstract
{

  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_Stripe_V1';

  protected function init()
  {
  }
}
