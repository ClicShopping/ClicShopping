<?php
/**
 * Outbound Flow Authorisation Mode
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
 * Which outbound destinations the AI layer may reach.
 *
 * 'open' by default so an existing installation is unchanged. Enforced by
 * \ClicShopping\AI\Security\OutboundPolicy at every connection the AI layer builds.
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ASY\Params
 * @since 4.33.0
 */
class outbound_mode extends \ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'open';
  public int|null $sort_order = 60;
  public bool $app_configured = true;

  /**
   * Initialize parameter configuration
   */
  protected function init()
  {
    $this->title = $this->app->getDef('cfg_chatgpt_outbound_mode_title');
    $this->description = $this->app->getDef('cfg_chatgpt_outbound_mode_description');
  }

  /**
   * Get input field HTML
   *
   * @return string HTML for dropdown select
   */
  public function getInputField()
  {
    $value = $this->getInputValue();

    $modes = [
      ['id' => 'open', 'text' => $this->app->getDef('cfg_chatgpt_outbound_mode_open')],
      ['id' => 'sovereign', 'text' => $this->app->getDef('cfg_chatgpt_outbound_mode_sovereign')],
      ['id' => 'allowlist', 'text' => $this->app->getDef('cfg_chatgpt_outbound_mode_allowlist')]
    ];

    return HTML::selectField($this->key, $modes, $value, 'id="' . $this->key . '"');
  }
}
