<?php
/**
 * Analytics Validation Gate Parameter
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ASY\Params;

use ClicShopping\OM\HTML;

/**
 * Analytics Validation Gate Enable/Disable Parameter
 *
 * When enabled, an LLM scores each analytics answer and may regenerate it once, keeping the new
 * answer only if it scores strictly better. Costs one extra evaluation call per analytics answer.
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ASY\Params
 * @since 4.33.0
 */
class validation_gate_status extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'True';
  public int|null $sort_order = 45;
  public bool $app_configured = true;

  /**
   * Initialize parameter configuration
   */
  protected function init()
  {
    $this->title = $this->app->getDef('cfg_chatgpt_validation_gate_status_title');
    $this->description = $this->app->getDef('cfg_chatgpt_validation_gate_status_description');
  }

  /**
   * Get input field HTML
   *
   * @return string HTML for radio button input
   */
  public function getInputField()
  {
    $value = $this->getInputValue();

    $input = HTML::radioField($this->key, 'True', $value, 'id="' . $this->key . '1" autocomplete="off"') . $this->app->getDef('cfg_chatgpt_status_true') . ' ';
    $input .= HTML::radioField($this->key, 'False', $value, 'id="' . $this->key . '2" autocomplete="off"') . $this->app->getDef('cfg_chatgpt_status_false');

    return $input;
  }
}
