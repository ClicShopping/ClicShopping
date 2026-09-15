<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\RA\Params;

class default_analysis_days extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '30';
  public int|null $sort_order = 105;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_chatgpt_default_analysis_days_title');
    $this->description = $this->app->getDef('cfg_chatgpt_default_analysis_days_description');
  }
}
