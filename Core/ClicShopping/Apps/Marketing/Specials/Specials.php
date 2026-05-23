<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Specials;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class Specials extends ConfigurableAppAbstract
{
  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_Specials_V1';

  /**
   * Initializes the component or performs initial setup tasks.
   *
   * @return void
   */
  protected function init()
  {
  }
}
