<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\RA\Params;

class max_rows_for_llm_interpretation extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '170';
  public int|null $sort_order = 100;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_chatgpt_max_rows_for_llm_interpretation_title');
    $this->description = $this->app->getDef('cfg_chatgpt_max_rows_for_llm_interpretation_description');
  }
}
