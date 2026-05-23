<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\WhosOnline;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class WhosOnline extends ConfigurableAppAbstract
{

  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_WhosOnline_V1';

  /**
   * Initializes the necessary configurations or setups for the method.
   *
   * @return void
   */
  protected function init()
  {
  }
}
