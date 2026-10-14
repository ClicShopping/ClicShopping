<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Account;

use ClicShopping\OM\Registry;

class AccountGdprCallDeleteCustomersInformations
{

  /**
   * Resets customer information in the database including date of last logon, number of logons,
   * account creation date, and account last modified date if a specific request to delete
   * customer information is detected.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (isset($_POST['delete_customers_info'])) {
      $Qupdate = $CLICSHOPPING_Db->prepare('update :table_customers_info
                                              set customers_info_date_of_last_logon = null,
                                                  customers_info_number_of_logons = 0,
                                                  customers_info_date_account_created = null,
                                                  customers_info_date_account_last_modified = null
                                              where customers_info_id = :customers_info_id
                                            ');
      $Qupdate->bindInt(':customers_info_id', $CLICSHOPPING_Customer->getID());
      $Qupdate->execute();
    }
  }
}
