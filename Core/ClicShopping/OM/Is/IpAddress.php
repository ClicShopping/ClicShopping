<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Is;

use UnexpectedValueException;

/**
 * Class IpAddress
 *
 * Provides a method to validate if a given value is an IP address.
 * Supports validation for IPv4, IPv6, or any IP address type.
 */

class IpAddress implements \ClicShopping\OM\Interfaces\IsInterface
{
  /**
   * Validates whether the provided value is a valid IP address.
   *
   * @param mixed $value The value to be checked as an IP address.
   * @param string $type The type of IP address to validate against. Possible values are:
   *                     'any' for both IPv4 and IPv6 (default),
   *                     'ipv4' for IPv4 validation,
   *                     'ipv6' for IPv6 validation.
   *
   * @return bool Returns true if the value is a valid IP address of the specified type. Returns false otherwise.
   */
  public static function execute(mixed $value, string $type = 'any'): bool
  {
    if (empty($value)) {
      return false;
    }

    try {
      $options = [];

      if ($type === 'any') {
        $options['flags'] = \FILTER_FLAG_IPV4 | \FILTER_FLAG_IPV6;
      } elseif ($type === 'ipv4') {
        $options['flags'] = \FILTER_FLAG_IPV4;
      } elseif ($type === 'ipv6') {
        $options['flags'] = \FILTER_FLAG_IPV6;
      } else {
        throw new UnexpectedValueException('Invalid type "' . $type . '". Expecting "any", "ipv4", or "ipv6".');
      }

      return filter_var($value, \FILTER_VALIDATE_IP, $options) !== false;
    } catch (UnexpectedValueException $e) {
      trigger_error('ClicShopping\OM\Is\IpAddress: ' . $e->getMessage());
    }

    return false;
  }
}