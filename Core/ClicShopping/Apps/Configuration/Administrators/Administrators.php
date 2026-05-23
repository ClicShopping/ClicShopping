<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Administrators;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class Administrators extends ConfigurableAppAbstract
{
  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_Administrators_V1';

  /**
   * Initializes the required components or configurations for the class.
   *
   * @return void
   */
  protected function init()
  {
  }
}
