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

class ApiDeleteManufacturer
{
  /**
   * Deletes a manufacturer and its related data from the database.
   *
   * This method removes a manufacturer record and its associated information from the database.
   * It also updates products to reset their manufacturer reference and sets their status to inactive.
   * Finally, it triggers any hooks associated with the manufacturer deletion.
   *
   * @param int $id The unique identifier of the manufacturer to be deleted.
   * @return void
   */
  private static function deleteManufacturer(int $id)
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $Qcheck = $CLICSHOPPING_Db->prepare('select manufacturers_id
                                           from :table_manufacturers
                                           where manufacturers_id = :manufacturers_id
                                          ');

    $Qcheck->bindInt(':manufacturers_id', $id);
    $Qcheck->execute();

    if ($Qcheck->fetch()) {
      $sql_array = [
        'manufacturers_id' => (int)$id,
      ];

      $CLICSHOPPING_Db->delete('manufacturers', $sql_array);
      $CLICSHOPPING_Db->delete('manufacturers_info', $sql_array);

      $Qupdate = $CLICSHOPPING_Db->prepare('update :table_products
                                              set manufacturers = :manufacturers,
                                                  products_status = 0
                                              where manufacturers = :manufacturers1
                                            ');
      $Qupdate->bindInt(':manufacturers', '');
      $Qupdate->bindInt(':manufacturers1', $id);

      $Qupdate->execute();

      $CLICSHOPPING_Hooks->call('Manufacturers', 'Delete');
    }
  }

  /**
   * Executes the deletion process for a manufacturer if the required parameters are present and valid.
   *
   * @return mixed Returns the result of the delete operation if successful, a JSON-encoded error message if the ID format is invalid, or false if necessary parameters are missing.
   */
  public function execute()
  {
    if (isset($_GET['mId'], $_GET['manufacturers'])) {

      if (ApiSecurity::isLocalEnvironment()) {
        ApiSecurity::logSecurityEvent('Local environment detected', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
      }

      if (!isset($_GET['token'])) {
        ApiSecurity::logSecurityEvent('Missing token in manufacturer request');
        return false;
      }

      // Check if the token is valid
      $token = ApiSecurity::checkToken($_GET['token']);
      if (!$token) {
        return false;
      }

      // Rate limiting
      $clientIp = HTTP::getIpAddress();
      if (!ApiSecurity::checkRateLimit($clientIp, 'delete_manufacturer')) {
        return false;
      }

      $id = HTML::sanitize($_GET['mId']);

      if (!is_numeric($id)) {
        http_response_code(400);
        return json_encode(['error' => 'Invalid ID format']);
      }

      return self::deleteManufacturer($id);

      http_response_code(200);
      echo json_encode(['success' => 'Manufacturer deleted']);
      exit;
    } else {
      return false;
    }
  }
}