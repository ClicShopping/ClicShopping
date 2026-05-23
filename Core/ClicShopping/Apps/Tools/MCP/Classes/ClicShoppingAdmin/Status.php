<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;

class Status
{
  /**
   * Updates the status of an MCP entry in the database.
   *
   * @param int $id The ID of the MCP record to update.
   * @param int $status The new status value for the MCP record (1 for active, 0 for inactive).
   * @return mixed Returns the result of the database save operation, or -1 if the status is invalid.
   */
  public static function getMcpStatus(int $id, int $status)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if ($status == '1') {
      $update_array = [
        'status' => 1,
        'date_modified' => 'now()'
      ];

      return $CLICSHOPPING_Db->save('mcp', $update_array, ['mcp_id' => (int)$id]);
    } elseif ($status == '0') {
      $update_array = [
        'status' => 0,
        'date_modified' => 'now()',
      ];

      return $CLICSHOPPING_Db->save('mcp', $update_array, ['mcp_id' => (int)$id]);
    } else {
      return -1;
    }
}
}