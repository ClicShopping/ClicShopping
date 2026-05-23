<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Module\ClicShoppingAdmin\Config\RV\Params;

class review_number extends \ClicShopping\Apps\Customers\Reviews\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{

  public $default = '10';
  public int|null $sort_order = 30;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_reviews_review_number_title');
    $this->description = $this->app->getDef('cfg_reviews_review_number_description');
  }
}
