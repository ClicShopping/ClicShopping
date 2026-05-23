<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class ChatGpt extends ConfigurableAppAbstract
{
  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_ChatGpt_V1';

  protected function init()
  {
  }
}
