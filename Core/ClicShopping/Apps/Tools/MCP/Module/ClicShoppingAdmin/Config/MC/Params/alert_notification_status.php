<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Module\ClicShoppingAdmin\Config\MC\Params;

use ClicShopping\OM\HTML;

/**
 * Master switch of the MCP administrator e-mail alerts.
 * An account also carries its own `alert_notification` flag: both must be on.
 * Read only through McpAccountConfig::notificationsEnabled().
 */
class alert_notification_status extends \ClicShopping\Apps\Tools\MCP\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = 'False';
  public int|null $sort_order = 90;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_mcp_alert_notification_status_title');
    $this->description = $this->app->getDef('cfg_mcp_alert_notification_status_description');
  }

  public function getInputField()
  {
    $value = $this->getInputValue();

    $input = HTML::radioField($this->key, 'True', $value, 'id="' . $this->key . '1" autocomplete="off"') . $this->app->getDef('cfg_mcp_alert_notification_status_true') . ' ';
    $input .= HTML::radioField($this->key, 'False', $value, 'id="' . $this->key . '2" autocomplete="off"') . $this->app->getDef('cfg_mcp_alert_notification_status_false');

    return $input;
  }
}
