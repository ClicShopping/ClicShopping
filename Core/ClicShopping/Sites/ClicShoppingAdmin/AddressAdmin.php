<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\ClicShoppingAdmin;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
/**
 * AddressAdmin class
 *
 * This class extends functionality of the Shop Address class and provides additional
 * methods specific to the application's admin context for managing addresses,
 * address formats, zones, and geo zones.
 */
class AddressAdmin extends \ClicShopping\Sites\Shop\Address
{
  /**
   * Retrieves the list of zones for a given country.
   *
   * @param int $country_id The ID of the country for which the zones should be retrieved.
   * @return array An array of zones associated with the specified country.
   */
  public static function getCountryZones($country_id)
  {
    $zones_array = parent::getCountryZones($country_id);

    return $zones_array;
  }

  /**
   * Retrieves a list of address formats from the database.
   *
   * @return array Returns an array containing address format data, where each entry includes an 'id' as the address format ID and a 'text' representation of the ID.
   */

  public static function getAddressFormats(): array
  {
    $address_format_array = [];

    $Qaddress = Registry::get('Db')->get('address_format', 'address_format_id', null, 'address_format_id');

    while ($Qaddress->fetch()) {
      $address_format_array[] = [
        'id' => $Qaddress->valueInt('address_format_id'),
        'text' => $Qaddress->valueInt('address_format_id')
      ];
    }

    return $address_format_array;
  }

  /**
   * Retrieves an array containing address format elements with their corresponding definitions.
   *
   * @return array An associative array where keys represent address format components
   *               (e.g., 'company', 'firstname') and values are localized definitions.
   */
  public static function setAddressFormatArrayAdmin(): array
  {
    $address_format_array = [
      'company' => CLICSHOPPING::getDef('text_address_company'),
      'firstname' => CLICSHOPPING::getDef('text_address_first_name'),
      'lastname' => CLICSHOPPING::getDef('text_address_last_name'),
      'street_address' => CLICSHOPPING::getDef('text_address_street_address'),
      'suburb' => CLICSHOPPING::getDef('text_address_suburb'),
      'city' => CLICSHOPPING::getDef('text_address_city'),
      'state' => CLICSHOPPING::getDef('text_address_state'),
      'postcode' => CLICSHOPPING::getDef('text_address_postcode'),
      'country' => CLICSHOPPING::getDef('text_address_country')
    ];

    return $address_format_array;
  }

  /**
   * Retrieves the formatted address for display using a specified format ID.
   *
   * @param int $address_format_id The ID of the address format to be applied.
   * @return string The formatted address string based on the provided format ID.
   */
  public static function getAddressFormatRadio(int $address_format_id)
  {
    return parent::addressFormat($address_format_id, AddressAdmin::setAddressFormatArrayAdmin(), true, '', '<br />');
  }

  /**
   * Retrieves the name of a geo zone based on its ID.
   *
   * @param int $geo_zone_id The ID of the geo zone to fetch the name for.
   * @return string|null Returns the geo zone name as a string if found,
   *                  or the provided geo zone ID as an integer if not found,
   *                  or null on failure.
   */
  public static function getGeoZoneName(int $geo_zone_id):  string|null
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qzones = $CLICSHOPPING_Db->prepare('select geo_zone_name
                                           from :table_geo_zones
                                           where geo_zone_id = :geo_zone_id
                                           ');
    $Qzones->bindInt(':geo_zone_id', $geo_zone_id);
    $Qzones->execute();

    if ($Qzones->fetch() === false) {
      $geo_zone_name = $geo_zone_id;
    } else {
      $geo_zone_name = $Qzones->value('geo_zone_name');
    }

    return $geo_zone_name;
  }

  /**
   * Generates an HTML dropdown menu containing geographical zones.
   *
   * @param string $parameters The name and identifier of the select element.
   * @param string $selected The ID of the pre-selected geographical zone (optional).
   * @return string The HTML string for the dropdown menu.
   */
  public static function getGeoZonesPullDown(string $parameters, string $selected = ''): string
  {
    $select_string = '<select name="' . $parameters . '" id="' . $parameters . '">';

    $Qzones = Registry::get('Db')->get('geo_zones', ['geo_zone_id',
      'geo_zone_name'
    ],
      null,
      'geo_zone_name'
    );

    while ($Qzones->fetch()) {
      $select_string .= '<option value="' . $Qzones->valueInt('geo_zone_id') . '"';

      if ($selected == $Qzones->valueInt('geo_zone_id')) {
        $select_string .= ' SELECTED';
      }

      $select_string .= '>' . $Qzones->value('geo_zone_name') . '</option>';
    }

    $select_string .= '</select>';

    return $select_string;
  }

  /**
   * Retrieves a listing of address book entries for a given customer ID.
   *
   * @param int $id The ID of the customer whose address book entries are to be retrieved.
   * @return mixed The query result object containing address book entries.
   */
  public static function getListingAdmin(int $id): mixed
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $QaddressesBook = $CLICSHOPPING_Db->prepare('select address_book_id,
                                                         entry_firstname as firstname,
                                                         entry_lastname as lastname,
                                                         entry_company as company,
                                                         entry_street_address as street_address,
                                                         entry_suburb as suburb,
                                                         entry_city as city,
                                                         entry_postcode as postcode,
                                                         entry_state as state,
                                                         entry_zone_id as zone_id,
                                                         entry_country_id as country_id ,
                                                         entry_telephone as telephone
                                                  from :table_address_book
                                                  where customers_id = :customers_id
                                                  order by address_book_id
                                                  ');
    $QaddressesBook->bindInt(':customers_id', (int)$id);
    $QaddressesBook->execute();

    return $QaddressesBook;
  }
}