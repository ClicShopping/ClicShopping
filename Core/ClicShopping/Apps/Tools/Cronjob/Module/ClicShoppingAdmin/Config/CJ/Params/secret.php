<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Cronjob\Module\ClicShoppingAdmin\Config\CJ\Params;

class secret extends \ClicShopping\Apps\Tools\Cronjob\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '';
  public int|null $sort_order = 20;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_products_cronjob_secret_title');
    $this->description = $this->app->getDef('cfg_products_cronjob_secret_description');
  }
}
