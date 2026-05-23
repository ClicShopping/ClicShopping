<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Classes;

use ClicShopping\OM\Registry;

class PasswordReset
{

  /**
   * @param string $email_address
   * @return mixed
   */
  public static function getPasswordResetCheckEmailAddress(string $email_address)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->prepare('select c.customers_id,
                                                c.customers_email_address,
                                              ci.password_reset_key,
                                              ci.password_reset_date
                                       from :table_customers c,
                                            :table_customers_info ci
                                       where c.customers_email_address = :customers_email_address
                                       and c.customers_id = ci.customers_info_id
                                       limit 1
                                     ');
    $Qcheck->bindValue(':customers_email_address', $email_address);
    $Qcheck->execute();

    $check_email_address = $Qcheck->fetch();

    return $check_email_address;
  }
}