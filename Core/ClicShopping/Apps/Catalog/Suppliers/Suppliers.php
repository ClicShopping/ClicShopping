<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Suppliers;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class Suppliers extends ConfigurableAppAbstract
{

  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_Suppliers_V1';

  /**
   * Initializes the necessary components or configurations for the class.
   *
   * @return void
   */
  protected function init()
  {
  }
}
