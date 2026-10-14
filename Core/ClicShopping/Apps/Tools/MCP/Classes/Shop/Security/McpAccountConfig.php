<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\Shop\Security;

use ClicShopping\OM\Registry;

/**
 * Class McpAccountConfig
 *
 * Single resolution point for the MCP settings an administrator may override per account.
 * A `null` column means "inherit the App constant"; the constant stays the default value.
 * No caller may read those constants directly, otherwise the column exists and nothing reads it.
 */
class McpAccountConfig
{
  /**
   * @var array<string, array{0: string, 1: int}> Column => [App constant, hard default]
   */
  private const SETTINGS = [
    'rate_limit_window' => ['CLICSHOPPING_APP_MCP_MC_RATE_LIMIT_WINDOW', 900],
    'max_requests_per_window' => ['CLICSHOPPING_APP_MCP_MC_MAX_REQUEST_PER_WINDOW', 20],
  ];

  private const ALERT_STATUS = 'CLICSHOPPING_APP_MCP_MC_ALERT_NOTIFICATION_STATUS';
  private const ALERT_EMAIL = 'CLICSHOPPING_APP_MCP_MC_ALERT_NOTIFICATION_EMAIL';

  /**
   * @var array<int, array<string, mixed>> Per-request memo, one row read per account.
   */
  private static array $rows = [];

  /**
   * Resolves an overridable setting for one MCP account.
   *
   * @param int $mcpId The MCP account, 0 when it is not resolved yet.
   * @param string $column One of the keys of self::SETTINGS.
   * @return int The account override when set, the App constant otherwise.
   */
  public static function int(int $mcpId, string $column): int
  {
    if (!isset(self::SETTINGS[$column])) {
      throw new \InvalidArgumentException('McpAccountConfig: unknown setting ' . $column);
    }

    [$constant, $hardDefault] = self::SETTINGS[$column];
    $default = \defined($constant) ? (int)\constant($constant) : $hardDefault;

    if ($mcpId < 1) {
      return $default;
    }

    $row = self::row($mcpId);

    // The column is absent until the migration runs, and `null` means "inherit".
    if (!isset($row[$column]) || $row[$column] === '') {
      return $default;
    }

    $value = (int)$row[$column];

    return $value > 0 ? $value : $default;
  }

  /**
   * Are the administrator e-mail alerts on for this account?
   * The App switch is the master, the account column is the per-account opt-in.
   *
   * @param int $mcpId The MCP account, 0 to test the App switch alone.
   * @return bool
   */
  public static function notificationsEnabled(int $mcpId): bool
  {
    if (!\defined(self::ALERT_STATUS) || \constant(self::ALERT_STATUS) != 'True') {
      return false;
    }

    if ($mcpId < 1) {
      return true;
    }

    $row = self::row($mcpId);

    return isset($row['alert_notification']) && (int)$row['alert_notification'] === 1;
  }

  /**
   * Recipient of the MCP alerts: the App param, the store owner address otherwise.
   *
   * @return string Empty when neither is set — nothing is sent then.
   */
  public static function alertEmail(): string
  {
    $email = \defined(self::ALERT_EMAIL) ? trim((string)\constant(self::ALERT_EMAIL)) : '';

    if ($email === '' && \defined('STORE_OWNER_EMAIL_ADDRESS')) {
      $email = trim((string)STORE_OWNER_EMAIL_ADDRESS);
    }

    return $email;
  }

  /**
   * Longest rate limit window in force, App setting and account overrides together.
   * A background purge must use it: cutting at the App window would reset a longer
   * account window and make its ceiling unreachable.
   *
   * @return int Seconds.
   */
  public static function longestWindow(): int
  {
    $longest = self::int(0, 'rate_limit_window');

    try {
      $CLICSHOPPING_Db = Registry::get('Db');

      $Qmax = $CLICSHOPPING_Db->prepare('select max(rate_limit_window) as longest
                                         from :table_mcp
                                       ');
      $Qmax->execute();

      $longest = max($longest, $Qmax->valueInt('longest'));
    } catch (\Exception $e) {
      McpSecurity::logSecurityEvent('Longest rate limit window read failed', []);
    }

    return $longest;
  }

  /**
   * Reads one account row, once per request.
   *
   * @param int $mcpId The MCP account.
   * @return array<string, mixed> The row, empty when unknown.
   */
  private static function row(int $mcpId): array
  {
    if (isset(self::$rows[$mcpId])) {
      return self::$rows[$mcpId];
    }

    self::$rows[$mcpId] = [];

    try {
      $CLICSHOPPING_Db = Registry::get('Db');

      $Qmcp = $CLICSHOPPING_Db->prepare('select *
                                         from :table_mcp
                                         where mcp_id = :mcp_id
                                       ');
      $Qmcp->bindInt(':mcp_id', $mcpId);
      $Qmcp->execute();

      $row = $Qmcp->toArray();

      if (\is_array($row)) {
        self::$rows[$mcpId] = $row;
      }
    } catch (\Exception $e) {
      McpSecurity::logSecurityEvent('Account configuration read failed', ['mcp_id' => $mcpId]);
    }

    return self::$rows[$mcpId];
  }
}
