<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
/**
 * Handles address-related operations such as formatting addresses,
 * retrieving country and zone details, and managing address-related data.
 */
class Address
{
  private mixed $db;

  /**
   * Constructor method for initializing the class.
   *
   * @return void
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
  }

  /**
   * Formats an address based on a specified address format ID and other attributes.
   *
   * @param int $address_format_id The ID of the address format to be used for formatting.
   * @param array $address The address data including components like company, street, suburb, city, state, country, etc.
   * @param bool $html Whether the formatted address should be in HTML format or plain text.
   * @param string $boln The beginning of line notation (used for formatting).
   * @param string $eoln The end of line notation (used for formatting).
   * @return string The formatted address as a string.
   */
  public static function addressFormat($address_format_id, $address, $html, $boln, $eoln)
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (!empty($CLICSHOPPING_Customer)) {
      $customer_group_id = $CLICSHOPPING_Customer->getCustomersGroupID();
    } else {
      $customer_group_id = 0;
    }

    $Qformat = Registry::get('Db')->get('address_format', 'address_format', ['address_format_id' => (int)$address_format_id]);

    // Check if format exists, otherwise use default format
    if ($Qformat->fetch() === false) {
      // Fallback to default format if not found
      $Qformat = Registry::get('Db')->get('address_format', 'address_format', ['address_format_id' => 1]);
      $Qformat->fetch();
    }

    $company = Hash::displayDecryptedDataText($address['company']);
    $street_address = Hash::displayDecryptedDataText($address['street_address']);
    $suburb = Hash::displayDecryptedDataText($address['suburb']);
    $city = Hash::displayDecryptedDataText($address['city']);
    $postcode = Hash::displayDecryptedDataText($address['postcode']);

    $replace = [
      '$company' => $company,
      '$firstname' => '',
      '$lastname' => '',
      '$street' =>  $street_address,
      '$suburb' => $suburb,
      '$city' => $city,
      '$state' => $address['state'],
      '$postcode' => $postcode,
      '$country' => ''
    ];

    if (isset($address['firstname']) && !empty($address['firstname'])) {
      $firstname = Hash::displayDecryptedDataText($address['firstname']);
      $lastname = Hash::displayDecryptedDataText($address['lastname']);
      $replace['$firstname'] = $firstname;
      $replace['$lastname'] = $lastname;
    } elseif (isset($address['name']) && !empty($address['name'])) {
      $name = Hash::displayDecryptedDataText($address['name']);
      $replace['$firstname'] = $name;
    }

    if (isset($address['country_id']) && !empty($address['country_id'])) {
      $replace['$country'] = self::getCountryName($address['country_id']);

      if (isset($address['zone_id']) && !empty($address['zone_id'])) {
        $replace['$state'] = static::getZoneName($address['country_id'], $address['zone_id'], $replace['$state']);
      }
    } elseif (isset($address['country']) && !empty($address['country'])) {
      if (CLICSHOPPING::getSite() === 'ClicShoppingAdmin') {
        $replace['$country'] = HTML::outputProtected($address['country']);
      } else {
        $replace['$country'] = HTML::outputProtected(is_array($address['country']) ? ($address['country']['title'] ?? '') : $address['country']);
      }
    }

    $replace['$zip'] = $replace['$postcode'];

    if ($html) {
// HTML Mode
      $HR = '<hr />';
      $hr = '<hr />';
      if (($boln == '') && ($eoln == "\n")) { // Values not specified, use rational defaults
        $CR = '<br />';
        $cr = '<br />';
        $eoln = $cr;
      } else { // Use values supplied
        $CR = $eoln . $boln;
        $cr = $CR;
      }
    } else {
// Text Mode
      $CR = $eoln;
      $cr = $CR;
      $HR = '----------------------------------------';
      $hr = '----------------------------------------';
    }

    $replace['$CR'] = $CR;
    $replace['$cr'] = $cr;
    $replace['$HR'] = $HR;
    $replace['$hr'] = $hr;

    $replace['$statecomma'] = '';
    $replace['$streets'] = $replace['$street'];

    if ($replace['$suburb'] != '') $replace['$streets'] = $replace['$street'] . $replace['$cr'] . $replace['$suburb'];
    if ($replace['$state'] != '') $replace['$statecomma'] = $replace['$state'] . ', ';

    $address = strtr($Qformat->value('address_format'), $replace);

    if ((($customer_group_id == 0) && (ACCOUNT_COMPANY == 'true') && (!empty($replace['$company']))) || (($customer_group_id != 0) && (ACCOUNT_COMPANY_PRO == 'true') && (!\is_null($replace['$company'])))) {
      $address = $replace['$company'] . $replace['$cr'] . $address;
    }

    return $address;
  }

  /**
   * Retrieves the address format ID associated with a given country ID.
   *
   * @param int $country_id The ID of the country for which the address format ID will be retrieved.
   * @return int The address format ID for the specified country. Defaults to 1 if no specific format is found.
   */

  public static function getAddressFormatId(int $country_id): int
  {
    $format_id = 1;

    $Qformat = Registry::get('Db')->get('countries', 'address_format_id', ['countries_id' => (int)$country_id]);

    if ($Qformat->fetch() !== false) {
      $format_id = $Qformat->valueInt('address_format_id');
    }

    return $format_id;
  }


  /**
   * Retrieves the zone code for a specified country and zone.
   *
   * @param int $country_id The ID of the country to which the zone belongs.
   * @param int $zone_id The ID of the zone to retrieve the code for.
   * @param string $default_zone The default zone code to return if no matching zone is found.
   * @return string The zone code if found, otherwise the default zone code.
   */

  public static function getZoneCode(int $country_id, int $zone_id, string $default_zone): string
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qzone = $CLICSHOPPING_Db->prepare('select zone_code
                                          from :table_zones
                                          where zone_country_id = :zone_country_id
                                          and zone_id = :zone_id
                                          and zone_status = 0
                                         ');

    $Qzone->bindInt(':zone_country_id', (int)$country_id);
    $Qzone->bindInt(':zone_id', (int)$zone_id);

    $Qzone->execute();

    if ($Qzone->fetch() !== false) {
      return $Qzone->value('zone_code');
    } else {
      return $default_zone;
    }
  }

  /**
   * Retrieves the name of the zone based on the provided country ID, zone ID, and default zone.
   *
   * @param int $country_id The ID of the country for which the zone name should be retrieved.
   * @param int|null $zone_id The ID of the zone to be fetched. If null, the default zone is used.
   * @param string|null $default_zone The default zone to return if no matching zone is found or if the zone_id is null.
   *
   * @return string|null The name of the zone if found, or the default zone value if no matching zone exists.
   */
  public static function getZoneName($country_id, $zone_id = null, $default_zone = null)
  {

    if (!\is_null($zone_id)) {
      $Qzone = Registry::get('Db')->get('zones', 'zone_name', ['zone_country_id' => (int)$country_id,
          'zone_id' => (int)$zone_id,
          'zone_status' => 0
        ]
      );

      if ($Qzone->fetch() !== false) {
        return $Qzone->value('zone_name');
      } else {
        return $default_zone;
      }
    } elseif (\is_null($default_zone)) {
      $Qzone = Registry::get('Db')->get('zones', 'zone_name', ['zone_country_id' => (int)$country_id,
          'zone_name' => $default_zone,
          'zone_status' => 0
        ]
      );

      if ($Qzone->fetch() !== false) {
        return $Qzone->value('zone_name');
      } else {
        return $default_zone;
      }
    } else {
      return $default_zone;
    }
  }

  /**
   * Retrieves a list of countries from the database. If a specific country ID is provided,
   * retrieves information for the specified country. Optionally includes ISO codes if requested.
   *
   * @param int|null $countries_id The ID of the specific country to retrieve. If null, retrieves all countries.
   * @param bool $with_iso_codes Whether to include ISO codes (2 or 3) in the result. Defaults to false.
   * @return array Returns an array containing countries information. The content depends on the parameters provided.
   */

  public static function getCountries($countries_id = null, bool $with_iso_codes = false): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $countries_array = [];

    if (!\is_null($countries_id)) {
      if ($with_iso_codes === true) {
        $Qcountries = $CLICSHOPPING_Db->prepare('select countries_name,
                                                          countries_iso_code_2,
                                                          countries_iso_code_3
                                                   from :table_countries
                                                   where countries_id = :countries_id
                                                   and status = 1
                                                   order by countries_name
                                                  ');
        $Qcountries->bindInt(':countries_id', $countries_id);
        $Qcountries->execute();

        $countries_array = $Qcountries->toArray();
      } else {

        $Qcountries = $CLICSHOPPING_Db->prepare('select countries_name
                                                   from :table_countries
                                                   where countries_id = :countries_id
                                                   and status = 1
                                                  ');
        $Qcountries->bindInt(':countries_id', $countries_id);
        $Qcountries->execute();

        $countries_array = $Qcountries->toArray();
      }
    } else {
      $countries_array = $CLICSHOPPING_Db->query('select countries_id,
                                                          countries_name,
                                                          countries_iso_code_2
                                                   from :table_countries
                                                   where status = 1
                                                    order by countries_name
                                                  ')->fetchAll();
    }

    return $countries_array;
  }

  /**
   * Retrieves the name of the country based on the provided country ID.
   *
   * @param int $country_id The ID of the country.
   * @return string The name of the country.
   */
  public static function getCountryName(int $country_id): string
  {
    $country_array = self::getCountries($country_id);

    return $country_array['countries_name'];

  }

  /**
   * Retrieves country information along with ISO codes.
   *
   * @param int $countries_id The ID of the country to fetch information for.
   * @return array Returns an array containing country details and their respective ISO codes.
   */
  public function getCountriesWithIsoCodes(int $countries_id)
  {
    return static::getCountries($countries_id, true);
  }

  /**
   * Retrieves a list of zones based on the provided country ID or retrieves all zones if no ID is provided.
   *
   * @param int|null $id The ID of the country to filter the zones. If null, retrieves zones from all countries.
   * @return array An array of zones, where each zone includes the zone ID, name, country ID, and country name.
   */

  public static function getZones(int|null $id = null): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $zones_array = [];

    $sql_query = 'select z.zone_id,
                           z.zone_country_id,
                           z.zone_name,
                           z.zone_status,
                           c.countries_name
                    from :table_zones z,
                         :table_countries c
                    where';

    if (!empty($id)) {
      $sql_query .= ' z.zone_country_id = :zone_country_id and';
    }

    $sql_query .= ' z.zone_country_id = c.countries_id
                      and z.zone_status = 0
                      order by c.countries_name,
                                z.zone_name';

    if (!empty($id)) {
      $Qzones = $CLICSHOPPING_Db->prepare($sql_query);
      $Qzones->bindInt(':zone_country_id', $id);
    } else {
      $Qzones = $CLICSHOPPING_Db->query($sql_query);
    }

    $Qzones->execute();

    while ($Qzones->fetch()) {
      $zones_array[] = [
        'id' => $Qzones->valueInt('zone_id'),
        'name' => $Qzones->value('zone_name'),
        'country_id' => $Qzones->valueInt('zone_country_id'),
        'country_name' => $Qzones->value('countries_name')
      ];
    }

    return $zones_array;
  }

  /**
   * Retrieves the zones associated with a specific country.
   *
   * @param int $country_id The ID of the country for which zones are to be fetched.
   * @return array An array of zones, where each zone contains 'id' and 'text' keys representing the zone ID and zone name respectively.
   */
  public static function getCountryZones($country_id)
  {
    $zones_array = [];

    $Qzones = Registry::get('Db')->get('zones', [
      'zone_id',
      'zone_name'
    ], [
      'zone_country_id' => (int)$country_id,
      'zone_status' => 0
    ], 'zone_name'
    );

    while ($Qzones->fetch()) {
      $zones_array[] = [
        'id' => $Qzones->valueInt('zone_id'),
        'text' => $Qzones->value('zone_name')
      ];
    }

    return $zones_array;
  }

  /**
   * Prepares a list of country zones for use in a dropdown menu.
   *
   * @param string $country_id The ID of the country for which the zones are being retrieved. Default is an empty string.
   * @return array An array of country zones with each element containing an 'id' and 'text' key.
   */
  public static function getPrepareCountryZonesPullDown($country_id = ''): array
  {
    $zones = self::getCountryZones($country_id);

    if (\count($zones) > 0) {
      $zones_select = array([
        'id' => '',
        'text' => CLICSHOPPING::getDef('text_selected')
      ]
      );
      $zones = array_merge($zones_select, $zones);

    } else {
      $zones = array([
        'id' => '',
        'text' => CLICSHOPPING::getDef('text_selected')
      ]
      );
    }

    return $zones;
  }

  /**
   * Retrieves all zones associated with the given country ID.
   *
   * @param int $country_id The ID of the country for which zones should be retrieved.
   * @return array|false Returns an array of zones (each containing 'id' and 'text') if zones are found, or false if no zones exist.
   */
  public function getAllZones(int $country_id)
  {
    $Qcheck = $this->db->prepare('select zone_name
                                     from :table_zones
                                     where zone_country_id = :zone_country_id
                                     and zone_status = 0
                                     order by zone_name
                                    ');
    $Qcheck->bindInt(':zone_country_id', $country_id);
    $Qcheck->execute();

    if ($Qcheck->rowCount() > 1) {
      while ($Qcheck->fetch()) {
        $zones_array[] = [
          'id' => $Qcheck->value('zone_name'),
          'text' => $Qcheck->value('zone_name')
        ];
      }

      return $zones_array;
    }

    return false;
  }


  /**
   * Generates a dropdown menu or input field for selecting or entering a state/zone based on the provided country ID.
   *
   * @param int|string $country_id The ID of the country used to retrieve the list of zones.
   * @return string The HTML string for the zone dropdown menu if zones exist, or an input field if no zones are found.
   */
  public function getZoneDropdown($country_id): string
  {
    $zones_array = $this->getAllZones($country_id);

    if ($zones_array !== false) {
      $result = HTML::selectMenu('state', $zones_array, 'id="inputState" aria-describedby="atState"');
    } else {
      $result = HTML::inputField('state', '', 'id="atState" placeholder="' . CLICSHOPPING::getDef('entry_state') . '" aria-required="true" aria-describedby="atState"');
    }

    return $result;
  }

  /**
   * Checks and retrieves a zone ID associated with a specific country and an optional zone identifier.
   *
   * @param int $country The ID of the country for which the zone is being checked.
   * @param mixed $zone_id Optional parameter; can be null, numeric, or a string representing the zone name or code.
   *                        If null, the method looks for any zone associated with the country.
   *                        If numeric, the method looks for a specific zone ID.
   *                        If a string, the method looks for a matching zone name or code.
   *
   * @return int|null The ID of the zone if found, or null if no matching zone is found.
   */
  public function checkZoneCountry(int $country, $zone_id = null)
  {
    if (\is_null($zone_id)) {
      $Qcheck = $this->db->prepare('select zone_id
                                     from :table_zones
                                     where zone_country_id = :zone_country_id
                                     and zone_status = 0
                                     limit 1
                                     ');
      $Qcheck->bindInt(':zone_country_id', $country);
      $Qcheck->execute();
    } else {
      if (is_numeric($zone_id)) {
        $Qcheck = $this->db->prepare('select zone_id
                                       from :table_zones
                                       where zone_country_id = :zone_country_id
                                       and zone_id = :zone_id
                                       and zone_status = 0
                                       limit 1
                                       ');
        $Qcheck->bindInt(':zone_country_id', $country);
        $Qcheck->bindInt(':zone_id', $zone_id);
        $Qcheck->execute();
      } else {
        $Qcheck = $this->db->prepare('select zone_id
                                       from :table_zones
                                       where zone_country_id = :zone_country_id
                                       and (zone_name = :zone_name or zone_code = :zone_code)
                                       and zone_status = 0
                                       limit 1
                                       ');
        $Qcheck->bindInt(':zone_country_id', $country);
        $Qcheck->bindValue(':zone_name', $zone_id);
        $Qcheck->bindValue(':zone_code', $zone_id);
        $Qcheck->execute();
      }
    }

    $result = $Qcheck->valueInt('zone_id');

    return $result;
  }

  /**
   * Checks the zone ID based on the given country ID and state.
   *
   * This method determines the zone ID for a specific country and state combination.
   * In case of a match, it returns the corresponding zone ID. If no matching zone is found,
   * it returns false.
   *
   * @param int $country_id The ID of the country to check the zones for.
   * @param mixed $state The state name, code, or ID. This parameter can be empty, a non-numeric
   *                     string for name/code, or a numeric value for ID.
   *
   * @return int|false Returns the zone ID if a matching zone is found. Returns false otherwise.
   */
  public function checkZoneByCountryState(int $country_id, $state = '')
  {

    $Qzone = $this->db->prepare('select zone_id
                                   from :table_zones
                                   where zone_country_id = :zone_country_id
                                   and zone_status = 0
                                 ');

    $Qzone->bindInt(':zone_country_id', $country_id);
    $Qzone->execute();

    $all_zone = $Qzone->fetchAll();
    $count = \count($all_zone);

    if ($count > 0 && !empty($state) && !is_numeric($state)) {
      $Qzone = $this->db->prepare('select distinct zone_id
                                     from :table_zones
                                     where zone_country_id = :zone_country_id
                                     and (zone_name = :zone_name or zone_code = :zone_code)
                                     and zone_status = 0
                                   ');

      $Qzone->bindInt(':zone_country_id', $country_id);
      $Qzone->bindValue(':zone_name', $state);
      $Qzone->bindValue(':zone_code', $state);
      $Qzone->execute();
      $Qzone->fetch();

      $zone_id = $Qzone->value('zone_id');

    } elseif (ACCOUNT_STATE_DROPDOWN == 'true' && $state > 0) {
      $Qzone = $this->db->prepare('select distinct zone_id
                                     from :table_zones
                                     where zone_country_id = :zone_country_id
                                     and zone_id = :zone_id
                                     and zone_status = 0
                                   ');

      $Qzone->bindInt(':zone_country_id', $country_id);
      $Qzone->bindValue(':zone_id', $state);
      $Qzone->execute();
      $Qzone->fetch();

      $zone_id = $Qzone->value('zone_id');
    } else {
      $zone_id = false;
    }

    return $zone_id;
  }
}

