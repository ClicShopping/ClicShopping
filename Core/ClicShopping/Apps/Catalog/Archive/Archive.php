<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Archive;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

/**
 * Represents the Archive class that extends the AppAbstract class and provides
 * methods for managing configuration modules and retrieving metadata such as
 * API version and instance identifier.
 */
class Archive extends ConfigurableAppAbstract
{
  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_Archive_V1';

  protected function init()
  {
  }
}
