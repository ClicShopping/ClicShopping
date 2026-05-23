<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\OrderTotal\SubTotal;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class SubTotal extends ConfigurableAppAbstract
{
  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_SubTotal_V1';

  /**
   * Initializes necessary components or settings for the class.
   *
   * @return void
   */
  protected function init()
  {
  }
}
