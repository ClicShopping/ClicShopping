<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use function is_array;
use function is_null;
/**
 * Class AddressBook
 * Provides methods for handling customer address book operations.
 */
class AddressBook
{

  /*
  * Return a pecific customer address
  * @param : int|null $id customer_id
  * @param : int|null$address_book_id , id of address book
  * @return array $Qaddresses
  * public
  */
  /**
   * Retrieves the address details of a customer based on the given customer ID and address book ID.
   *
   * @param int|null $customers_id The ID of the customer. If null, the currently logged-in customer's ID is used.
   * @param int|null $address_book_id The ID of the address book entry. If not provided, no specific entry is targeted.
   * @return array|false The address details as an associative array if found, or false if no address is found.
   */
  public static function getAddressCustomer(int|null $customers_id = null, int|null $address_book_id = null)
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (is_null($customers_id)) {
      $customers_id = $CLICSHOPPING_Customer->getID();
    }

    $Qaddress = $CLICSHOPPING_Db->prepare('select address_book_id,
                                                    entry_firstname as firstname,
                                                    entry_lastname as lastname,
                                                    entry_telephone as telephone,
                                                    entry_company as company,
                                                    entry_street_address as street_address,
                                                    entry_suburb as suburb,
                                                    entry_city as city,
                                                    entry_postcode as postcode,
                                                    entry_state as state,
                                                    entry_zone_id as zone_id,
                                                    entry_country_id as country_id
                                         from :table_address_book
                                         where customers_id = :customers_id
                                         and address_book_id = :address_book_id
                                         order by firstname, lastname
                                        ');
    $Qaddress->bindInt(':address_book_id', $address_book_id);
    $Qaddress->bindInt(':customers_id', $customers_id);

    $Qaddress->execute();

    $address = $Qaddress->fetch();

    return $address;
  }

  /**
   * Generates an address label based on the given customer ID and address ID.
   *
   * @param int $customers_id The ID of the customer for which the address label is generated.
   * @param int|null $address_id The ID of the address to format. Defaults to 1 if not specified.
   * @param bool $html Indicates whether the address label should be formatted in HTML. Defaults to false.
   * @param string $boln The string to prepend to the beginning of each line (used for formatting). Defaults to an empty string.
   * @param string $eoln The string to append at the end of each line (used for formatting). Defaults to a newline character ("\n").
   *
   * @return string The formatted address label.
   */
  public static function addressLabel(int $customers_id,  int|null $address_id = 1, bool $html = false, string $boln = '', string $eoln = "\n")
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Address = Registry::get('Address');

    if (is_array($address_id) && !empty($address_id)) {
      return $CLICSHOPPING_Address->addressFormat($address_id['address_format_id'], $address_id, $html, $boln, $eoln);
    }

    $Qaddress = $CLICSHOPPING_Db->prepare('select entry_firstname as firstname,
                                                    entry_lastname as lastname,
                                                    entry_company as company,
                                                    entry_street_address as street_address,
                                                    entry_suburb as suburb, entry_city as city,
                                                    entry_postcode as postcode,
                                                    entry_state as state,
                                                    entry_zone_id as zone_id,
                                                    entry_country_id as country_id
                                             from :table_address_book
                                             where customers_id = :customers_id
                                             and address_book_id = :address_book_id
                                           ');

    $Qaddress->bindInt(':address_book_id', $address_id);
    $Qaddress->bindInt(':customers_id', $customers_id);

    $Qaddress->execute();

    $format_id = $CLICSHOPPING_Address->getAddressFormatId($Qaddress->valueInt('country_id'));

    return $CLICSHOPPING_Address->addressFormat($format_id, $Qaddress->toArray(), $html, $boln, $eoln);
  }

  /**
   * Counts the default address modification status for a customer.
   *
   * @param string $id The customer ID. If not provided, it attempts to determine the ID from the logged-in session.
   * @param bool $check_session Whether to validate the session for the logged-in customer. Defaults to true.
   * @return string The customer's default address modification status, or 0 if conditions are not met.
   */
  public static function countCustomersModifyAddressDefault(int|string $id = '', bool $check_session = true): string
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');

    if (is_numeric($id) === false) {
      if ($CLICSHOPPING_Customer->isLoggedOn()) {
        $id = $CLICSHOPPING_Customer->getID();
      } else {
        return '0';
      }
    }

    if ($check_session === true) {
      if (!$CLICSHOPPING_Customer->isLoggedOn() || ($id != $CLICSHOPPING_Customer->getID())) {
        return '0';
      }
    }

    if (ACCOUNT_MODIFY_ADRESS_DEFAULT_PRO == 'true' || $CLICSHOPPING_Customer->getCustomersGroupID() == '0') {
      $QcustomersModifyAddressDefault = $CLICSHOPPING_Db->prepare('select customers_modify_address_default
                                                                   from :table_customers
                                                                   where customers_id = :customers_id
                                                                  ');
      $QcustomersModifyAddressDefault->bindInt(':customers_id', (int)$CLICSHOPPING_Customer->getID());

      $QcustomersModifyAddressDefault->execute();

      return $QcustomersModifyAddressDefault->value('customers_modify_address_default');
    }

    return '0';
  }

  /**
   * Counts and retrieves the customer's additional address information.
   *
   * @param int|null $id The customer's ID, or null to use the ID of the currently logged-in customer.
   * @param bool $check_session Whether to ensure the customer's session is valid. Defaults to true.
   * @return string|null The customer's additional address information, or null if not available.
   */
  public static function countCustomersAddAddress(int|string|null $id = null, bool $check_session = true): ?string
  {

    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');

    if (is_numeric($id) === false) {
      if ($CLICSHOPPING_Customer->isLoggedOn()) {
        $id = $CLICSHOPPING_Customer->getID();
      } else {
        return 0;
      }
    }

    if ($check_session === true) {
      if (!$CLICSHOPPING_Customer->isLoggedOn() || ($id != $CLICSHOPPING_Customer->getID())) {
        return 0;
      }
    }

    if ($CLICSHOPPING_Customer->getCustomersGroupID() == 0 || ACCOUNT_ADRESS_BOOK_PRO == 'true') {
      $Qaddresses = $CLICSHOPPING_Db->prepare('select customers_add_address
                                                 from :table_customers
                                                 where customers_id = :customers_id
                                                ');
      $Qaddresses->bindInt(':customers_id', (int)$CLICSHOPPING_Customer->getID());

      $Qaddresses->execute();
      $customers_add_address = $Qaddresses->fetch();
    }

    return $customers_add_address['customers_add_address'];
  }

  /**
   * Retrieves the modification status of the company associated with the specified customer ID.
   *
   * @param string $id The customer ID. If not provided and the customer is logged on, their ID will be used. Otherwise, returns 0.
   * @param bool $check_session If true, ensures the logged-in session corresponds to the provided ID. Defaults to true.
   * @return string The value of the "customers_modify_company" field for the customer, or 0 if conditions are not met.
   */
  public static function countCustomersModifyCompany($id = '', bool $check_session = true): string
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');

    if (is_numeric($id) === false) {
      if ($CLICSHOPPING_Customer->isLoggedOn()) {
        $id = $CLICSHOPPING_Customer->getID();
      } else {
        return 0;
      }
    }

    if ($check_session === true) {
      if (!$CLICSHOPPING_Customer->isLoggedOn() || ($id != $CLICSHOPPING_Customer->getID())) {
        return 0;
      }
    }

    $QcustomersModifyCompany = $CLICSHOPPING_Db->prepare('select customers_modify_company
                                                            from :table_customers
                                                            where customers_id = :customers_id
                                                          ');
    $QcustomersModifyCompany->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());

    $QcustomersModifyCompany->execute();
    $customers_modify_company = $QcustomersModifyCompany->fetch();

    return $customers_modify_company['customers_modify_company'];
  }

  /**
   * Checks if an address book entry exists for the given customer.
   *
   * @param int $id The address book ID to be checked.
   * @return bool Returns true if the address book entry exists, otherwise false.
   */
  public static function checkEntry(int $id)
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    $count = static::countCustomerAddressBookEntries($CLICSHOPPING_Customer->getID());

    if (isset($_GET['newcustomer']) && $count == 1) {
      $Qentry = $CLICSHOPPING_Db->prepare('select address_book_id
                                            from :table_address_book
                                            where customers_id = :customers_id
                                          ');
      $Qentry->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
      $Qentry->execute();
    } else {
      $Qentry = $CLICSHOPPING_Db->prepare('select address_book_id
                                            from :table_address_book
                                            where address_book_id = :address_book_id
                                            and customers_id = :customers_id
                                          ');
      $Qentry->bindInt(':address_book_id', $id);
      $Qentry->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
      $Qentry->execute();
    }

    return ($Qentry->fetch() !== false);
  }

  /**
   * Deletes an address book entry for the currently logged-in customer.
   *
   * @param int $id The ID of the address book entry to be deleted.
   * @return bool Returns true if the entry was successfully deleted, false otherwise.
   */
  public static function deleteEntry(int $id)
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    $Qdelete = $CLICSHOPPING_Db->prepare('delete from :table_address_book
                                            where address_book_id = :address_book_id
                                            and customers_id = :customers_id
                                           ');
    $Qdelete->bindInt(':address_book_id', $id);
    $Qdelete->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
    $Qdelete->execute();

    return ($Qdelete->rowCount() === 1);
  }

  /**
   * Counts the total number of address book entries for a specific customer.
   *
   * @param int|string $id The customer ID. If not provided, the method will attempt to determine
   *                       the ID from the logged-in customer's session.
   * @param bool $check_session Whether to verify that the provided customer ID matches the current
   *                            logged-in customer's ID.
   *
   * @return int The total number of address book entries for the customer. Returns 0 if the customer
   *             is not logged in or if the ID validation fails.
   */
  public static function countCustomerAddressBookEntries($id = '', bool $check_session = true)
  {

    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');

    if (is_numeric($id) === false) {
      if ($CLICSHOPPING_Customer->isLoggedOn()) {
        $id = $CLICSHOPPING_Customer->getID();
      } else {
        return 0;
      }
    }

    if ($check_session === true) {
      if (!$CLICSHOPPING_Customer->isLoggedOn() || ($id != $CLICSHOPPING_Customer->getID())) {
        return 0;
      }
    }

    $Qaddresses = $CLICSHOPPING_Db->prepare('select count(*) as total
                                               from :table_address_book
                                               where customers_id = :customers_id
                                               limit 1
                                              ');
    $Qaddresses->bindInt(':customers_id', (int)$id);

    $Qaddresses->execute();

    if ($Qaddresses->fetch() !== false) {
      return $Qaddresses->valueInt('total');
    }

    return 0;
  }

  /**
   * Counts the number of orders made by a specific customer.
   *
   * @param int|string $id The ID of the customer. If not provided, the ID of the currently logged-in customer is used.
   * @param bool $check_session Whether to verify that the session belongs to the customer. Defaults to true.
   * @return int The total number of orders for the customer, or 0 if the customer is not logged in or not valid.
   */
  public static function countCustomerOrders($id = '', $check_session = true)
  {

    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    if (is_numeric($id) === false) {
      if ($CLICSHOPPING_Customer->isLoggedOn()) {
        $id = $CLICSHOPPING_Customer->getID();
      } else {
        return 0;
      }
    }

    if ($check_session === true) {
      if (!$CLICSHOPPING_Customer->isLoggedOn() || ($id != $CLICSHOPPING_Customer->getID())) {
        return 0;
      }
    }

    $Qorders = $CLICSHOPPING_Db->prepare('select count(*) as total
                                            from :table_orders o,
                                                 :table_orders_status s
                                            where o.customers_id = :customers_id
                                            and o.orders_status = s.orders_status_id
                                            and s.language_id = :language_id
                                            and s.public_flag = 1
                                          ');
    $Qorders->bindInt(':customers_id', (int)$id);
    $Qorders->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());

    $Qorders->execute();

    if ($Qorders->fetch() !== false) {
      return $Qorders->valueInt('total');
    }

    return 0;
  }

  /**
   * Retrieves a list of addresses associated with the currently logged-in customer.
   *
   * This method queries the database to fetch address book entries for the customer.
   * Each address includes details such as the first name, last name, company,
   * street address, suburb, city, postcode, state, zone, and country information.
   *
   * The results are ordered by the customer's first and last name.
   *
   * @return object Returns the prepared statement object containing the address list.
   */
  public static function getListing()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qaddresses = $CLICSHOPPING_Db->prepare('select ab.address_book_id,
                                                       ab.entry_firstname as firstname,
                                                       ab.entry_lastname as lastname,
                                                       ab.entry_company as company,
                                                       ab.entry_street_address as street_address,
                                                       ab.entry_suburb as suburb,
                                                       ab.entry_city as city,
                                                       ab.entry_postcode as postcode,
                                                       ab.entry_state as state,
                                                       ab.entry_zone_id as zone_id,
                                                       ab.entry_country_id as country_id,
                                                       z.zone_code as zone_code,
                                                       z.zone_name as zone_name,
                                                       c.countries_name as country_title
                                              from :table_address_book ab left join :table_zones z on (ab.entry_zone_id = z.zone_id),
                                                    :table_countries c
                                              where ab.customers_id = :customers_id
                                              and ab.entry_country_id = c.countries_id
                                              order by ab.entry_firstname,
                                                       ab.entry_lastname
                                             ');
    $Qaddresses->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
    $Qaddresses->execute();

    return $Qaddresses;
  }

  /**
   * Retrieves an entry from the address book based on the address book ID or the current customer's ID.
   *
   * If the `newcustomer` parameter is set to 1 in the GET request, the method returns details for the
   * current customer. Otherwise, it retrieves the entry for the specified address book ID.
   *
   * @param int $id The address book ID for the entry to retrieve. Ignored when `newcustomer` is set.
   * @return array An associative array containing the entry details, such as gender, company, name, address, and contact information.
   */
  public static function getEntry(int $id)
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');

    if (isset($_GET['newcustomer']) && $_GET['newcustomer'] == 1) {
      $Qentry = $CLICSHOPPING_Db->prepare('select entry_gender as gender,
                                                     entry_company as company,
                                                     entry_firstname as firstname,
                                                     entry_lastname as lastname,
                                                     entry_street_address as street_address,
                                                     entry_suburb as suburb,
                                                     entry_postcode as postcode,
                                                     entry_city as city,
                                                     entry_state as state,
                                                     entry_zone_id as zone_id,
                                                     entry_country_id as country_id,
                                                     entry_telephone as telephone
                                             from :table_address_book
                                             where customers_id = :customers_id
                                             ');
      $Qentry->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
      $Qentry->execute();

      return $Qentry->toArray();
    } else {
      $Qentry = $CLICSHOPPING_Db->prepare('select entry_gender as gender,
                                                     entry_company as company,
                                                     entry_firstname as firstname,
                                                     entry_lastname as lastname,
                                                     entry_street_address as street_address,
                                                     entry_suburb as suburb,
                                                     entry_postcode as postcode,
                                                     entry_city as city,
                                                     entry_state as state,
                                                     entry_zone_id as zone_id,
                                                     entry_country_id as country_id,
                                                     entry_telephone as telephone
                                             from :table_address_book
                                             where address_book_id = :address_book_id
                                             and customers_id = :customers_id
                                             ');
      $Qentry->bindInt(':address_book_id', $id);
      $Qentry->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
      $Qentry->execute();

      return $Qentry->toArray();
    }
  }

  /**
   * Calculates and returns the total number of address book entries for the logged-in customer.
   *
   * @param int $total_entries The current total number of entries, may be initialized as 0.
   * @return int The total number of address book entries for the logged-in customer.
   */
  public static function numberOfEntries($total_entries): int
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');

    if (!isset($total_entries)) {
      $total_entries = 0;

      if ($CLICSHOPPING_Customer->isLoggedOn()) {
        $Qaddresses = $CLICSHOPPING_Db->prepare('select count(*) as total
                                                  from :table_address_book
                                                  where customers_id = :customers_id');
        $Qaddresses->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
        $Qaddresses->execute();

        $total_entries = $Qaddresses->valueInt('total');
      }
    }

    return $total_entries;
  }

  /**
   * Sets the primary address for the currently logged-in customer.
   *
   * @param int $id The ID of the address to be set as the primary address.
   * @return bool Returns true if the primary address was successfully updated, false otherwise.
   */
  public static function setPrimaryAddress(int $id)
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (is_numeric($id) && ($id > 0)) {
      $Qupdate = $CLICSHOPPING_Db->prepare('update :table_customers
                                              set customers_default_address_id = :customers_default_address_id
                                              where customers_id = :customers_id
                                             ');
      $Qupdate->bindInt(':customers_default_address_id', $id);
      $Qupdate->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
      $Qupdate->execute();

      return ($Qupdate->rowCount() === 1);
    }

    return false;
  }

  /**
   * Determines whether the operation involves a new customer or an existing customer by evaluating specific conditions
   * such as the presence of query parameters and their associated values, as well as validating address book entries.
   *
   * @return bool Returns true if a valid address book entry exists, otherwise returns false.
   */
  public static function checkNewCustomer(): bool
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (isset($_GET['newcustomer'])) {
      $new_customer = HTML::sanitize($_GET['newcustomer']);
    } else {
      $new_customer = null;
    }

    if ($new_customer == 1) {
      if (!empty($CLICSHOPPING_Customer->getDefaultAddressID())) {
        $entry = AddressBook::getEntry($CLICSHOPPING_Customer->getDefaultAddressID());
      }
    } else {
      if (isset($_GET['edit'])) {
        $entry = AddressBook::getEntry((int)$_GET['edit']);
      } else {
        $entry = false;
      }
    }

    $exists = false;

    if ($entry !== false) {
      $exists = true;
    }

    return $exists;
  }
}