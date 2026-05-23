<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Recommendations;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class Recommendations extends ConfigurableAppAbstract
{
  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_Recommendations_V1';

  /**
   * Initializes the object or performs setup tasks.
   *
   * @return void
   */
  protected function init()
  {
  }
}
