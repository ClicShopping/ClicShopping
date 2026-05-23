<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\CH\Params;

use ClicShopping\OM\HTML;

/**
 * Class reasoning_effort
 *
 * This class defines a configuration parameter for the reasoning effort of the ChatGPT model.
 * It extends the ConfigParamAbstract class and provides methods to initialize the parameter,
 * set its default value, and generate an HTML input field for it.
 */
class reasoning_effort extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'medium';
  public int|null $sort_order = 42;

  /**
   * Initializes the configuration parameter with its title and description.
   */
  protected function init()
  {
    $this->title = $this->app->getDef('cfg_chatgpt_reasoning_title');
    $this->description = $this->app->getDef('cfg_chatgpt_reasoning_description');
  }

  /**
   * Returns the HTML input field for the configuration parameter.
   *
   * @return string The HTML input field.
   */
  public function getInputField()
  {
    // $value = $this->getInputValue();

    $array = [
      ['id' => 'text', 'text' => $this->app->getDef('cfg_chatgpt_response_reasoning_select')],
      ['id' => 'minimal', 'text' => $this->app->getDef('cfg_chatgpt_response_reasoning_minimal')],
      ['id' => 'low', 'text' => $this->app->getDef('cfg_chatgpt_response_reasoning_low')],
      ['id' => 'medium', 'text' => $this->app->getDef('cfg_chatgpt_response_reasoning_medium')],
      ['id' => 'high', 'text' => $this->app->getDef('cfg_chatgpt_response_reasoning_high')],
    ];

    $input = HTML::selectField($this->key, $array, $this->getInputValue(), 'id="model_reasoning"');

    return $input;
  }
}