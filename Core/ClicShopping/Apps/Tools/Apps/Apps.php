<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Apps;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class Apps extends ConfigurableAppAbstract
{

  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_Apps_V1';

  /**
   * Initializes the necessary components or configuration for the object.
   *
   * @return void
   */
  protected function init()
  {
  }
}
