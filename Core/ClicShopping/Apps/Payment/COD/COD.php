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
 * Class COD
 *
 * This class extends the abstract class \ClicShopping\OM\Domains\AppAbstract and provides functionalities
 * related to the configuration and management of Cash on Delivery (COD) payment modules within
 * the ClicShopping application. It includes methods for retrieving configuration modules, module
 * information, API version, and identifier.
 */
class COD extends ConfigurableAppAbstract
{
  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_COD_V1';

  protected function init()
  {
  }
}
