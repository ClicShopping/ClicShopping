<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\SecurityCheck;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class SecurityCheck extends ConfigurableAppAbstract
{

  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_SecurityCheck_V1';

  /**
   * Initializes the necessary components or configuration for the current instance.
   *
   * @return void
   */
  protected function init()
  {
  }
}
