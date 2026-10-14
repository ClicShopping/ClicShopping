<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Modules;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class Modules extends ConfigurableAppAbstract
{
  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_Modules_V1';

  /**
   * Initializes the necessary settings or configurations for the class.
   *
   * @return void
   */
  protected function init()
  {
  }
}
