<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\CH\Params;

use ClicShopping\OM\HTML;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\ModelManager;

/**
 * Class reasoning_effort
 *
 * This class defines a configuration parameter for the reasoning effort of the ChatGPT model.
 * It extends the ConfigParamAbstract class and provides methods to initialize the parameter,
 * set its default value, and generate an HTML input field for it.
 */
class reasoning_effort extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'none';
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
    $value = (string)$this->getInputValue();

    // Only the tiers the current default model actually accepts: the families disagree
    // (none/xhigh against minimal), so a fixed list offers values the API rejects.
    $supported = ModelManager::supportedReasoningEfforts(ModelManager::defaultModel());

    // A value stored for a previous model stays listed, otherwise the form would show
    // nothing selected and silently rewrite the configuration on the next save.
    if ($value !== '' && $value !== 'text' && !in_array($value, $supported, true)) {
      $supported[] = $value;
    }

    $array = [
      ['id' => 'text', 'text' => $this->app->getDef('cfg_chatgpt_response_reasoning_select')],
    ];

    foreach ($supported as $effort) {
      $array[] = [
        'id' => $effort,
        'text' => $this->app->getDef('cfg_chatgpt_response_reasoning_' . $effort),
      ];
    }

    $input = HTML::selectField($this->key, $array, $value, 'id="model_reasoning"');

    return $input;
  }
}