<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\WhosOnline\Classes\Shop;

use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Customers\Classes\Shop\CustomerShop as NewCustomer;

class WhosOnlineShop
{
  protected $contents;
  protected $total;
  protected $weight;
  private mixed $db;

  public function __construct()
  {
  }

  /**
   * Updates the "who's online" tracking data by managing session information
   * and storing/retrieving customer details, IP address, last activity time,
   * user agent, and HTTP referer into the database. Also handles cleanup of
   * expired records based on a defined time interval.
   *
   * @return void
   */
  public static function getUpdateWhosOnline()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if (!Registry::exists('NewCustomer')) {
      Registry::set('NewCustomer', new NewCustomer());
    }

    $CLICSHOPPING_Customer = Registry::get('NewCustomer');

    $wo_customer_id = 0;
    $wo_full_name = 'Guest';

    if (isset($_SESSION['customer_id'])) {
      $wo_customer_id = $CLICSHOPPING_Customer->getID();

      $Qcustomer = $CLICSHOPPING_Db->prepare('select customers_firstname,
                                                        customers_lastname
                                                from :table_customers
                                                where customers_id = :customers_id
                                                ');
      $Qcustomer->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
      $Qcustomer->execute();

      $wo_full_name = $Qcustomer->value('customers_firstname') . ' ' . $Qcustomer->value('customers_lastname');
    }

    $wo_session_id = session_id();
    $wo_ip_address = HTTP::getIpAddress();

    if (\is_null($wo_ip_address)) { // database table field (ip_address) is not_null
      $wo_ip_address = '';
    }

    $wo_last_page_url = '';

    if (isset($_SERVER['REQUEST_URI']) && !empty($_SERVER['REQUEST_URI'])) {
      $wo_last_page_url = $_SERVER['REQUEST_URI'];
    }

    $current_time = time();
    $xx_mins_ago = ($current_time - 900);

// remove entries that have expired
    $Qdel = $CLICSHOPPING_Db->prepare('delete from :table_whos_online
                                         where time_last_click < :time_last_click
                                        ');
    $Qdel->bindInt(':time_last_click', $xx_mins_ago);
    $Qdel->execute();

    $Qsession = $CLICSHOPPING_Db->prepare('select session_id
                                             from :table_whos_online
                                             where session_id = :session_id
                                             limit 1
                                             ');

    $Qsession->bindValue(':session_id', $wo_session_id);
    $Qsession->execute();

    if (isset($_SERVER['HTTP_REFERER'])) {
      $referer = HTML::sanitize($_SERVER['HTTP_REFERER']);
    } else {
      $referer = 'Unknown';
    }

    if (!empty(gethostbyaddr($wo_ip_address))) {
      $referer = gethostbyaddr($wo_ip_address);
    } else {
      $referer = 'localhost or not defined';
    }


    if (isset($_SERVER['HTTP_USER_AGENT'])) {
      $user_agent = HTML::sanitize($_SERVER['HTTP_USER_AGENT']);
    } else {
      $user_agent = 'user agent or not defined';
    }

    if ($Qsession->fetch() !== false) {
      $sql_array = [
        'customer_id' => $wo_customer_id,
        'full_name' => $wo_full_name,
        'ip_address' => $wo_ip_address,
        'time_last_click' => $current_time,
        'last_page_url' => $wo_last_page_url,
        'http_referer' => $referer,
        'user_agent' => $user_agent
      ];

      $CLICSHOPPING_Db->save('whos_online', $sql_array, ['session_id' => $wo_session_id]);
    } else {
      $sql_array = [
        'customer_id' => $wo_customer_id,
        'full_name' => $wo_full_name,
        'session_id' => $wo_session_id,
        'ip_address' => $wo_ip_address,
        'time_entry' => $current_time,
        'time_last_click' => $current_time,
        'last_page_url' => $wo_last_page_url,
        'http_referer' => $referer,
        'user_agent' => $user_agent
      ];

      $CLICSHOPPING_Db->save('whos_online', $sql_array);
    }
  }

  /**
   * Updates the session ID in the `whos_online` table for a specified old session ID.
   *
   * @param string $old_id The current session ID to be updated.
   * @param string $new_id The new session ID to replace the old one.
   * @return void This method does not return a value.
   */
  public static function getWhosOnlineUpdateSession_id(string $old_id, string $new_id)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('whos_online', ['session_id' => $new_id], ['session_id' => $old_id]);
  }
}
