<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Api;

use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\Api\Classes\Shop\ApiSecurity;

class ApiDeleteSupplier
{
  /**
   * Deletes a supplier and associated data from the database.
   *
   * Deletes records from the suppliers table, suppliers_info table, and updates
   * affected products in the database. Additionally, triggers any associated
   * hooks for supplier deletion.
   *
   * @param int $id The ID of the supplier to delete.
   * @return void
   */
  private static function deleteSupplier(int $id): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $Qcheck = $CLICSHOPPING_Db->prepare('select suppliers_id
                                           from :table_suppliers
                                           where suppliers_id = :suppliers_id
                                          ');

    $Qcheck->bindInt(':suppliers_id', $id);
    $Qcheck->execute();

    if ($Qcheck->fetch()) {
      $sql_array = [
        'suppliers_id' => (int)$id,
      ];

      $CLICSHOPPING_Db->delete('suppliers', $sql_array);
      $CLICSHOPPING_Db->delete('suppliers_info', $sql_array);

      $Qupdate = $CLICSHOPPING_Db->prepare('update :table_products
                                              set suppliers_id = :suppliers_id,
                                                  products_status = 0
                                              where suppliers_id = :suppliers_id1
                                            ');
      $Qupdate->bindInt(':suppliers_id', '');
      $Qupdate->bindInt(':suppliers_id1', $id);

      $Qupdate->execute();

      $CLICSHOPPING_Hooks->call('Suppliers', 'Delete');
    }
  }

  /**
   * Executes the main logic to delete a supplier based on the provided ID.
   *
   * Checks if the necessary parameters are passed via the GET request. Validates the supplier ID
   * format and sanitizes it. Calls the static method to perform the deletion of the supplier.
   *
   * @return false|string False if required parameters are missing; JSON-encoded error message if
   *                      the ID format is invalid; otherwise, no return value.
   */
  public function execute()
  {
    if (isset($_GET['sId'], $_GET['suppliers'])) {
      if (ApiSecurity::isLocalEnvironment()) {
        ApiSecurity::logSecurityEvent('Local environment detected', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
      }

      if (!isset($_GET['token'])) {
        ApiSecurity::logSecurityEvent('Missing token in supplier request');
        return false;
      }

      // Check if the token is valid
      $token = ApiSecurity::checkToken($_GET['token']);
      if (!$token) {
        return false;
      }

      // Rate limiting
      $clientIp = HTTP::getIpAddress();
      if (!ApiSecurity::checkRateLimit($clientIp, 'delete_supplier')) {
        return false;
      }

      $id = HTML::sanitize($_GET['sId']);

      if (!is_numeric($id)) {
        http_response_code(400);
        return json_encode(['error' => 'Invalid ID format']);
      }

      self::deleteSupplier($id);

      http_response_code(200);
      echo json_encode(['success' => 'Supplier deleted']);
      exit;
    } else {
      return false;
    }
  }
}