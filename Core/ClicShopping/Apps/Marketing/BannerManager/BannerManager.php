<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\BannerManager;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class BannerManager extends ConfigurableAppAbstract
{
  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_BannerManager_V1';

  /**
   * Initializes the required properties or components for the class.
   *
   * @return void
   */
  protected function init()
  {
  }
}
