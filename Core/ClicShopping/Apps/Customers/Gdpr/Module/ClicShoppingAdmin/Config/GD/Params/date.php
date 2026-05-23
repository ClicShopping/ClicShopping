<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Gdpr\Module\ClicShoppingAdmin\Config\GD\Params;

class date extends \ClicShopping\Apps\Customers\Gdpr\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{

  public $default = '180';
  public int|null $sort_order = 20;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_gdpr_date_title');
    $this->description = $this->app->getDef('cfg_gdpr_date_description');
  }
}
