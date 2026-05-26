<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\RA\Params;

class interaction_response_max_chars extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '16000000';
  public int|null $sort_order = 190;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_chatgpt_interaction_response_max_chars_title');
    $this->description = $this->app->getDef('cfg_chatgpt_interaction_response_max_chars_description');
  }
}
