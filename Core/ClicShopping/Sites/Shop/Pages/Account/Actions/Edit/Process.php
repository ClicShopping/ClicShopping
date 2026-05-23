<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Actions\Edit;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Is;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\Pages\Account\Classes\Edit;
use function strlen;

class Process extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    if (isset($_POST['action']) && ($_POST['action'] == 'process') && isset($_POST['formid']) && ($_POST['formid'] === $_SESSION['sessiontoken'])) {

      if (isset($_POST['gender']) && ((ACCOUNT_GENDER == 'true' && $CLICSHOPPING_Customer->getCustomersGroupID() == 0) || (ACCOUNT_GENDER_PRO == 'true' && $CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
        $gender = HTML::sanitize($_POST['gender']);
      }

      if (isset($_POST['firstname'])) $firstname = HTML::sanitize($_POST['firstname']);
      if (isset($_POST['lastname'])) $lastname = HTML::sanitize($_POST['lastname']);

      if (isset($_POST['dob']) && ((ACCOUNT_DOB == 'true' && $CLICSHOPPING_Customer->getCustomersGroupID() == 0) || ((ACCOUNT_DOB_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)))) {
        $dob = DateTime::toShortWithoutFormat(HTML::sanitize($_POST['dob']));
      } else {
        $dob = null;
      }

      if (isset($_POST['email_address'])) {
        $email_address = HTML::sanitize($_POST['email_address']);
      }

      if (isset($_POST['customers_telephone'])) {
        $telephone = HTML::sanitize($_POST['customers_telephone']);
      } else {
        $telephone = null;
      }

      if (isset($_POST['customers_cellular_phone']) && ((ACCOUNT_CELLULAR_PHONE == 'true' && $CLICSHOPPING_Customer->getCustomersGroupID() == 0) || ((ACCOUNT_CELLULAR_PHONE_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)))) {
        $cellular_phone = HTML::sanitize($_POST['customers_cellular_phone']);
      } else {
        $cellular_phone = null;
      }

// Clients en mode B2B : Informations societe
      if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
        if (ACCOUNT_COMPANY_PRO == 'true' && isset($_POST['company'])) $company = HTML::sanitize($_POST['company']);
        if (ACCOUNT_SIRET_PRO == 'true' && isset($_POST['siret'])) $siret = HTML::sanitize($_POST['siret']);
        if (ACCOUNT_APE_PRO == 'true' && isset($_POST['ape'])) $ape = HTML::sanitize($_POST['ape']);
        if (ACCOUNT_TVA_INTRACOM_PRO == 'true' && isset($_POST['tva_intracom'])) $tva_intracom = HTML::sanitize($_POST['tva_intracom']);
        if (ACCOUNT_TVA_INTRACOM_PRO == 'true' && isset($_POST['compisoany'])) $iso = HTML::sanitize($_POST['iso']);
      }

      $error = false;

// Clients B2C et B2B : Controle selection de la civilite
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

// Clients B2B : Controle de la selection du pays pour le code ISO
      if ((ACCOUNT_COMPANY_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
        if (strlen($company) < ENTRY_COMPANY_PRO_MIN_LENGTH) {
          $error = true;

          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_company_error_pro', ['min_length' => ENTRY_COMPANY_PRO_MIN_LENGTH]), 'error');
        }
      }

      if ((ACCOUNT_SIRET_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
        if (strlen($siret) < ENTRY_SIRET_MIN_LENGTH) {
          $error = true;

          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_siret_error', ['min_length' => ENTRY_SIRET_MIN_LENGTH]), 'error');
        }
      }

      if ((ACCOUNT_APE_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
        if (strlen($ape) < ENTRY_CODE_APE_MIN_LENGTH) {
          $error = true;

          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_code_ape_error', ['min_length' => ENTRY_CODE_APE_MIN_LENGTH]), 'error');
        }
      }

      if ((ACCOUNT_TVA_INTRACOM_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
        if (strlen($tva_intracom) < ENTRY_TVA_INTRACOM_MIN_LENGTH) {
          $error = true;

          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_tva_intracom_error', ['min_length' => ENTRY_TVA_INTRACOM_MIN_LENGTH]), 'error');
        }
      }

      if ((strlen($firstname) < ENTRY_FIRST_NAME_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(ENTRY_FIRST_NAME_ERROR, 'danger', 'account_edit');
      } elseif ((strlen($firstname) < ENTRY_FIRST_NAME_PRO_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_first_name_error_pro', ['min_length' => ENTRY_FIRST_NAME_PRO_MIN_LENGTH]), 'error');
      }

      if ((strlen($lastname) < ENTRY_LAST_NAME_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(ENTRY_LAST_NAME_ERROR, 'danger', 'account_edit');
      } elseif ((strlen($lastname) < ENTRY_LAST_NAME_PRO_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_last_name_error_pro', ['min_length' => ENTRY_LAST_NAME_PRO_MIN_LENGTH]), 'error');
      }

      if ((ACCOUNT_DOB == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) {

        $dobDateTime = new DateTime($dob, false);

        if ((strlen($dob) < ENTRY_DOB_MIN_LENGTH) || ($dobDateTime->isValid() === false)) {
          $error = true;

          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_date_of_birth_error'), 'error');
        }
      } elseif ((ACCOUNT_DOB_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {

        $dobDateTime = new DateTime($dob, false);

        if ((strlen($dob) < ENTRY_DOB_MIN_LENGTH) || ($dobDateTime->isValid() === false)) {
          $error = true;

          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_date_of_birth_error_pro', ['min_length' => ENTRY_DOB_MIN_LENGTH]), 'error');
        }
      }

      if (Is::EmailAddress($email_address) === false) {
        $error = true;

        if ($CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_email_address_check_error'), 'error');

        } elseif ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_email_address_check_error_pro'), 'error');
        }
      }

      $check_email = edit::getCountEmail($email_address);

      if ($check_email > 0) {
        $check_customer_email = edit::getCustomerAddressEmail($email_address);

        if ($check_customer_email !== false) {
          $error = true;

          if ($CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
            $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_email_address_error_exists'), 'error');
          } elseif ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
            $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_email_address_error_exists_pro'), 'error');
          }
        }
      }

// Clients B2C et B2B : Controle entree telephone
      if ((strlen($telephone) < ENTRY_TELEPHONE_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(ENTRY_TELEPHONE_NUMBER_ERROR, 'danger', 'account_edit');
      } elseif ((strlen($telephone) < ENTRY_TELEPHONE_PRO_MIN_LENGTH) && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_telephone_number_error_pro', ['min_length' => ENTRY_TELEPHONE_PRO_MIN_LENGTH]), 'error');
      }

      if ($error === false) {
        $sql_data_array = [
          'customers_firstname' =>  Hash::encryptDatatext($firstname),
          'customers_lastname' =>  Hash::encryptDatatext($lastname),
          'customers_email_address' => $email_address,
          'customers_telephone' =>  Hash::encryptDatatext($telephone)
        ];

        if (((ACCOUNT_CELLULAR_PHONE == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) || ((ACCOUNT_CELLULAR_PHONE_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
          $sql_data_array['customers_cellular_phone'] = $cellular_phone;
        }

        if (((ACCOUNT_GENDER == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) || ((ACCOUNT_GENDER_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
          $sql_data_array['customers_gender'] = $gender;
        }

        if (((ACCOUNT_DOB == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) || ((ACCOUNT_DOB_PRO == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() != 0))) {
          $sql_data_array['customers_dob'] = $dobDateTime->getRaw(false);
        }

// Clients en mode B2B : Informations societe
        if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
          if (ACCOUNT_COMPANY_PRO == 'true') $sql_data_array['customers_company'] = Hash::encryptDatatext($company);
          if (ACCOUNT_SIRET_PRO == 'true') $sql_data_array['customers_siret'] = $siret;
          if (ACCOUNT_APE_PRO == 'true') $sql_data_array['customers_ape'] = $ape;
          if (ACCOUNT_TVA_INTRACOM_PRO == 'true') $sql_data_array['customers_tva_intracom'] = $tva_intracom;
          if (ACCOUNT_TVA_INTRACOM_PRO == 'true') $sql_data_array['customers_tva_intracom_code_iso'] = $iso;
        }

        $insert_array = ['customers_id' => (int)$CLICSHOPPING_Customer->getID()];
        $CLICSHOPPING_Db->save('customers', $sql_data_array, $insert_array);

        $CLICSHOPPING_Db->save('customers_info',
          ['customers_info_date_account_last_modified' => 'now()'],
          ['customers_info_id' => (int)$CLICSHOPPING_Customer->getID()]
        );

        $sql_data_array = [
          'customers_firstname' => Hash::encryptDatatext($firstname),
          'customers_lastname' => Hash::encryptDatatext($lastname)
        ];

        $CLICSHOPPING_Db->save('customers', $sql_data_array, ['customers_id' => (int)$CLICSHOPPING_Customer->getID()],
          ['address_book_id' => (int)$CLICSHOPPING_Customer->getDefaultAddressID()]
        );

// Clients en mode B2B : Modifier le nom de la societe sur toutes les adresses ce trouvant dans le carnet d'adresse
        if (($CLICSHOPPING_Customer->getCustomersGroupID() != 0) && (ACCOUNT_COMPANY_PRO == 'true')) {
          $sql_data_array = ['customers_company' => Hash::encryptDatatext($company)];
          $insert_array = ['customers_id' => (int)$CLICSHOPPING_Customer->getID()];

          $CLICSHOPPING_Db->save('customers', $sql_data_array, $insert_array);
        }

        $CLICSHOPPING_Hooks->call('Edit', 'Process');

        $_SESSION['customer_first_name'] = $firstname;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('success_account_updated'), 'success', 'account_edit');
        CLICSHOPPING::redirect(null, 'Account&Main');
      }
    }
  }
}