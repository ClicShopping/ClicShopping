<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\AT\Params;

use ClicShopping\OM\HTML;

/**
 * Cache TTL Parameter
 * 
 * Controls the cache time-to-live (in seconds) for agent responses.
 * Longer TTL reduces API costs but may serve stale data.
 * 
 * @package ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\AT\Params
 * @since 4.2.0
 */
class cache_ttl extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '3600';
  public int|null $sort_order = 50;
  public bool $app_configured = true;

  /**
   * Initialize parameter configuration
   */
  protected function init()
  {
    $this->title = $this->app->getDef('cfg_chatgpt_cache_ttl_title');
    $this->description = $this->app->getDef('cfg_chatgpt_cache_ttl_description');
  }
}
