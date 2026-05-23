<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\ActionsRecorder;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;

class ActionsRecorder extends ConfigurableAppAbstract
{

  protected $api_version = 1;
  protected string $identifier = 'ClicShopping_ActionsRecorder_V1';

  /**
   * Initializes the necessary components or settings for the current instance.
   *
   * @return void
   */
  protected function init()
  {
  }
}
