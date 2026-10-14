<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Api\Module\ClicShoppingAdmin\Config\AI\Params;

use ClicShopping\OM\HTML;

/**
 * Defines how strictly ApiSecurity::checkToken() compares the client's
 * current IP against the IP stored at session creation.
 *
 * Modes:
 *   - strict : exact match (legacy)
 *   - subnet : same /24 (IPv4) or /64 (IPv6) — tolerates NAT/CDN/load-balancer
 *   - off    : no IP check — session relies purely on the token secret
 *
 * Default 'subnet' is a sane balance between security and tolerance of
 * multi-PoP infrastructure.
 */
class ip_check_mode extends \ClicShopping\Apps\Configuration\Api\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'subnet';
  public int|null $sort_order = 35;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title       = $this->app->getDef('cfg_products_api_ip_check_mode_title');
    $this->description = $this->app->getDef('cfg_products_api_ip_check_mode_description');
  }

  public function getInputField()
  {
    $value = $this->getInputValue();

    $input  = HTML::radioField($this->key, 'strict', $value, 'id="' . $this->key . '1" autocomplete="off"')
            . ' ' . $this->app->getDef('cfg_products_api_ip_check_mode_strict') . '<br>';
    $input .= HTML::radioField($this->key, 'subnet', $value, 'id="' . $this->key . '2" autocomplete="off"')
            . ' ' . $this->app->getDef('cfg_products_api_ip_check_mode_subnet') . '<br>';
    $input .= HTML::radioField($this->key, 'off', $value, 'id="' . $this->key . '3" autocomplete="off"')
            . ' ' . $this->app->getDef('cfg_products_api_ip_check_mode_off');

    return $input;
  }
}
