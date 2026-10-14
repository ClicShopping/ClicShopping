<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Account;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class AccountGdprNoIp
{
  /**
   * Retrieves the IP address associated with the current customer from the database.
   *
   * @return string Returns the IP address of the customer as a string.
   */
  private function getIpAddress(): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    $Qgdpr = $CLICSHOPPING_Db->prepare('select no_ip_address
                                          from :table_customers_gdpr
                                          where customers_id = :customers_id
                                        ');
    $Qgdpr->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
    $Qgdpr->execute();

    $ip_address = $Qgdpr->valueInt('no_ip_address');

    return $ip_address;
  }

  /**
   * @return string
   */
  public function display(): string
  {
    $output = '<div>
                    <ul class="list-group list-group-flush">
                      <li class="list-group-item">
                        <div class="mt-1"></div>
                           ' . CLICSHOPPING::getDef('module_account_customers_gdpr_no_ip_address') . '
                          <label class="switch">
                            ' . HTML::checkboxField('no_ip_address', $this->getIpAddress(), $this->getIpAddress(), 'class="success"') . '
                            <span class="slider"></span>
                          </label>
                      </li>
                    </ul>
                  </div>
                 ';

    return $output;
  }
}
