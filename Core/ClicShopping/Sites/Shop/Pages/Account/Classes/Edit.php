<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Classes;

use ClicShopping\OM\Registry;

class Edit
{
  public function __construct()
  {
  }

  /**
   * Retrieves account details for the currently logged-in customer.
   *
   * @return array An associative array containing customer details such as gender,
   *               firstname, lastname, date of birth, email address, telephone,
   *               cellular phone, company, SIRET, APE, VAT intracommunity number,
   *               and VAT ISO code.
   */
  public static function getAccountEdit(): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    $Qaccount = $CLICSHOPPING_Db->prepare('select customers_gender,
                                                    customers_firstname,
                                                    customers_lastname,
                                                    customers_dob,
                                                    customers_email_address,
                                                    customers_telephone,
                                                    customers_cellular_phone,
                                                    customers_company,
                                                    customers_siret,
                                                    customers_ape,
                                                    customers_tva_intracom,
                                                    customers_tva_intracom_code_iso
                                             from :table_customers
                                             where customers_id = :customers_id
                                            ');
    $Qaccount->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
    $Qaccount->execute();

    $account = $Qaccount->fetch();

    return $account;
  }

  /**
   * Retrieves the count of email addresses in the customers table that match a specific email address
   * but exclude the current customer's ID.
   *
   * @param string $email_address The email address to compare against records in the customers table.
   * @return int The total count of matching email addresses, excluding the current customer.
   */
  public static function getCountEmail(string $email_address): int
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    $QcheckEmail = $CLICSHOPPING_Db->prepare('select count(*) as total
                                         from :table_customers
                                         where customers_email_address = :customers_email_address
                                         and customers_id != :customers_id
                                        ');
    $QcheckEmail->bindValue(':customers_email_address', $email_address);
    $QcheckEmail->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
    $QcheckEmail->execute();

    $check_email = $QcheckEmail->valueInt('total');

    return $check_email;
  }

  /**
   * Checks if there is a customer address email in the database that matches the provided email
   * address and belongs to a different customer.
   *
   * @param string $email_address The email address to check against customer records.
   * @return bool Returns true if a matching customer email address is found, otherwise false.
   */
  public static function getCustomerAddressEmail(string $email_address): bool
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    $Qcheck = $CLICSHOPPING_Db->prepare('select customers_id
                                            from :table_customers
                                            where customers_email_address = :customers_email_address
                                            and customers_id != :customers_id
                                            limit 1
                                            ');
    $Qcheck->bindValue(':customers_email_address', $email_address);
    $Qcheck->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
    $Qcheck->execute();

    $check = $Qcheck->fetch();

    return $check;
  }

  /**
   * Verifies the existence of a country using its ISO Code 2 and retrieves its ID.
   *
   * @param int $country_id The ISO Code 2 of the country to check.
   *
   * @return int The ID of the country if it exists, otherwise 0 or a similar non-existent indicator.
   */
  public static function getCheckCountryIsoCode2(int $country_id): int
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->prepare('select countries_id
                                          from :table_countries
                                          where countries_iso_code_2 = :countries_iso_code_2
                                          limit 1
                                          ');
    $Qcheck->bindValue(':countries_iso_code_2', $country_id);
    $Qcheck->execute();

    $country_id = $Qcheck->valueInt('countries_id');

    return $country_id;
  }
}