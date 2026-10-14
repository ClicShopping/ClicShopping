<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Classes\Shop;

use ClicShopping\OM\Registry;

use function is_array;
use function is_numeric;

/**
 * Resolves the shipping, billing and tax addresses used while building an order from the
 * cart. Extracted verbatim from {@see Order::cart()} — it is the coupon-free, address-only
 * half of that hot-path method — so the address resolution has a single responsibility.
 *
 * The customer address (which relies on Order's protected helpers) and the whole
 * product/coupon/total computation stay in Order::cart(); this builder only turns the
 * session + address book into the three raw address arrays.
 *
 * Note: like the original cart() code, this reads $_SESSION['sendto']/['billto'] directly;
 * the caller is responsible for having initialised $_SESSION['sendto'] beforehand.
 */
class OrderCartBuilder
{
  private mixed $db;
  private mixed $hooks;

  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->hooks = Registry::get('Hooks');
  }

  /**
   * Builds the shipping, billing and tax address arrays from the session and address book.
   *
   * @param mixed $customer The Registry 'Customer' (CustomerShop) instance.
   * @param string $contentType The cart content type ('virtual' selects billing for tax).
   * @return array{shipping_address: array<string, mixed>, billing_address: array<string, mixed>, tax_address: array<string, mixed>}
   */
  public function resolveAddresses(mixed $customer, string $contentType): array
  {
    if (is_array($_SESSION['sendto']) && !empty($_SESSION['sendto'])) {
      $shipping_address = [
        'entry_firstname' => $_SESSION['sendto']['firstname'],
        'entry_lastname' => $_SESSION['sendto']['lastname'],
        'entry_company' => $_SESSION['sendto']['company'],
        'entry_street_address' => $_SESSION['sendto']['street_address'],
        'entry_suburb' => $_SESSION['sendto']['suburb'],
        'entry_postcode' => $_SESSION['sendto']['postcode'],
        'entry_city' => $_SESSION['sendto']['city'],
        'entry_zone_id' => $_SESSION['sendto']['zone_id'],
        'zone_name' => $_SESSION['sendto']['zone_name'],
        'entry_country_id' => $_SESSION['sendto']['country_id'],
        'countries_id' => $_SESSION['sendto']['country_id'],
        'countries_name' => $_SESSION['sendto']['country_name'],
        'countries_iso_code_2' => $_SESSION['sendto']['country_iso_code_2'],
        'countries_iso_code_3' => $_SESSION['sendto']['country_iso_code_3'],
        'address_format_id' => $_SESSION['sendto']['address_format_id'],
        'entry_state' => $_SESSION['sendto']['zone_name']
      ];
    } elseif (is_numeric($_SESSION['sendto'])) {
      $Qaddress = $this->db->prepare('select ab.entry_firstname,
                                               ab.entry_lastname,
                                               ab.entry_company,
                                               ab.entry_street_address,
                                               ab.entry_suburb,
                                               ab.entry_postcode,
                                               ab.entry_city,
                                               ab.entry_zone_id,
                                               ab.entry_country_id,
                                               ab.entry_state,
                                               z.zone_name,
                                               c.countries_id,
                                               c.countries_name,
                                               c.countries_iso_code_2,
                                               c.countries_iso_code_3,
                                               c.address_format_id
                                       from :table_address_book ab left join :table_zones z on (ab.entry_zone_id = z.zone_id)
                                                                   left join :table_countries c on (ab.entry_country_id = c.countries_id)
                                       where ab.customers_id = :customers_id
                                       and ab.address_book_id = :address_book_id
                                    ');
      $Qaddress->bindInt(':customers_id', $customer->getID());
      $Qaddress->bindInt(':address_book_id', (int)$_SESSION['sendto']);
      $Qaddress->execute();

      $shipping_address = $Qaddress->toArray();
    } else {
      $shipping_address = [
        'entry_firstname' => null,
        'entry_lastname' => null,
        'entry_company' => null,
        'entry_street_address' => null,
        'entry_suburb' => null,
        'entry_postcode' => null,
        'entry_city' => null,
        'entry_zone_id' => null,
        'zone_name' => null,
        'entry_country_id' => null,
        'countries_id' => null,
        'countries_name' => null,
        'countries_iso_code_2' => null,
        'countries_iso_code_3' => null,
        'address_format_id' => 0,
        'entry_state' => null
      ];
    }

    if (isset($_SESSION['billto']) && is_array($_SESSION['billto']) && !empty($_SESSION['billto'])) {
      $billing_address = [
        'entry_firstname' => $_SESSION['billto']['firstname'],
        'entry_lastname' => $_SESSION['billto']['lastname'],
        'entry_company' => $_SESSION['billto']['company'],
        'entry_street_address' => $_SESSION['billto']['street_address'],
        'entry_suburb' => $_SESSION['billto']['suburb'],
        'entry_postcode' => $_SESSION['billto']['postcode'],
        'entry_city' => $_SESSION['billto']['city'],
        'entry_zone_id' => $_SESSION['billto']['zone_id'],
        'zone_name' => $_SESSION['billto']['zone_name'],
        'entry_country_id' => $_SESSION['billto']['country_id'],
        'countries_id' => $_SESSION['billto']['country_id'],
        'countries_name' => $_SESSION['billto']['country_name'],
        'countries_iso_code_2' => $_SESSION['billto']['country_iso_code_2'],
        'countries_iso_code_3' => $_SESSION['billto']['country_iso_code_3'],
        'address_format_id' => $_SESSION['billto']['address_format_id'],
        'entry_state' => $_SESSION['billto']['zone_name']
      ];
    } else {
      $Qaddress = $this->db->prepare('select ab.entry_firstname,
                                                ab.entry_lastname,
                                                ab.entry_company,
                                                ab.entry_street_address,
                                                ab.entry_suburb,
                                                ab.entry_postcode,
                                                ab.entry_city,
                                                ab.entry_zone_id,
                                                z.zone_name,
                                                ab.entry_country_id,
                                                c.countries_id,
                                                c.countries_name,
                                                c.countries_iso_code_2,
                                                c.countries_iso_code_3,
                                                c.address_format_id,
                                                ab.entry_state
                                         from :table_address_book ab left join :table_zones z on (ab.entry_zone_id = z.zone_id)
                                                                     left join :table_countries c on (ab.entry_country_id = c.countries_id)
                                         where ab.customers_id = :customers_id
                                         and ab.address_book_id = :address_book_id
                                        ');
      $Qaddress->bindInt(':customers_id', $customer->getID());
      $Qaddress->bindInt(':address_book_id', $customer->getDefaultAddressID());
      $Qaddress->execute();

      $billing_address = $Qaddress->toArray();
    }

    if ($contentType == 'virtual') {
      $tax_address = [
        'entry_country_id' => $billing_address['entry_country_id'],
        'entry_zone_id' => $billing_address['entry_zone_id']
      ];
    } else {
      $tax_address = [
        'entry_country_id' => $shipping_address['entry_country_id'],
        'entry_zone_id' => $shipping_address['entry_zone_id']
      ];
    }

    // Extension seam: observers can inspect / react to the resolved addresses.
    $this->hooks->call('OrderCartBuilder', 'AddressesResolved', ['content_type' => $contentType]);

    return [
      'shipping_address' => $shipping_address,
      'billing_address' => $billing_address,
      'tax_address' => $tax_address,
    ];
  }
}
