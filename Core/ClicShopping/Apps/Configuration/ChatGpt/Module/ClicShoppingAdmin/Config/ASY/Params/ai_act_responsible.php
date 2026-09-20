<?php
/**
 * AI Act Responsible Person Parameter
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ASY\Params;

/**
 * Natural or legal person accountable for the AI system (EU AI Act, deployer obligations).
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ASY\Params
 * @since 4.33.0
 */
class ai_act_responsible extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '';
  public int|null $sort_order = 5;
  public bool $app_configured = true;

  /**
   * Initialize parameter configuration
   */
  protected function init()
  {
    $this->title = $this->app->getDef('cfg_chatgpt_ai_act_responsible_title');
    $this->description = $this->app->getDef('cfg_chatgpt_ai_act_responsible_description');
  }
}
