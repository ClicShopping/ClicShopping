<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Customers\Sites\ClicShoppingAdmin\Pages\Home\Actions\Customers;

use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Is;
use ClicShopping\OM\Registry;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Customers = Registry::get('Customers');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    $customers_tva_intracom = '';
    $error = false;

    if (isset($_POST['customers_id'])) {
      $customers_id = HTML::sanitize($_POST['customers_id']);
    }

    if (isset($_POST['customers_firstname'])) {
      $customers_firstname = HTML::sanitize($_POST['customers_firstname']);
    }

    if (isset($_POST['customers_lastname'])) {
      $customers_lastname = HTML::sanitize($_POST['customers_lastname']);
    }

    if (isset($_POST['customers_email_address'])) {
      $customers_email_address = HTML::sanitize($_POST['customers_email_address']);
    }

    if (isset($_POST['customers_telephone'])) {
      $customers_telephone = HTML::sanitize($_POST['customers_telephone']);
    } else {
      $customers_telephone = '';
    }

    if (isset($_POST['customers_newsletter'])) {
      $customers_newsletter = HTML::sanitize($_POST['customers_newsletter']);
    } else {
      $customers_newsletter = 0;
    }

    if (isset($_POST['languages_id'])) {
      $language_id = HTML::sanitize($_POST['languages_id']);
    }

    if (isset($_POST['customers_gender'])) {
      $customers_gender = HTML::sanitize($_POST['customers_gender']);
    }

    if (isset($_POST['customers_dob'])) {
      $customers_dob = HTML::sanitize($_POST['customers_dob']);
    } else {
      $customers_dob = null;
    }

    if (isset($_POST['customers_cellular_phone'])) {
      $customers_cellular_phone = HTML::sanitize($_POST['customers_cellular_phone']);
    } else {
      $customers_cellular_phone = '';
    }

    if (isset($_POST['customers_notes'])) {
      $customers_notes = HTML::sanitize($_POST['customers_notes']);
    } else {
      $customers_notes = '';
    }

// Autorisation aux clients de modifier Les informations de la société
    if (isset($_POST['customers_modify_company'])) {
      $customers_modify_company = HTML::sanitize($_POST['customers_modify_company']);
    } else {
      $customers_modify_company = 0;
    }

// Informations sur le type de facturation

    if (isset($_POST['default_address_id'])) $default_address_id = HTML::sanitize($_POST['default_address_id']);
    if (isset($_POST['entry_street_address'])) $entry_street_address = HTML::sanitize($_POST['entry_street_address']);

    if (isset($_POST['entry_suburb'])) {
      $entry_suburb = HTML::sanitize($_POST['entry_suburb']);
    } else {
      $entry_suburb = '';
    }

    if (isset($_POST['entry_postcode'])) $entry_postcode = HTML::sanitize($_POST['entry_postcode']);
    if (isset($_POST['entry_city'])) $entry_city = HTML::sanitize($_POST['entry_city']);
    if (isset($_POST['entry_country_id'])) $entry_country_id = HTML::sanitize($_POST['entry_country_id']);

    if (isset($_POST['entry_company'])) {
      $entry_company = HTML::sanitize($_POST['entry_company']);
    } else {
      $entry_company = '';
    }

    if (isset($_POST['entry_state'])) {
      $entry_state = HTML::sanitize($_POST['entry_state']);
    } else {
      $entry_state = '';
    }

    if (isset($_POST['entry_telephone'])) {
      $entry_telephone = HTML::sanitize($_POST['entry_telephone']);
    } else {
      $entry_telephone = '';
    }

// Informations sur la société
    if (ACCOUNT_COMPANY_PRO == 'true') {
      if (isset($_POST['customers_company'])) {
        $customers_company = HTML::sanitize($_POST['customers_company']);
      }
    } else {
      $customers_company = '';
    }
    if (ACCOUNT_SIRET_PRO == 'true') {
      if (isset($_POST['customers_siret'])) {
        $customers_siret = HTML::sanitize($_POST['customers_siret']);
      }
    } else {
      $customers_siret = '';
    }

    if (ACCOUNT_APE_PRO == 'true') {
      if (isset($_POST['customers_ape'])) {
        $customers_ape = HTML::sanitize($_POST['customers_ape']);
      }
    } else {
      $customers_ape = '';
    }

// Information numéro de TVA avec transformation de code ISO en majuscule
    if (ACCOUNT_TVA_INTRACOM_PRO == 'true') {
      if (isset($_POST['customers_tva_intracom_code_iso'])) {
        $customers_tva_intracom_code_iso = HTML::sanitize($_POST['customers_tva_intracom_code_iso']);
      }
    } else {
      $customers_tva_intracom_code_iso = '';
    }

    if (ACCOUNT_TVA_INTRACOM_PRO == 'true') {
      if (isset($_POST['customers_id'])) {
        $customers_tva_intracom_code_iso = mb_strtoupper($customers_tva_intracom_code_iso);
      }
    } else {
      $customers_tva_intracom_code_iso = 0;
    }

    if (ACCOUNT_TVA_INTRACOM_PRO == 'true') {
      if (isset($_POST['customers_tva_intracom'])) {
        $customers_tva_intracom = HTML::sanitize($_POST['customers_tva_intracom']);
      }
    } else {
      $customers_tva_intracom = '';
    }

    if (isset($_POST['entry_zone_id'])) {
      if (isset($_POST['entry_zone_id'])) {
        $entry_zone_id = HTML::sanitize($_POST['entry_zone_id']);
      } else {
        $entry_zone_id = false;
      }
    }

// Autorisation aux clients de modifier adresse principal
    if (isset($_POST['customers_modify_address_default'])) {
      $customers_modify_address_default = HTML::sanitize($_POST['customers_modify_address_default']);
    } else {
      $customers_modify_address_default = 0;
    }

    if (isset($_POST['customers_add_address'])) {
      $customers_add_address = HTML::sanitize($_POST['customers_add_address']);
    } else {
      $customers_add_address = 0;
    }

    if (!\is_null($customers_dob)) {
      $dobDateTime = new DateTime($customers_dob, false);
    } else {
      $dobDateTime = null;
    }

// Contrôle des saisies faites sur les champs TVA Intracom
    if ((\strlen($customers_tva_intracom_code_iso) > 0) || (\strlen($customers_tva_intracom) > 0)) {

      $QcustomersTva = $CLICSHOPPING_Customers->db->prepare('select countries_iso_code_2
                                                               from :table_countries
                                                               where countries_iso_code_2 = :countries_iso_code_2
                                                              ');
      $QcustomersTva->bindValue(':countries_iso_code_2', $customers_tva_intracom_code_iso);

      $QcustomersTva->execute();

      if ($QcustomersTva->fetch()) {
        $error = false;
      } else {
        if (ACCOUNT_TVA_INTRACOM_PRO == 'true') {
          $error = true;
          $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Customers->getDef('error_code_iso'), 'error');
        } else {
          $error = false;
        }
      }
    }

    if (\strlen($customers_firstname) < ENTRY_FIRST_NAME_MIN_LENGTH) {
      $error = true;
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Customers->getDef('error_firstname'), 'error');
    } else {
      $error = false;
    }

    if (\strlen($customers_lastname) < ENTRY_LAST_NAME_MIN_LENGTH) {
      $error = true;
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Customers->getDef('error_lastname'), 'error');
    } else {
      $error = false;
    }

    if (!Is::EmailAddress($customers_email_address)) {
      $error = true;
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Customers->getDef('error_email'), 'error');
    } else {
      $error = false;
    }

    if (\strlen($entry_street_address) < ENTRY_STREET_ADDRESS_MIN_LENGTH) {
      $error = true;
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Customers->getDef('error_street_address'), 'error');
    } else {
      $error = false;
    }

    if (\strlen($entry_postcode) < ENTRY_POSTCODE_MIN_LENGTH) {
      $error = true;
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Customers->getDef('error_postcode'), 'error');
    } else {
      $error = false;
    }

    if (\strlen($entry_city) < ENTRY_CITY_MIN_LENGTH) {
      $error = true;
    } else {
      $error = false;
    }

    if ($entry_country_id === false) {
      $error = true;
      $entry_country_error = true;
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Customers->getDef('error_country'), 'error');
    } else {
      $entry_country_error = false;
    }

    if (ACCOUNT_STATE == 'true') {
      if ($entry_country_error === true) {
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Customers->getDef('error_state'), 'error');

      } else {
        $_SESSION['entry_state_error'] = false;

        $Qcheck = $CLICSHOPPING_Customers->db->get('zones', 'zone_country_id', ['zone_country_id' => (int)$entry_country_id]);
        $_SESSION['entry_state_has_zones'] = $Qcheck->fetch() !== false;

        if ($_SESSION['entry_state_has_zones'] === true) {
          $Qzone = $CLICSHOPPING_Customers->db->get('zones', 'zone_id', [
              'zone_country_id' => (int)$entry_country_id,
              'zone_name' => $entry_state
            ]
          );

          if ($Qzone->fetch() !== false) {
            $entry_zone_id = $Qzone->valueInt('zone_id');
          } else {
            $error = true;
            $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Customers->getDef('error_state'), 'error');
          }
        } else {
          if ($Qcheck->valueInt('zone_country_id') === true) {
            if (\strlen($entry_state) < ENTRY_STATE_MIN_LENGTH) {
              $error = true;
              $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Customers->getDef('error_state'), 'error');
            }
          }
        }
      }
    }

    if (\strlen($customers_telephone) < ENTRY_TELEPHONE_MIN_LENGTH) {
      $error = true;
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Customers->getDef('error_telephone'), 'error');
    }

    $QcheckEmail = $CLICSHOPPING_Customers->db->prepare('select customers_email_address
                                                           from :table_customers
                                                           where customers_email_address = :customers_email_address
                                                           and customers_id <> :customers_id
                                                          ');
    $QcheckEmail->bindValue(':customers_email_address', $customers_email_address);
    $QcheckEmail->bindInt(':customers_id', $customers_id);
    $QcheckEmail->execute();

    if ($QcheckEmail->fetch() !== false) {
      $error = true;
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Customers->getDef('error_email_exists'), 'error');
    }

    if ($error === false) {
      $sql_data_array = [
        'customers_firstname' => Hash::encryptDatatext($customers_firstname),
        'customers_lastname' => Hash::encryptDatatext($customers_lastname),
        'customers_email_address' => $customers_email_address,
        'customers_telephone' => Hash::encryptDatatext($customers_telephone),
        'customers_newsletter' => $customers_newsletter,
        'languages_id' => (int)$language_id,
        'customers_cellular_phone' => Hash::encryptDatatext($customers_cellular_phone),
      ];

//       $customers_dob = str_replace('/', '-', $customers_dob);
      if (!\is_null($customers_dob)) {
        $sql_data_array['customers_dob'] = $dobDateTime->getRaw($customers_dob);
      } else {
        $sql_data_array['customers_dob'] = null;
      }

      if (ACCOUNT_GENDER == 'true') $sql_data_array['customers_gender'] = $customers_gender;

// Informations sur la société
      if (ACCOUNT_COMPANY_PRO == 'true') $sql_data_array['customers_company'] = Hash::encryptDatatext($customers_company);
      if (ACCOUNT_SIRET_PRO == 'true') $sql_data_array['customers_siret'] = $customers_siret;
      if (ACCOUNT_APE_PRO == 'true') $sql_data_array['customers_ape'] = $customers_ape;
      if (ACCOUNT_TVA_INTRACOM_PRO == 'true') $sql_data_array['customers_tva_intracom_code_iso'] = $customers_tva_intracom_code_iso;

      if (ACCOUNT_TVA_INTRACOM_PRO == 'true') {
        $sql_data_array['customers_tva_intracom'] = $customers_tva_intracom;
      }

// Autorisation aux clients de modifier informations société et adresse principal + Ajout adresse
      if ($customers_modify_company == 1) {
        $customers_modify_company = 0;
      } else {
        $customers_modify_company = 1;
      }

      if ($customers_modify_address_default == 1) {
        $customers_modify_address_default = 0;
      } else {
        $customers_modify_address_default = 1;
      }

      if ($customers_add_address != 1) {
        $customers_add_address = 0;
      } else {
        $customers_add_address = 1;
      }

      $sql_data_array['customers_modify_company'] = $customers_modify_company;
      $sql_data_array['customers_modify_address_default'] = $customers_modify_address_default;
      $sql_data_array['customers_add_address'] = $customers_add_address;


      $CLICSHOPPING_Customers->db->save('customers', $sql_data_array, ['customers_id' => (int)$customers_id]);

      $CLICSHOPPING_Customers->db->save('customers_info',
        ['customers_info_date_account_last_modified' => 'now()'],
        ['customers_info_id' => (int)$customers_id]
      );

// notes clients
      if (!empty($customers_notes)) {

        $CLICSHOPPING_Customers->db->save('customers_notes', [
            'customers_id' => (int)$customers_id,
            'customers_notes' => $customers_notes,
            'customers_notes_date' => 'now()',
            'user_administrator' => AdministratorAdmin::getUserAdmin()
          ]
        );

      } // end empty($customers_notes)

      if (isset($entry_zone_id) && $entry_zone_id > 0) $entry_state = '';

      $sql_data_array = [
        'entry_firstname' => Hash::encryptDatatext($customers_firstname),
        'entry_lastname' => Hash::encryptDatatext($customers_lastname),
        'entry_street_address' => Hash::encryptDatatext($entry_street_address),
        'entry_postcode' => Hash::encryptDatatext($entry_postcode),
        'entry_city' => Hash::encryptDatatext($entry_city),
        'entry_country_id' => (int)$entry_country_id,
        'entry_telephone' => Hash::encryptDatatext($entry_telephone)
      ];

      if (ACCOUNT_COMPANY == 'true') {
        $sql_data_array['entry_company'] = Hash::encryptDatatext($entry_company);
      }

      if (ACCOUNT_SUBURB == 'true') {
        $sql_data_array['entry_suburb'] = Hash::encryptDatatext( $entry_suburb);
      }

      if (ACCOUNT_STATE == 'true') {
        if (isset($entry_zone_id) && $entry_zone_id > 0) {
          $sql_data_array['entry_zone_id'] = $entry_zone_id;
          $sql_data_array['entry_state'] = '';
        } else {
          $sql_data_array['entry_zone_id'] = 0;
          $sql_data_array['entry_state'] = $entry_state;
        }
      }

      $CLICSHOPPING_Customers->db->save('address_book', $sql_data_array, [
          'customers_id' => (int)$customers_id,
          'address_book_id' => (int)$default_address_id
        ]
      );

      $CLICSHOPPING_Hooks->call('Customers', 'Update');

      $CLICSHOPPING_Customers->redirect('Customers&page=' . $page . '&cID=' . $customers_id);

    } elseif ($error === true) {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Customers->getDef('error_in_form'), 'error');

      $CLICSHOPPING_Customers->redirect('Edit&cID=' . $customers_id);
    }
  }
}