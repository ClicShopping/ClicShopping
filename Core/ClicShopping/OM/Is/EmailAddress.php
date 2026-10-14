<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Is;

use EmailChecker\EmailChecker;
use function strlen;
use const FILTER_VALIDATE_EMAIL;

/**
 * Class EmailAddress
 *
 * This class provides functionality for validating email addresses.
 * It checks the format of the email address and optionally verifies
 * the existence of the domain through DNS records.
 *
 * Methods:
 * - execute: Validates an email address based on format, length, and optional DNS checks.
 */
class EmailAddress implements \ClicShopping\OM\Interfaces\IsInterface
{
  /**
   * Validates an email address and optionally checks its DNS records.
   *
   * @param mixed $value The email address to validate.
   * @param bool $check_dns Optional. Whether to perform a DNS check for the domain of the email address. Default is false.
   * @return bool Returns true if the email address is valid and, if applicable, the DNS check passes. Returns false otherwise.
   */
  public static function execute(mixed $value, bool $check_dns = false): bool
  {
    if (empty($value) || (strlen($value) > 191)) {
      return false;
    }

    if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
      return false;
    }

    if (ENTRY_EMAIL_ADDRESS_CHECKER == 'true') {
      $checker = new EmailChecker();

      if ($checker->isValid($value) === false) {
        return false;
      }
    }

    if ($check_dns === true || ENTRY_EMAIL_ADDRESS_CHECK == 'true') {
      $domain = explode('@', $value, 2);

      // international domains (eg, containing german umlauts) are converted to punycode
      if (mb_detect_encoding($domain[1], 'ASCII', true) !== 'ASCII') {
        $domain[1] = idn_to_ascii($domain[1]);
      }

      if ($domain[1] === false) {
        return false;
      }

      if (!checkdnsrr($domain[1], 'MX') && !checkdnsrr($domain[1], 'A')) {
        return false;
      }
    }

    return true;
  }
}
