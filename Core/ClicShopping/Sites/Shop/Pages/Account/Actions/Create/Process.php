<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Actions\Create;

use ClicShopping\Apps\Configuration\TemplateEmail\Classes\Shop\TemplateEmail;
use ClicShopping\Apps\Tools\ActionsRecorder\Classes\Shop\ActionRecorder;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Is;
use ClicShopping\OM\Registry;
use function defined;
use function strlen;

class Process extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
    $CLICSHOPPING_Mail = Registry::get('Mail');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    if (isset($_POST['action']) && ($_POST['action'] == 'process') && isset($_POST['formid']) && ($_POST['formid'] === $_SESSION['sessiontoken'])) {
// error checking when updating or adding an entry
      $error = false;

      $CLICSHOPPING_Hooks->call('Create', 'PreAction');

      $firstname = HTML::sanitize($_POST['firstname']);
      $lastname = HTML::sanitize($_POST['lastname']);

      if (ACCOUNT_DOB == 'true') $dob = DateTime::toShortWithoutFormat(HTML::sanitize($_POST['dob']));

      $email_address = HTML::sanitize($_POST['email_address']);
      $email_address_confirm = HTML::sanitize($_POST['email_address_confirm']);

      if (isset($_POST['customers_telephone'])) {
        $telephone = HTML::sanitize($_POST['customers_telephone']);
      } else {
        $telephone = null;
      }

      if (isset($_POST['customers_cellular_phone'])) {
        $cellular_phone = HTML::sanitize($_POST['customers_cellular_phone']);
      } else {
        $cellular_phone = null;
      }
      if (isset($_POST['newsletter'])) {
        $newsletter = HTML::sanitize($_POST['newsletter']);
      } else {
        $newsletter = 0;
      }

      $password = HTML::sanitize($_POST['password']);
      $confirmation = HTML::sanitize($_POST['confirmation']);

      $customer_agree_privacy = HTML::sanitize($_POST['customer_agree_privacy']);

      if (DISPLAY_PRIVACY_CONDITIONS == 'true') {
        if ($customer_agree_privacy != 'on') {
          $error = true;
          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_agreement_check_error'), 'error');
        }
      }

// Clients B2C : Controle entree du prenom
      if (strlen($firstname) < ENTRY_FIRST_NAME_MIN_LENGTH) {
        $error = true;
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_first_name_error', ['min_length' => (int)ENTRY_FIRST_NAME_MIN_LENGTH]), 'error');
      }

// Clients B2C : Controle entree du nom de famille
      if (strlen($lastname) < ENTRY_LAST_NAME_MIN_LENGTH) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_last_name_error', ['min_length' => (int)ENTRY_LAST_NAME_MIN_LENGTH]), 'error');
      }

// Clients B2C : Controle entree date de naissance
      if (ACCOUNT_DOB == 'true') {
        $dobDateTime = new DateTime($dob);

        if ((strlen($dob) < ENTRY_DOB_MIN_LENGTH) || ($dobDateTime->isValid() === false)) {
          $error = true;

          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_date_of_birth_error'), 'error');
        }
      }

// Clients B2C : Controle entree adresse e-mail
      if (Is::EmailAddress($email_address) === false) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_email_address_check_error', ['min_length' => (int)ENTRY_EMAIL_ADDRESS_MIN_LENGTH]), 'error');

      } elseif ($email_address != $email_address_confirm) {
        $error = true;
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_email_address_confirm_not_matching'), 'error');
      } else {
        $Qcheckemail = $CLICSHOPPING_Db->prepare('select customers_id
                                                    from :table_customers
                                                    where customers_email_address = :customers_email_address
                                                   ');
        $Qcheckemail->bindValue(':customers_email_address', $email_address);

        $Qcheckemail->execute();

        if ($Qcheckemail->fetch() !== false) {
          $error = true;

          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_email_address_error_exists'), 'error');
        }
      }

      if (strlen($password) < ENTRY_PASSWORD_MIN_LENGTH) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_password_error', ['min_length' => (int)ENTRY_PASSWORD_MIN_LENGTH]), 'error');

      } elseif ($password != $confirmation) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_password_error_not_matching'), 'error');
      }

      Registry::set('ActionRecorder', new ActionRecorder('ar_create_account', ($CLICSHOPPING_Customer->isLoggedOn() ? $CLICSHOPPING_Customer->getID() : null), $lastname));
      $CLICSHOPPING_ActionRecorder = Registry::get('ActionRecorder');

      if (!$CLICSHOPPING_ActionRecorder->canPerform()) {
        $error = true;
        $CLICSHOPPING_ActionRecorder->record(false);

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_action_recorder', ['module_action_recorder_create_account_email_minutes' => (defined('MODULE_ACTION_RECORDER_CREATE_ACCOUNT_EMAIL_MINUTES') ? (int)MODULE_ACTION_RECORDER_CREATE_ACCOUNT_EMAIL_MINUTES : 15)]), 'error');
      }

      if ($error === false) {
        $sql_data_array = [
          'customers_firstname' => Hash::encryptDatatext($firstname),
          'customers_lastname' => Hash::encryptDatatext($lastname),
          'customers_email_address' => $email_address,
          'customers_newsletter' => (int)$newsletter,
          'languages_id' => (int)$CLICSHOPPING_Language->getId(),
          'customers_password' => Hash::encrypt($password),
          'customers_telephone' => Hash::encryptDatatext($telephone),
          'customers_cellular_phone' => Hash::encryptDatatext($cellular_phone),
          'member_level' => 1,
          'client_computer_ip' => HTTP::getIPAddress(),
          'provider_name_client' => HTTP::getProviderNameCustomer(),
        ];

        if (ACCOUNT_DOB == 'true') $sql_data_array['customers_dob'] = $dobDateTime->getRaw(false);

        $CLICSHOPPING_Db->save('customers', $sql_data_array);

        $customer_id = $CLICSHOPPING_Db->lastInsertId();

// save element in address book
        $sql_data_array_book = [
          'customers_id' => (int)$customer_id,
          'entry_firstname' => Hash::encryptDatatext($firstname),
          'entry_lastname' => Hash::encryptDatatext($lastname),
          'entry_telephone' => Hash::encryptDatatext($telephone)
        ];

        $CLICSHOPPING_Db->save('address_book', $sql_data_array_book);

        $address_id = $CLICSHOPPING_Db->lastInsertId();

        $sql_data_array = ['customers_default_address_id' => (int)$address_id];

        $insert_array = ['customers_id' => (int)$customer_id];

        $CLICSHOPPING_Db->save('customers', $sql_data_array, $insert_array);

        $sql_array = [
          'customers_info_id' => (int)$customer_id,
          'customers_info_number_of_logons' => 0,
          'customers_info_date_account_created' => 'now()'
        ];

        $CLICSHOPPING_Db->save('customers_info', $sql_array);

        $CLICSHOPPING_Customer->setData($customer_id);

        Registry::get('Session')->recreate();

// restore cart contents
        $CLICSHOPPING_ShoppingCart->getRestoreContents();

// build the message content
        $name = $firstname . ' ' . $lastname;

        $template_email_welcome_catalog = TemplateEmail::getTemplateEmailWelcomeCatalog();

        if (!empty(COUPON_CUSTOMER)) {
          $email_coupon_catalog = TemplateEmail::getTemplateEmailCouponCatalog();
          $email_coupon = $email_coupon_catalog . HTML::sanitize(COUPON_CUSTOMER);
        } else {
          $email_coupon = '';
        }

// Content email
        $template_email_signature = TemplateEmail::getTemplateEmailSignature();
        $template_email_footer = TemplateEmail::getTemplateEmailTextFooter();
        $email_subject = CLICSHOPPING::getDef('email_subject', ['store_name' => STORE_NAME]);
        $email_gender = CLICSHOPPING::getDef('email_greet_ms', ['last_name' => $lastname]) . ', ' . CLICSHOPPING::getDef('email_greet_mr', ['last_name' => $lastname]) . ' ' . $lastname;
        $email_text = $email_gender . ',<br /><br />' . $template_email_welcome_catalog . '<br /><br />' . $email_coupon . '<br /><br />' . $template_email_signature . '<br /><br />' . $template_email_footer;

// EEmail send
        $message = $email_text;
        $message = str_replace('src="/', 'src="' . HTTP::typeUrlDomain() . '/', $message);
        $CLICSHOPPING_Mail->addHtmlCkeditor($message);

        $from = STORE_OWNER_EMAIL_ADDRESS;

        $CLICSHOPPING_Mail->send($email_address, $name, null, $from, $email_subject);
// Administrator email
        if (EMAIL_INFORMA_ACCOUNT_ADMIN == 'true') {
          $email_subject_admin = CLICSHOPPING::getDef('admin_email_subject', ['store_name' => STORE_NAME]);
          $admin_email_welcome = CLICSHOPPING::getDef('admin_email_welcome');

          $data_array = [
            'customer_name' => $lastname,
            'customer_firstname' => $firstname,
            'customer_mail' => $email_address
          ];

          $admin_email_text_admin = CLICSHOPPING::getDef('admin_email_text', $data_array);

          $admin_to = STORE_OWNER_EMAIL_ADDRESS;
          $from = STORE_OWNER_EMAIL_ADDRESS;
          $admin_email_text_admin = $admin_email_welcome . $admin_email_text_admin;
          $CLICSHOPPING_Mail->addHtmlCkeditor($admin_email_text_admin);

          $CLICSHOPPING_Mail->send($admin_to, STORE_NAME, null, $from, $email_subject_admin);
        }

        $CLICSHOPPING_ActionRecorder->record();

        $CLICSHOPPING_Hooks->call('Create', 'Process');

        CLICSHOPPING::redirect(null, 'Account&AddressBookProcess&edit=' . $CLICSHOPPING_Customer->getID() . '&newcustomer=1');
      }
    }
  }
}