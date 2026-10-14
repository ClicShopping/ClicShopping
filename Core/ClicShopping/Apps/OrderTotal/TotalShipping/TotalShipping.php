<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\OrderTotal\TotalShipping;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class TotalShipping extends ConfigurableAppAbstract
{
  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_TotalShipping_V1';

  /**
   * Initializes the necessary configurations or settings for the class or object.
   *
   * @return void
   */
  protected function init()
  {
  }
}
