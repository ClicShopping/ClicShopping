<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Settings;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class Settings extends ConfigurableAppAbstract
{
  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_Settings_V1';

  /**
   * Initializes the necessary settings or configurations required for the object or process.
   *
   * @return void
   */
  protected function init()
  {
  }
}
