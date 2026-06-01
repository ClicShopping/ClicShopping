<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\EMail;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class EMail extends ConfigurableAppAbstract
{
  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_Email_V1';

  protected function init()
  {
  }
}
