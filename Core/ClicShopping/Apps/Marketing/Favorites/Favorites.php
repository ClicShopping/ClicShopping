<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Favorites;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class Favorites extends ConfigurableAppAbstract
{
  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_Favorites_V1';

  /**
   * Initializes the required setup or configuration.
   *
   * @return void
   */
  protected function init()
  {
  }
}
