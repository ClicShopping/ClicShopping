<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Featured;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class Featured extends ConfigurableAppAbstract
{
  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_Featured_V1';

  /**
   * Initializes the required components or properties for the class.
   *
   * @return void
   */
  protected function init()
  {
  }
}
