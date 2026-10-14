<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\CH\Params;

use ClicShopping\OM\HTML;

class api_key_serpapi extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{

  public $default = '';
  public int|null $sort_order = 35;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_chatgpt_api_key_serpapi_title');
    $this->description = $this->app->getDef('cfg_chatgpt_api_key_serpapi_description');
  }

  public function getInputField()
  {
    $value = $this->getInputValue();

    $input = HTML::passwordField($this->key,  $value, 'id="' . $this->key . '" autocomplete="off"');

    return $input;
  }
}
