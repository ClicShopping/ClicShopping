<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\WEB\Params;

class fuzzy_match_threshold extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '0.75';
  public int|null $sort_order = 50;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_ecommerce_web_fuzzy_match_threshold_title');
    $this->description = $this->app->getDef('cfg_ecommerce_web_fuzzy_match_threshold_description');
  }
}
