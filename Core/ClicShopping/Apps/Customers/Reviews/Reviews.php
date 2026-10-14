<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class Reviews extends ConfigurableAppAbstract
{
  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_Reviews_V1';

  /**
   * Initializes the necessary components or state required for the method.
   *
   * @return void
   */
  protected function init()
  {
  }
}
