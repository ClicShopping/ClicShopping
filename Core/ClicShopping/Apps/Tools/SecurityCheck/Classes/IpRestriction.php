<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Tools\SecurityCheck\Classes;

use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

class IpRestriction
{
  /**
   * Retrieves the remote IP address from the server.
   *
   * @return string|null Returns the IP address as a string if available, or null if not.
   */
  public static function getRemoteAddress(): ?string
  {
    $address_server = HTTP::getIpAddress();

    return $address_server;
  }

  /**
   * Checks if the current remote IP address is allowed based on shop IP restrictions.
   *
   * This function queries the database for IP restrictions defined in the shop configuration.
   * If the remote address matches any of the allowed IPs, the function returns true, otherwise false.
   *
   * @return bool Returns true if the remote IP is allowed; otherwise, false.
   */
  public static function checkAllIpShopRestriction(): bool
  {
    $ClISHOPPING_db = Registry::get('Db');

    $allowed_ip = false;

    $Qrestriction = $ClISHOPPING_db->prepare('select distinct ip_restriction
                                                from :table_ip_restriction
                                                where ip_status_shop = 1
                                               ');
    $Qrestriction->execute();

    $restriction = $Qrestriction->fetchAll();

    foreach ($restriction as $value) {
      if (trim($value['0']) === static::getRemoteAddress()) {
        $allowed_ip = true;
        break;
      }
    }

    return $allowed_ip;
  }

  /**
   * Saves the IP restriction data if the IP passes either shop or admin restriction checks.
   *
   * @return void
   */
  public static function saveIpRestriction(): void
  {
    $ClISHOPPING_db = Registry::get('Db');

    if (static::checkAllIpShopRestriction() === true || static::checkAllIpAdminRestriction() === true) {
      $ClISHOPPING_db->save('ip_restriction_stats', ['ip_remote' => static::getRemoteAddress()]);
    }
  }

  /**
   * Checks if the remote IP address is allowed based on admin IP restrictions.
   *
   * This method retrieves the list of IP restrictions configured for admin access
   * and verifies if the calling remote IP address matches any of the allowed IPs.
   *
   * @return bool Returns true if the remote IP address is allowed for admin access, false otherwise.
   */
  public static function checkAllIpAdminRestriction(): bool
  {
    $ClISHOPPING_db = Registry::get('Db');

    $allowed_ip = false;

    $Qrestriction = $ClISHOPPING_db->prepare('select distinct ip_restriction
                                                from :table_ip_restriction
                                                where ip_status_admin = 1
                                               ');
    $Qrestriction->execute();

    $restriction = $Qrestriction->fetchAll();

    foreach ($restriction as $value) {
      if (trim($value['0']) === static::getRemoteAddress()) {
        $allowed_ip = true;
        break;
      }
    }

    return $allowed_ip;
  }

  /**
   * Updates the IP restriction shop status for a given ID.
   *
   * @param int $id The ID of the IP restriction record to update.
   * @param int $status The new status to set (1 to activate, 0 to deactivate).
   * @return mixed Returns true if the status is successfully updated, false on error, or -1 if an invalid status is provided.
   */
  public static function getIpRestrictionShopStatus(int $id, int $status)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if ($status == 1) {
      return $CLICSHOPPING_Db->save('ip_restriction', ['ip_status_shop' => 1], ['id' => (int)$id]);
    } elseif ($status == 0) {
      return $CLICSHOPPING_Db->save('ip_restriction', ['ip_status_shop' => 0], ['id' => (int)$id]);
    } else {
      return -1;
    }
  }

  /**
   * Updates the administrative IP restriction status for a given ID and status.
   *
   * @param int $id The unique identifier of the IP restriction entry.
   * @param int $status The new status for the IP restriction (1 for enabled, 0 for disabled).
   * @return bool|int Returns true on successful update of the status, false on failure, and -1 if an invalid status is provided.
   */
  public static function getIpRestrictionAdminStatus(int $id, int $status)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if ($status == 1) {
      return $CLICSHOPPING_Db->save('ip_restriction', ['ip_status_admin' => 1], ['id' => (int)$id]);
    } elseif ($status == 0) {
      return $CLICSHOPPING_Db->save('ip_restriction', ['ip_status_admin' => 0], ['id' => (int)$id]);
    } else {
      return -1;
    }
  }
}