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
use function strlen;

class Create extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Address = Registry::get('Address');

    $_SESSION['process'] = false;

    if (isset($_POST['action']) && $_POST['action'] == 'process' && isset($_POST['formid']) && ($_POST['formid'] === $_SESSION['sessiontoken'])) {
      $_SESSION['process'] = true;
      $error = false;

      if (isset($_POST['gender']) && (((ACCOUNT_GENDER == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) || ((ACCOUNT_GENDER_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)))) {
        $gender = HTML::sanitize($_POST['gender']);
        if (empty($gender)) $gender = 'm';
      } else {
        $gender = 'm';
      }

      if (((ACCOUNT_COMPANY == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) || ((ACCOUNT_COMPANY_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
        $company = HTML::sanitize($_POST['company']);
      } else {
        $company = '';
      }

      $firstname = HTML::sanitize($_POST['firstname']);
      $lastname = HTML::sanitize($_POST['lastname']);
      $street_address = HTML::sanitize($_POST['street_address']);
      $postcode = HTML::sanitize($_POST['postcode']);
      $city = HTML::sanitize($_POST['city']);
      $country = HTML::sanitize($_POST['country']);

      if (((ACCOUNT_SUBURB == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) || ((ACCOUNT_SUBURB_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
        if (isset($_POST['suburb'])) {
          $suburb = HTML::sanitize($_POST['suburb']);
        } else {
          $suburb = '';
        }
      }

      if ((($CLICSHOPPING_Customer->getCustomersGroupID() == 0) && (ENTRY_TELEPHONE_MIN_LENGTH > 0)) || (($CLICSHOPPING_Customer->getCustomersGroupID() != 0) && (ENTRY_TELEPHONE_PRO_MIN_LENGTH > 0))) {
        if (isset($_POST['customers_telephone'])) {
          $telephone = HTML::sanitize($_POST['customers_telephone']);
        } else {
          $telephone = '';
        }
      }

      if ((($CLICSHOPPING_Customer->getCustomersGroupID() == 0) && (ACCOUNT_CELLULAR_PHONE == 'true')) || (($CLICSHOPPING_Customer->getCustomersGroupID() != 0) && (ACCOUNT_CELLULAR_PHONE_PRO == 'true'))) {
        if (isset($_POST['customers_cellular_phone'])) {
          $cellular_phone = HTML::sanitize($_POST['customers_cellular_phone']);
        } else {
          $cellular_phone = '';
        }
      }

      if ((ACCOUNT_STATE == 'true' && $CLICSHOPPING_Customer->getCustomersGroupID() == 0) || (ACCOUNT_STATE_PRO == 'true' && $CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
        if (isset($_POST['zone_id'])) {
          $zone_id = HTML::sanitize($_POST['zone_id']);
        } else {
          $zone_id = false;
        }

        $state = HTML::sanitize($_POST['state']);
      }

      if ((ACCOUNT_GENDER == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) {
        if (($gender != 'm') && ($gender != 'f')) {
          $error = true;

          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_gender_error'), 'error');
        }
      } elseif ((ACCOUNT_GENDER_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
        if (($gender != 'm') && ($gender != 'f')) {
          $error = true;

          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_gender_error_pro'), 'error', 'checkout_address');
        }
      }

      if ((strlen($firstname) < ENTRY_FIRST_NAME_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_first_name_error', ['min_length' => ENTRY_FIRST_NAME_MIN_LENGTH]), 'error');

      } elseif ((strlen($firstname) < ENTRY_FIRST_NAME_PRO_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_first_name_error_pro', ['min_length' => ENTRY_FIRST_NAME_PRO_MIN_LENGTH]), 'error');
      }

      if ((strlen($lastname) < ENTRY_LAST_NAME_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_last_name_error', ['min_length' => ENTRY_LAST_NAME_MIN_LENGTH]), 'error');

      } elseif ((strlen($lastname) < ENTRY_LAST_NAME_PRO_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_last_name_error_pro', ['min_length' => ENTRY_LAST_NAME_PRO_MIN_LENGTH]), 'error');
      }

      if ((strlen($street_address) < ENTRY_STREET_ADDRESS_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_street_address_error', ['min_length' => ENTRY_STREET_ADDRESS_MIN_LENGTH]), 'error');
      } elseif ((strlen($street_address) < ENTRY_STREET_ADDRESS_PRO_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_street_address_error_pro', ['min_length' => ENTRY_STREET_ADDRESS_PRO_MIN_LENGTH]), 'error');
      }

      if ((strlen($postcode) < ENTRY_POSTCODE_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_post_code_error', ['min_length' => ENTRY_POSTCODE_MIN_LENGTH]), 'error');

      } elseif ((strlen($postcode) < ENTRY_POSTCODE_PRO_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_post_code_error_pro', ['min_length' => ENTRY_POSTCODE_PRO_MIN_LENGTH]), 'error');
      }

      if ((strlen($city) < ENTRY_CITY_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_city_error', ['min_length' => ENTRY_CITY_MIN_LENGTH]), 'error');
      } elseif ((strlen($city) < ENTRY_CITY_PRO_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_city_error_pro', ['min_length' => ENTRY_CITY_PRO_MIN_LENGTH]), 'error');
      }

      if ((!is_numeric($country)) && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_country_error'), 'error');

      } elseif ((!is_numeric($country)) && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_country_error_pro'), 'error');
      }

      if (((ACCOUNT_STATE == 'true' && $CLICSHOPPING_Customer->getCustomersGroupID() == 0) || (ACCOUNT_STATE_PRO == 'true' && $CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
        $zone_id = 0;

        if (!empty($country)) {
          if ($CLICSHOPPING_Address->checkZoneCountry($country) !== false) {
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
              $zone_id = $CLICSHOPPING_Address->checkZoneByCountryState($country, $state);
            } else {
              $zone_id = $CLICSHOPPING_Address->checkZoneByCountryState($country);
            }

            $zone_name = $CLICSHOPPING_Address->getZoneName($country, $state);

            if (!empty($zone_name)) $state = $zone_name;
          } else {
            $zone_id = $CLICSHOPPING_Address->checkZoneCountry($country, $state);
            $zone_name = $CLICSHOPPING_Address->getZoneName($country, 0, $state);

            if (!empty($zone_name)) $state = $zone_name;
          }

          if ($zone_id === false) {
            $error = true;

            if ($CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
              $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_state_error_select'), 'error');

            } elseif ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
              $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_state_error_select_pro'), 'error');
            }
          } // end else
        }
      } else {
        if ((strlen($state) < ENTRY_STATE_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) {
          $error = true;
        } elseif ((strlen($state) < ENTRY_STATE_PRO_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
          $error = true;

          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_state_error_select_pro', ['min_length' => entry_state_error_select_pro]), 'error');
        }
      } // end else

      if ($error === false) {
        $sql_data_array = [
          'entry_firstname' => Hash::encryptDatatext($firstname),
          'entry_lastname' => Hash::encryptDatatext($lastname),
          'entry_street_address' => Hash::encryptDatatext($street_address),
          'entry_postcode' => Hash::encryptDatatext($postcode),
          'entry_city' => Hash::encryptDatatext($city),
          'entry_country_id' => (int)$country,
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

          if ($zone_id > 0 && is_numeric($state)) {
            $sql_data_array['entry_zone_id'] = $zone_id;
            $sql_data_array['entry_state'] = '';
          } else {
            $sql_data_array['entry_zone_id'] = '0';
            $sql_data_array['entry_state'] = $state;
          }
        }

// create address
        $sql_data_array['customers_id'] = (int)$CLICSHOPPING_Customer->getID();

        $CLICSHOPPING_Db->save('address_book', $sql_data_array);

        $new_address_book_id = $CLICSHOPPING_Db->lastInsertId();

// register session variables
        if ((isset($_POST['primary']) && ($_POST['primary'] == 'on')) || (isset($_GET['Edit']) && $_GET['Edit'] == $CLICSHOPPING_Customer->getDefaultAddressID())) {
          $CLICSHOPPING_Customer->setCountryID($country);
          $CLICSHOPPING_Customer->setZoneID(($zone_id > 0) ? (int)$zone_id : '0');

          if (isset($_GET['id'])) {
            $CLICSHOPPING_Customer->setDefaultAddressID((int)$_GET['id']);
          } else {
            $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error'), 'error');
            CLICSHOPPING::redirect(null, 'Account&Main');
          }

          $sql_data_array = [
            'customers_firstname' => Hash::encryptDatatext($firstname),
            'customers_lastname' => Hash::encryptDatatext($lastname),
            'customers_cellular_phone' => Hash::encryptDatatext($cellular_phone),
            'customers_telephone' => Hash::encryptDatatext($telephone)
          ];

          if (((ACCOUNT_GENDER == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) || ((ACCOUNT_GENDER_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
            $sql_data_array['customers_gender'] = $gender;
          }

          if (isset($_POST['primary']) && (HTML::sanitize($_POST['primary']) == 'on')) $sql_data_array['customers_default_address_id'] = $new_address_book_id;

          $CLICSHOPPING_Db->save('customers', $sql_data_array, ['customers_id' => (int)$CLICSHOPPING_Customer->getID()]);

          $CLICSHOPPING_Hooks->call('AddressBookProcess', 'Create');

          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('success_address_book_entry_updated'), 'success');
        } else {
          $CLICSHOPPING_Hooks->call('AddressBookProcess', 'Create');
        }// end isset($_POST['primary']
      }// end $error

      if (isset($_POST['shopping']) && HTML::sanitize($_POST['shopping']) == 1) {
        CLICSHOPPING::redirect(null, 'Cart');
      } else {
        CLICSHOPPING::redirect(null, 'Account&AddressBook');
      }
    } // end $error
  } // end isset($_POST['action']
}