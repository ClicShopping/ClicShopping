<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\AT\Params;

/**
 * Coordination Timeout Parameter
 * 
 * Controls the maximum time (in seconds) for actor-critic coordination.
 * If coordination exceeds this timeout, the system returns the best available response.
 * 
 * @package ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\AT\Params
 * @since 4.2.0
 */
class coordination_timeout extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '30';
  public int|null $sort_order = 15;
  public bool $app_configured = true;

  /**
   * Initialize parameter configuration
   */
  protected function init()
  {
    $this->title = $this->app->getDef('cfg_chatgpt_coordination_timeout_title');
    $this->description = $this->app->getDef('cfg_chatgpt_coordination_timeout_description');
  }
}
