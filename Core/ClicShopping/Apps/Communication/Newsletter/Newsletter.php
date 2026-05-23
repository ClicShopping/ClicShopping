<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\Newsletter;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class Newsletter extends ConfigurableAppAbstract
{

  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_Newsletter_V1';

  /**
   * Initializes the necessary configurations or properties for the class.
   *
   * @return void
   */
  protected function init()
  {
  }
}
