<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\COD;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

/**
 *
 * This class represents the MoneyOrder application in the ClicShopping environment.
 * It extends the AppAbstract class and implements the necessary methods to handle
 * the configuration of Money Order payment modules. It provides methods to retrieve
 * configuration modules, module information, API version, and the identifier of the app.
 */
class COD extends ConfigurableAppAbstract
{
  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_COD_V1';

  protected function init()
  {
  }
}
