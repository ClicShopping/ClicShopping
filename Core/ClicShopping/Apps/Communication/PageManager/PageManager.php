<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\PageManager;


use ClicShopping\OM\Domains\ConfigurableAppAbstract;


class PageManager extends ConfigurableAppAbstract
{
  /**
   * API version for this domain app
   * 
   * @var int
   */
  protected $api_version = 1;

  /**
   * Unique identifier for this domain app
   * 
   * Format: ClicShopping_{Vendor}_{AppName}_V{Version}
   * 
   * @var string
   */
  protected string $identifier = 'ClicShopping_PageManager_V1';

  /**
   * Initializes the necessary components or configurations for the class.
   *
   * @return void
   */
  protected function init(): void
  {
  }
}
