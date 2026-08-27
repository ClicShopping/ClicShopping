<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\CH\Params;

class translation_max_token extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '500';
  public int|null $sort_order = 52;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_chatgpt_translation_max_token_title');
    $this->description = $this->app->getDef('cfg_chatgpt_translation_max_token_description');
  }
}
