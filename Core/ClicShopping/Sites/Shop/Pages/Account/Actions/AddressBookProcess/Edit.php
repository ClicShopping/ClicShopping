<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Actions\AddressBookProcess;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\AddressBook;
use function strlen;

class Edit extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Address = Registry::get('Address');

// error checking when updating or adding an entry
    if (AddressBook::checkNewCustomer() === false) {
      $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_nonexisting_address_book_entry'), 'error');

      CLICSHOPPING::redirect(null, 'Account&AddressBook');
    }

    if (isset($_POST['action']) && $_POST['action'] == 'update' && isset($_POST['formid']) && ($_POST['formid'] === $_SESSION['sessiontoken'])) {
      $error = false;

      if (isset($_POST['gender']) && ((ACCOUNT_GENDER == 'true' && $CLICSHOPPING_Customer->getCustomersGroupID() == 0) || (ACCOUNT_GENDER_PRO == 'true' && $CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
        $gender = HTML::sanitize($_POST['gender']);
      } else {
        $gender = 'm';
      }

      if (isset($_POST['company']) && ((ACCOUNT_COMPANY == 'true' && $CLICSHOPPING_Customer->getCustomersGroupID() == 0) || (ACCOUNT_COMPANY_PRO == 'true' && $CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
        $company = HTML::sanitize($_POST['company']);
      } else {
        $company = null;
      }

      if (isset($_POST['firstname'])) $firstname = HTML::sanitize($_POST['firstname']);
      if (isset($_POST['lastname'])) $lastname = HTML::sanitize($_POST['lastname']);
      if (isset($_POST['street_address'])) $street_address = HTML::sanitize($_POST['street_address']);

      if (isset($_POST['suburb']) && ((ACCOUNT_SUBURB == 'true' && $CLICSHOPPING_Customer->getCustomersGroupID() == 0) || (ACCOUNT_SUBURB_PRO == 'true' && $CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
        $suburb = HTML::sanitize($_POST['suburb']);
      } else {
        $suburb = null;
      }

      $postcode = HTML::sanitize($_POST['postcode']);
      $city = HTML::sanitize($_POST['city']);
      $country_id = HTML::sanitize($_POST['country']);

      if (isset($_POST['customers_telephone']) && (($CLICSHOPPING_Customer->getCustomersGroupID() == 0 && ENTRY_TELEPHONE_MIN_LENGTH > 0) || ($CLICSHOPPING_Customer->getCustomersGroupID() != 0 && ENTRY_TELEPHONE_PRO_MIN_LENGTH > 0))) {
        $telephone = HTML::sanitize($_POST['customers_telephone']);
      } else {
        $telephone = null;
      }

      if (isset($_POST['customers_cellular_phone']) && (($CLICSHOPPING_Customer->getCustomersGroupID() == 0 && ACCOUNT_CELLULAR_PHONE == 'true') || ($CLICSHOPPING_Customer->getCustomersGroupID() != 0 && ACCOUNT_CELLULAR_PHONE_PRO == 'true'))) {
        $cellular_phone = HTML::sanitize($_POST['customers_cellular_phone']);
      } else {
        $cellular_phone = null;
      }

      if ((ACCOUNT_STATE == 'true' && $CLICSHOPPING_Customer->getCustomersGroupID() == 0) || (ACCOUNT_STATE_PRO == 'true' && $CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
        if (isset($_POST['zone_id'])) {
          $zone_id = HTML::sanitize($_POST['zone_id']);
        } else {
          $zone_id = false;
        }

        $state = HTML::sanitize($_POST['state']);
      }

      if (ACCOUNT_GENDER == 'true' && $CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
        if ($gender != 'm' && $gender != 'f') {
          $error = true;

          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_gender_error'), 'error');
        }
      } elseif (ACCOUNT_GENDER_PRO == 'true' && $CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
        if (($gender != 'm') && ($gender != 'f')) {
          $error = true;

          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_gender_error_pro'), 'error');
        }
      }

// Clients B2C et B2B : Controle entree du prenom
      if ((strlen($firstname) < ENTRY_FIRST_NAME_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_first_name_error', ['min_length' => ENTRY_FIRST_NAME_MIN_LENGTH]), 'error');

      } elseif ((strlen($firstname) < ENTRY_FIRST_NAME_PRO_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_first_name_error_pro', ['min_length' => ENTRY_FIRST_NAME_PRO_MIN_LENGTH]), 'error');
      }

// Clients B2C et B2B : Controle entree du nom de famille
      if ((strlen($lastname) < ENTRY_LAST_NAME_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_last_name_error', ['min_length' => ENTRY_LAST_NAME_MIN_LENGTH]), 'error');

      } elseif ((strlen($lastname) < ENTRY_LAST_NAME_PRO_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_last_name_error_pro', ['min_length' => ENTRY_LAST_NAME_PRO_MIN_LENGTH]), 'error');
      }

// Clients B2C et B2B : Controle entree adresse
      if ((strlen($street_address) < ENTRY_STREET_ADDRESS_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_street_address_error', ['min_length' => ENTRY_STREET_ADDRESS_MIN_LENGTH]), 'error');
      } elseif ((strlen($street_address) < ENTRY_STREET_ADDRESS_PRO_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_street_address_error_pro', ['min_length' => ENTRY_STREET_ADDRESS_PRO_MIN_LENGTH]), 'error');
      }

// Clients B2C et B2B : Controle entree code postal
      if ((strlen($postcode) < ENTRY_POSTCODE_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_post_code_error', ['min_length' => ENTRY_POSTCODE_MIN_LENGTH]), 'error');

      } elseif ((strlen($postcode) < ENTRY_POSTCODE_PRO_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_post_code_error_pro', ['min_length' => ENTRY_POSTCODE_PRO_MIN_LENGTH]), 'error');
      }

// Clients B2C et B2B : Controle entree de la ville
      if ((strlen($city) < ENTRY_CITY_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_city_error', ['min_length' => ENTRY_CITY_MIN_LENGTH]), 'error');
      } elseif ((strlen($city) < ENTRY_CITY_PRO_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_city_error_pro', ['min_length' => ENTRY_CITY_PRO_MIN_LENGTH]), 'error');
      }

// Clients B2C et B2B : Controle de la selection du pays
      if ((!is_numeric($country_id)) && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_country_error'), 'error');

      } elseif ((!is_numeric($country_id)) && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_country_error_pro'), 'error');
      }

      if (((ACCOUNT_STATE == 'true' && $CLICSHOPPING_Customer->getCustomersGroupID() == 0) || (ACCOUNT_STATE_PRO == 'true' && $CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
        $zone_id = 0;

        if (!empty($country_id)) {
          if ($CLICSHOPPING_Address->checkZoneCountry($country_id) !== false) {
            $_SESSION['entry_state_has_zones'] = true;
          } else {
            $_SESSION['entry_state_has_zones'] = false;
          }
        } else {
          $_SESSION['entry_state_has_zones'] = false;
        }

        if ($_SESSION['entry_state_has_zones'] === true) {
          if (ACCOUNT_STATE_DROPDOWN == 'true') {
            if (!empty($state)) {
              $zone_id = $CLICSHOPPING_Address->checkZoneByCountryState($country_id, $state);
            } else {
              $zone_id = $CLICSHOPPING_Address->checkZoneByCountryState($country_id);
            }
          } else {
            $zone_id = $CLICSHOPPING_Address->checkZoneByCountryState($country_id, $state);
          }

          if ($zone_id === false) {
            $error = true;

            if ($CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
              $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_state_error_not_existing'), 'error');

            } elseif ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
              $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_state_error_select_pro'), 'error');
            }
          }
        } else {
          if (!empty($state)) {
            $check_zone = $CLICSHOPPING_Address->checkZoneByCountryState($country_id, $state);

            if ($check_zone === false) {
              $error = true;
              $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_state_error_not_existing'), 'error');
            }
          }
        }
      } else {
        if ((strlen($state) < ENTRY_STATE_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) {
          $error = true;

          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_state_error', ['min_length' => ENTRY_STATE_MIN_LENGTH]), 'error');
        } elseif ((strlen($state) < ENTRY_STATE_PRO_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
          $error = true;

          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_state_error_select_pro', ['min_length' => entry_state_error_select_pro]), 'error');
        }
      } // end else

      if ($error === true) {
        $_SESSION['process'] = true;
      }

      if ($error === false) {
        $sql_data_array = [
          'entry_firstname' => Hash::encryptDatatext($firstname),
          'entry_lastname' => Hash::encryptDatatext($lastname),
          'entry_street_address' => Hash::encryptDatatext($street_address),
          'entry_postcode' => Hash::encryptDatatext($postcode),
          'entry_city' => Hash::encryptDatatext($city),
          'entry_country_id' => (int)$country_id,
          'entry_telephone' => Hash::encryptDatatext($telephone)
        ];

        if (((ACCOUNT_GENDER == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) || ((ACCOUNT_GENDER_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
          $sql_data_array['entry_gender'] = $gender;
        }

        if (((ACCOUNT_COMPANY == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) || ((ACCOUNT_COMPANY_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
          $sql_data_array['entry_company'] = Hash::encryptDatatext($company);
        }

        if (((ACCOUNT_SUBURB == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) || ((ACCOUNT_SUBURB_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
          $sql_data_array['entry_suburb'] = Hash::encryptDatatext($suburb);
        }

        if (((ACCOUNT_STATE == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) || ((ACCOUNT_STATE_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
          if ($zone_id > 0) {
            $sql_data_array['entry_zone_id'] = (int)$zone_id;
            $sql_data_array['entry_state'] = '';
          } else {
            $sql_data_array['entry_zone_id'] = '0';
            $sql_data_array['entry_state'] = $state;
          }
        }

        if (AddressBook::checkEntry($_GET['edit']) !== false) {
          if (isset($_GET['newcustomer']) && HTML::sanitize($_GET['newcustomer']) == 1 && AddressBook::countCustomerAddressBookEntries($CLICSHOPPING_Customer->getID()) == 1) {
            $CLICSHOPPING_Db->save('address_book', $sql_data_array, ['customers_id' => (int)$CLICSHOPPING_Customer->getID()]);
          } else {
            $CLICSHOPPING_Db->save('address_book', $sql_data_array, ['address_book_id' => $_GET['edit'], 'customers_id' => (int)$CLICSHOPPING_Customer->getID()]);
          }
// register session variables
          if ((isset($_POST['primary']) && ($_POST['primary'] == 'on')) || ($_GET['edit'] == $CLICSHOPPING_Customer->getDefaultAddressID())) {
            $CLICSHOPPING_Customer->setCountryID($country_id);
            $CLICSHOPPING_Customer->setZoneID(($zone_id > 0) ? (int)$zone_id : '0');

            if (isset($_GET['newcustomer']) && HTML::sanitize($_GET['newcustomer']) == 1) {
              $QAddressBook = $CLICSHOPPING_Db->prepare('select address_book_id
                                                            from :table_address_book
                                                            where customers_id = :customers_id
                                                          ');
              $QAddressBook->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
              $QAddressBook->execute();

              $CLICSHOPPING_Customer->setDefaultAddressID($QAddressBook->valueInt('address_book_id'));
            } else {
              $CLICSHOPPING_Customer->setDefaultAddressID(HTML::sanitize($_GET['edit']));
            }
          }

          if (HTML::sanitize($_POST['shopping']) != 1) {
            $sql_data_array = [
              'customers_firstname' => Hash::encryptDatatext($firstname),
              'customers_lastname' => Hash::encryptDatatext($lastname)
            ];

            if (((ACCOUNT_GENDER == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) || ((ACCOUNT_GENDER_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
              $sql_data_array['customers_gender'] = $gender;
            }

            $sql_data_array['customers_telephone'] = Hash::encryptDatatext($telephone);
          } else {
            $sql_data_array = [
              'customers_firstname' => Hash::encryptDatatext($firstname),
              'customers_lastname' => Hash::encryptDatatext($lastname)
            ];

            $sql_data_array['customers_telephone'] = $telephone;

            if (((ACCOUNT_GENDER == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) || ((ACCOUNT_GENDER_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
              $sql_data_array['customers_gender'] = $gender;
            }

            if (((ACCOUNT_CELLULAR_PHONE == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) || ((ACCOUNT_CELLULAR_PHONE_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
              $sql_data_array['customers_cellular_phone'] = Hash::encryptDatatext($cellular_phone);
            }
          }

          $CLICSHOPPING_Db->save('customers', $sql_data_array, ['customers_id' => (int)$CLICSHOPPING_Customer->getID()]);

          $CLICSHOPPING_Hooks->call('AddressBookProcess', 'Edit');
        }

        if (HTML::sanitize($_POST['shopping']) == 1) {
          CLICSHOPPING::redirect(null, 'Cart');
        } else {
          CLICSHOPPING::redirect(null, 'Account&AddressBook');
        }
      }
    }
  }
}