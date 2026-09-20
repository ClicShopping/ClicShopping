<?php
/**
 * Outbound Allowed Hosts
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ASY\Params;

/**
 * Hosts the AI layer may reach when the outbound mode is 'allowlist'.
 *
 * Separated by commas or spaces. An entry also covers its subdomains. Read nowhere else than
 * \ClicShopping\AI\Security\OutboundPolicy.
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ASY\Params
 * @since 4.33.0
 */
class outbound_allowed_hosts extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '';
  public int|null $sort_order = 61;
  public bool $app_configured = true;

  /**
   * Initialize parameter configuration
   */
  protected function init()
  {
    $this->title = $this->app->getDef('cfg_chatgpt_outbound_allowed_hosts_title');
    $this->description = $this->app->getDef('cfg_chatgpt_outbound_allowed_hosts_description');
  }
}
