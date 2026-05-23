<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Account;

use ClicShopping\OM\Registry;
use function is_null;

class AccountGdprCallNoAcceptIP
{
  /**
   * Handles the execution of customer GDPR data validation and updates within the database.
   *
   * This method checks if a customer's GDPR data exists in the database. If not, it creates a new record.
   * If the data exists, it updates the `no_ip_address` status and the timestamp accordingly.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    $Qcheck = $CLICSHOPPING_Db->prepare('select customers_id,
                                                  no_ip_address
                                           from :table_customers_gdpr
                                           where customers_id = :customers_id
                                         ');
    $Qcheck->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
    $Qcheck->execute();

    if ($Qcheck->fetch() === false) {
      $CLICSHOPPING_Db->save('customers_gdpr', ['customers_id' => $CLICSHOPPING_Customer->getID(), 'date_added' => 'now()']);
    } else {
      if (!is_null($_POST['no_ip_address'])) {
        $no_ip_address = 1;
      } else {
        $no_ip_address = 0;
      }

      $Qupdate = $CLICSHOPPING_Db->prepare('update :table_customers_gdpr
                                              set no_ip_address = :no_ip_address,
                                              customers_id = :customers_id,
                                              date_added = now()
                                            ');
      $Qupdate->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
      $Qupdate->bindInt(':no_ip_address', $no_ip_address);

      $Qupdate->execute();
    }
  }
}
