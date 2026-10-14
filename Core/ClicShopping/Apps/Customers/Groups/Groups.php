<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

/**
 * Customerssupport management application for handling Customers operations.
 * Provides configuration management and business domain organization.
 */
class Groups extends ConfigurableAppAbstract
{
  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_Groups_V1';

  /**
   * Initializes the necessary properties or settings for the class or component.
   *
   * @return void
   */
  protected function init()
  {
  }
}
