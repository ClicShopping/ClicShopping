<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\CH\Params;

use ClicShopping\OM\HTML;

class response_format extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'text';
  public int|null $sort_order = 50;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_chatgpt_response_format_title');
    $this->description = $this->app->getDef('cfg_chatgpt_response_format_description');
  }

  public function getInputField()
  {
   // $value = $this->getInputValue();

    $array = [
      ['id' => 'text', 'text' => $this->app->getDef('cfg_chatgpt_response_format_text')],
      ['id' => 'json_object', 'text' => $this->app->getDef('cfg_chatgpt_response_format_json')],
    ];

    $input = HTML::selectField($this->key, $array, $this->getInputValue(), 'id="model_json"');

//    $input = HTML::radioField($this->key, 'text', $value, 'id="' . $this->key . '1" autocomplete="off"') . $this->app->getDef('cfg_chatgpt_response_format_text') . ' ';
//    $input .= HTML::radioField($this->key, 'json_object', $value, 'id="' . $this->key . '2" autocomplete="off"') . $this->app->getDef('cfg_chatgpt_response_format_json');

    return $input;
  }
}