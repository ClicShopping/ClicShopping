<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Shipping\Item;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class Item extends ConfigurableAppAbstract
{

  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_Item_V1';

  /**
   * Initializes the necessary setup or configurations for the current instance.
   *
   * @return void
   */
  protected function init()
  {
  }
}
