<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Customers\Classes\Shared;

use ClicShopping\OM\Registry;

/**
 * A customer account is deleted only after the customer types back the number e-mailed to them:
 * a stolen session alone cannot erase an account.
 *
 * Only the SHA-256 of the number is stored (customers_gdpr.delete_key). Expiry is computed by the
 * database clock, the one that wrote the date.
 */
class AccountDeletionRequest
{
  public const int LIFETIME_MINUTES = 30;
  public const int MAX_ATTEMPTS = 5;

  /**
   * Arms a new request, replacing any pending one.
   *
   * @param int $customers_id Customer id
   * @return string The 6-digit number to e-mail, never stored in clear
   */
  public static function arm(int $customers_id): string
  {
    $db = Registry::get('Db');
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $data = ['delete_key' => hash('sha256', $code), 'delete_key_date' => 'now()', 'delete_key_attempts' => 0];

    $Qcheck = $db->get('customers_gdpr', 'id', ['customers_id' => $customers_id]);

    if ($Qcheck->fetch() === false) {
      $db->save('customers_gdpr', $data + ['customers_id' => $customers_id, 'date_added' => 'now()']);
    } else {
      $db->save('customers_gdpr', $data, ['customers_id' => $customers_id]);
    }

    return $code;
  }

  /**
   * @param int $customers_id Customer id
   * @return bool True while a number can still be typed back
   */
  public static function isPending(int $customers_id): bool
  {
    return self::state($customers_id)['live'] ?? false;
  }

  /**
   * Checks a typed-back number. A match consumes the request; a miss counts, and the last
   * allowed miss cancels it — a new number must then be asked for.
   *
   * @param int $customers_id Customer id
   * @param string $code Number typed by the customer
   * @return bool True when the number matches a live request
   */
  public static function confirm(int $customers_id, string $code): bool
  {
    $state = self::state($customers_id);

    if ($state === null) {
      return false;
    }

    if (!$state['live']) {
      self::cancel($customers_id);

      return false;
    }

    if (hash_equals($state['key'], hash('sha256', trim($code)))) {
      self::cancel($customers_id);

      return true;
    }

    if ($state['attempts'] + 1 >= self::MAX_ATTEMPTS) {
      self::cancel($customers_id);
    } else {
      Registry::get('Db')->save('customers_gdpr', ['delete_key_attempts' => $state['attempts'] + 1], ['customers_id' => $customers_id]);
    }

    return false;
  }

  /**
   * @param int $customers_id Customer id
   * @return void
   */
  public static function cancel(int $customers_id): void
  {
    Registry::get('Db')->save('customers_gdpr', ['delete_key' => 'null', 'delete_key_date' => 'null', 'delete_key_attempts' => 0], ['customers_id' => $customers_id]);
  }

  /**
   * @param int $customers_id Customer id
   * @return array{key: string, attempts: int, live: bool}|null Null when no request was armed
   */
  private static function state(int $customers_id): ?array
  {
    $Qrequest = Registry::get('Db')->prepare('select delete_key,
                                                     delete_key_attempts,
                                                     delete_key_date >= now() - interval ' . self::LIFETIME_MINUTES . ' minute as fresh
                                              from :table_customers_gdpr
                                              where customers_id = :customers_id
                                              and delete_key is not null
                                              limit 1');
    $Qrequest->bindInt(':customers_id', $customers_id);
    $Qrequest->execute();

    if ($Qrequest->fetch() === false) {
      return null;
    }

    $attempts = $Qrequest->valueInt('delete_key_attempts');

    return [
      'key' => (string)$Qrequest->value('delete_key'),
      'attempts' => $attempts,
      'live' => $Qrequest->valueInt('fresh') === 1 && $attempts < self::MAX_ATTEMPTS,
    ];
  }
}
