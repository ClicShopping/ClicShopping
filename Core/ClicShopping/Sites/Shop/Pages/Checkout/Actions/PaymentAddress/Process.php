<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Checkout\Actions\PaymentAddress;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\AddressBook;
use function strlen;

class Process extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Address = Registry::get('Address');

    if (isset($_POST['action']) && ($_POST['action'] == 'process') && isset($_POST['formid']) && ($_POST['formid'] === $_SESSION['sessiontoken'])) {
      $error = false;
// process a new billing address
      if (!$CLICSHOPPING_Customer->hasDefaultAddress() || (isset($_POST['firstname']) && !empty($_POST['firstname']) && isset($_POST['lastname']) && !empty($_POST['lastname']) && isset($_POST['street_address']) && !empty($_POST['street_address']))) {

        if (ACCOUNT_GENDER == 'true') {
          $gender = HTML::sanitize($_POST['gender']);
        } else {
          $gender = '';
        }

        if (ACCOUNT_COMPANY == 'true') {
          $company = HTML::sanitize($_POST['company']);
        } else {
          $company = '';
        }

        $firstname = HTML::sanitize($_POST['firstname']);
        $lastname = HTML::sanitize($_POST['lastname']);
        $street_address = HTML::sanitize($_POST['street_address']);

        if (ACCOUNT_SUBURB == 'true') {
          $suburb = HTML::sanitize($_POST['suburb']);
        } else {
          $suburb = '';
        }

        $postcode = HTML::sanitize($_POST['postcode']);
        $city = HTML::sanitize($_POST['city']);
        $country = HTML::sanitize($_POST['country']);

        if (isset($_POST['customers_telephone'])) {
          $entry_telephone = HTML::sanitize($_POST['customers_telephone']);
        } else {
          $entry_telephone = '';
        }

        // Always initialise so the zone validation works even when only ACCOUNT_STATE_PRO is enabled.
        $zone_id = false;
        $state = isset($_POST['state']) ? HTML::sanitize($_POST['state']) : '';

        if (ACCOUNT_STATE == 'true') {
          if (isset($_POST['zone_id'])) {
            $zone_id = HTML::sanitize($_POST['zone_id']);
          }
        }

        if ((ACCOUNT_GENDER == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) {
          if (($gender != 'm') && ($gender != 'f')) {
            $error = true;

            $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_gender_error'), 'error');
          }
        } elseif ((ACCOUNT_GENDER_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
          if (($gender != 'm') && ($gender != 'f')) {
            $error = true;
            $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_gender_error_pro'), 'error');
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

        if (((ACCOUNT_STATE == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) || ((ACCOUNT_STATE_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
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
            } else {
              $zone_id = $CLICSHOPPING_Address->checkZoneByCountryState($country, $state);
            }

            if ($zone_id === false) {
              $error = true;

              if ($CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
                $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_state_error_select'), 'error');

              } elseif ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
                $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_state_error_select_pro'), 'error');
              }
            }
          } else {
            if ($zone_id === false) {
              $check_zone = $CLICSHOPPING_Address->checkZoneByCountryState($country, $state);

              if ($check_zone === false) {
                $error = true;
                $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_state_error_not_existing'), 'error');
              }
            }
          }

          if (ACCOUNT_STATE_DROPDOWN == 'false') {
            if ((strlen($state) < ENTRY_STATE_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) {
              $error = true;

              $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_state_error', ['min_length' => ENTRY_STATE_MIN_LENGTH]), 'error');
            } elseif ((strlen($state) < ENTRY_STATE_PRO_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
              $error = true;

              $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_state_error_pro', ['min_length' => ENTRY_STATE_PRO_MIN_LENGTH]), 'error');
            }
          }
        }

// Clients B2C et B2B : Controle de la selection du pays
        if (is_numeric($country) === false && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0 || $country < 1)) {
          $error = true;

          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_country_error'), 'error', 'header');
        } elseif (is_numeric($country) === false && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0 || $country < 1)) {
          $error = true;

          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_country_error_pro'), 'error');
        }

        if ($error === false) {
          $sql_data_array = [
            'customers_id' => (int)$CLICSHOPPING_Customer->getID(),
            'entry_firstname' => Hash::encryptDatatext($firstname),
            'entry_lastname' => Hash::encryptDatatext($lastname),
            'entry_street_address' => Hash::encryptDatatext($street_address),
            'entry_postcode' => Hash::encryptDatatext($postcode),
            'entry_city' => Hash::encryptDatatext($city),
            'entry_country_id' => (int)$country,
            'entry_telephone' => Hash::encryptDatatext($entry_telephone)
          ];

          if (ACCOUNT_GENDER == 'true') $sql_data_array['entry_gender'] = $gender;
          if (ACCOUNT_COMPANY == 'true') $sql_data_array['entry_company'] = Hash::encryptDatatext($company);
          if (ACCOUNT_SUBURB == 'true') $sql_data_array['entry_suburb'] = Hash::encryptDatatext($suburb);

          if (ACCOUNT_STATE == 'true') {
            if ($zone_id > 0) {
              $sql_data_array['entry_zone_id'] = (int)$zone_id;
              $sql_data_array['entry_state'] = '';
            } else {
              $sql_data_array['entry_zone_id'] = 0;
              $sql_data_array['entry_state'] = $state;
            }
          }

          $CLICSHOPPING_Db->save('address_book', $sql_data_array);

          $_SESSION['billto'] = $CLICSHOPPING_Db->lastInsertId();

          if (!$CLICSHOPPING_Customer->hasDefaultAddress()) {
            $CLICSHOPPING_Customer->setCountryID($country);
            $CLICSHOPPING_Customer->setZoneID(($zone_id > 0) ? (int)$zone_id : '0');
            $CLICSHOPPING_Customer->setDefaultAddressID($_SESSION['billto']);
          }

          $CLICSHOPPING_Hooks->call('PaymentAddress', 'Process');

          if (isset($_SESSION['payment'])) {
            unset($_SESSION['payment']);
          }

          CLICSHOPPING::redirect(null, 'Checkout&Billing');
        }
// process the selected shipping destination
      } elseif (isset($_POST['address'])) {
        $reset_payment = false;

        if (isset($_SESSION['billto'])) {
          if ($_SESSION['billto'] != $_POST['address']) {
            if (isset($_SESSION['payment'])) {
              $reset_payment = true;
            }
          }
        }

        $_SESSION['billto'] = HTML::sanitize($_POST['address']);

        $check = AddressBook::getAddressCustomer(null, (int)$_SESSION['billto']);

        if ($check !== false) {
          $CLICSHOPPING_Hooks->call('PaymentAddress', 'Process');

          if ($reset_payment === true) {
            unset($_SESSION['payment']);
          }

          CLICSHOPPING::redirect(null, 'Checkout&Billing');
        } else {
          unset($_SESSION['billto']);
        }
      } else {
// no addresses to select from - customer decided to keep the current assigned address
        $_SESSION['billto'] = $CLICSHOPPING_Customer->getDefaultAddressID();

        CLICSHOPPING::redirect(null, 'Checkout&Billing');
      }
    }
  }
}


