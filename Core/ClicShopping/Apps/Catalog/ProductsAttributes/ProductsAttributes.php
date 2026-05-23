<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\ProductsAttributes;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class ProductsAttributes extends ConfigurableAppAbstract
{
  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_ProductsAttributes_V1';

  /**
   * Initializes the required configurations or settings for the class instance.
   *
   * @return void
   */
  protected function init()
  {
  }
}
