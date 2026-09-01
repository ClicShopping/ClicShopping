<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\Shop\Security;

use ClicShopping\Apps\Tools\MCP\MCP;
use ClicShopping\OM\Mail;
use ClicShopping\OM\Registry;

/**
 * Class McpAlert
 *
 * Durable trace of an MCP quota refusal, plus the administrator notification.
 * One row per account and per window: that row IS the anti-flood guard, a client looping on a
 * closed quota would otherwise raise one alert and one e-mail per refused request.
 */
class McpAlert
{
  private const ALERT_TYPE = 'rate_limit';

  /**
   * Records a quota refusal and notifies the administrator, once per account and per window.
   *
   * @param int $mcpId The refused MCP account.
   * @param int $limit The ceiling in force for that account.
   * @param int $window The window in force for that account, in seconds.
   */
  public static function quotaExceeded(int $mcpId, int $limit, int $window): void
  {
    if ($mcpId < 1) {
      return;
    }

    try {
      $CLICSHOPPING_Db = Registry::get('Db');

      $Qexisting = $CLICSHOPPING_Db->prepare('select count(id) as count
                                              from :table_mcp_alerts
                                              where mcp_id = :mcp_id
                                                and alert_type = :alert_type
                                                and alert_timestamp >= :since
                                            ');
      $Qexisting->bindInt(':mcp_id', $mcpId);
      $Qexisting->bindValue(':alert_type', self::ALERT_TYPE);
      $Qexisting->bindValue(':since', date('Y-m-d H:i:s', time() - max(1, $window)));
      $Qexisting->execute();

      if ($Qexisting->valueInt('count') > 0) {
        return;
      }

      $message = self::def('text_alert_mcp_rate_limit', [
        'account' => $mcpId,
        'limit' => $limit,
        'window' => $window
      ]);

      $CLICSHOPPING_Db->save('mcp_alerts', [
        'mcp_id' => $mcpId,
        'alert_type' => self::ALERT_TYPE,
        'message' => $message,
        'alert_timestamp' => date('Y-m-d H:i:s'),
        'severity_level' => 2,
        'context' => json_encode(['mcp_id' => $mcpId, 'limit' => $limit, 'window' => $window])
      ]);

      self::notify($mcpId, $message);
    } catch (\Exception $e) {
      McpSecurity::logSecurityEvent('Quota alert could not be recorded', [
        'mcp_id' => $mcpId,
        'error' => $e->getMessage()
      ]);
    }
  }

  /**
   * Sends the alert e-mail when the App switch and the account flag are both on.
   *
   * @param int $mcpId The refused MCP account.
   * @param string $message The alert body.
   */
  private static function notify(int $mcpId, string $message): void
  {
    if (!McpAccountConfig::notificationsEnabled($mcpId)) {
      return;
    }

    $to = McpAccountConfig::alertEmail();

    if ($to === '') {
      return;
    }

    (new Mail())->clicMail($to, 'Admin', self::def('text_alert_mcp_rate_limit_subject'), $message, 'Agent MCP', $to);
  }

  /**
   * Reads an MCP definition from the Shop side, where the App group is not loaded.
   *
   * @param string $key The definition key.
   * @param array<string, mixed> $values The {{var}} substitutions.
   * @return string
   */
  private static function def(string $key, array $values = []): string
  {
    static $app = null;

    if ($app === null) {
      $app = Registry::exists('MCP') ? Registry::get('MCP') : new MCP();
      $app->loadDefinitions('Sites/ClicShoppingAdmin/main');
    }

    return $app->getDef($key, $values);
  }
}
