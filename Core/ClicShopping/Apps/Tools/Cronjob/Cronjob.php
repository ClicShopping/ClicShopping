<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Cronjob;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class Cronjob extends ConfigurableAppAbstract
{

  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_Cronjob_V1';

  /**
   * Initializes the necessary components or configurations for the current object.
   *
   * @return void
   */
  protected function init()
  {
  }
}
