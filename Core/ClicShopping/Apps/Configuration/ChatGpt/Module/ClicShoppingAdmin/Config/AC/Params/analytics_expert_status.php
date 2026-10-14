<?php
/**
 * Analytics Expert Critic Configuration Parameter
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\AC\Params;

use ClicShopping\OM\HTML;

/**
 * Analytics Expert Critic Parameter
 * 
 * Controls activation of the Analytics Expert critic.
 * Specializes in evaluating data analysis, SQL queries, and BI operations.
 * 
 * @package ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\AgentCritics\Params
 * @since 4.2.0
 */
class analytics_expert_status extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'True';
  public int|null $sort_order = 15;
  public bool $app_configured = true;

  /**
   * Initialize parameter configuration
   */
  protected function init()
  {
    $this->title = $this->app->getDef('cfg_chatgpt_analytics_expert_status_title');
    $this->description = $this->app->getDef('cfg_chatgpt_analytics_expert_status_description');
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
