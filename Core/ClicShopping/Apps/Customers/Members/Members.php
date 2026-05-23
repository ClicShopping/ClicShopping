<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Members;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class Members extends ConfigurableAppAbstract
{
  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_Members_V1';

  /**
   * Initializes the component or performs necessary setup operations.
   *
   * @return void
   */
  protected function init()
  {
  }
}
