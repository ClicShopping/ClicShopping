<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\AT\Params;

/**
 * Max Retries Parameter
 *
 * Controls how many times the actor-critic coordination retries a transient
 * failure before giving up. Read by AgentTechnicalConfig::getMaxRetries()
 * (constant CLICSHOPPING_APP_CHATGPT_AT_MAX_RETRIES) and propagated to the
 * ActorCriticCoordinator. Higher = more resilient but slower and costlier.
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\AT\Params
 * @since 4.2.0
 */
class max_retries extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '2';
  public int|null $sort_order = 25;
  public bool $app_configured = true;

  /**
   * Initialize parameter configuration
   */
  protected function init()
  {
    $this->title = $this->app->getDef('cfg_chatgpt_max_retries_title');
    $this->description = $this->app->getDef('cfg_chatgpt_max_retries_description');
  }
}
