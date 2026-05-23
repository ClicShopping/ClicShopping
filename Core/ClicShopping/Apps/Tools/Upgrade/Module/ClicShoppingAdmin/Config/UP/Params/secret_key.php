<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Upgrade\Module\ClicShoppingAdmin\Config\UP\Params;

class secret_key extends \ClicShopping\Apps\Tools\Upgrade\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{

  public $default = '';
  public bool $app_configured = true;
  public int|null $sort_order = 55;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_upgrade_secret_key_title');
    $this->description = $this->app->getDef('cfg_upgrade_secret_key_description');
  }
}
