<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\ServiceAPP;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class ServiceAPP extends ConfigurableAppAbstract
{

  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_ServiceAPP_V1';

  /**
   * Initializes the instance or prepares the required setup.
   *
   * @return void
   */
  protected function init()
  {
  }
}
