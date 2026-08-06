<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\OrderTotal\TotalTax;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class TotalTax extends ConfigurableAppAbstract
{
  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_TotalTax_V1';

  /**
   * Initializes the necessary components or configurations for the current context.
   *
   * @return void
   */
  protected function init()
  {
  }
}
