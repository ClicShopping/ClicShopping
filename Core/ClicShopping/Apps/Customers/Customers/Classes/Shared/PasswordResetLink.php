<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Customers\Classes\Shared;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

/**
 * Single point that arms a customer password reset link, consumed by Shop Account&PasswordReset.
 *
 * The customer chooses the password: nothing here generates, stores or mails one.
 */
class PasswordResetLink
{
  /**
   * Stores a fresh single-use reset key and returns the shop URL that consumes it.
   *
   * @param int $customers_id Customer id
   * @param string $email_address Account e-mail as stored: the reset page looks the account up by it
   * @return string Absolute shop URL, valid one day (enforced by Account&PasswordReset)
   */
  public static function create(int $customers_id, string $email_address): string
  {
    $reset_key = Hash::getRandomString(40);

    Registry::get('Db')->save('customers_info',
      ['password_reset_key' => $reset_key, 'password_reset_date' => 'now()'],
      ['customers_info_id' => $customers_id]
    );

    // Query form on purpose: CLICSHOPPING::link() differs by calling site and SEO mode; the shop reads both.
    return HTTP::getShopUrlDomain() . CLICSHOPPING::getConfig('bootstrap_file') . '?Account&PasswordReset&account=' . urlencode($email_address) . '&key=' . $reset_key;
  }
}
