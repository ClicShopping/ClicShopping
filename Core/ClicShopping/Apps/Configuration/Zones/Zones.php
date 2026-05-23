<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Zones;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class Zones extends ConfigurableAppAbstract
{
  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_Zones_V1';

  /**
   * Initializes the necessary configurations or setups for the current instance.
   *
   * @return void
   */
  protected function init()
  {
  }
}
