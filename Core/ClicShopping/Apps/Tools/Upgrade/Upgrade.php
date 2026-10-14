<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Upgrade;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class Upgrade extends ConfigurableAppAbstract
{

  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_Upgrade_V1';

  /**
   * Initializes the required properties or configurations for the current class.
   *
   * @return void
   */
  protected function init()
  {
  }
}
