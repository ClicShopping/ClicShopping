<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Module\ClicShoppingAdmin\Config\MC\Params;

/**
 * Recipient of the MCP alerts. Empty falls back to STORE_OWNER_EMAIL_ADDRESS.
 * Read only through McpAccountConfig::alertEmail().
 */
class alert_notification_email extends \ClicShopping\Apps\Tools\MCP\Module\ClicShoppingAdmin\Config\ConfigParamAbstract
{
  public $default = '';
  public int|null $sort_order = 100;
  public bool $app_configured = true;

  protected function init()
  {
    $this->title = $this->app->getDef('cfg_mcp_alert_notification_email_title');
    $this->description = $this->app->getDef('cfg_mcp_alert_notification_email_description');
  }
}
