<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\PageManager\Sites\Shop\Pages\Contact\Actions\Contact;

use ClicShopping\Apps\Configuration\TemplateEmail\Classes\ClicShoppingAdmin\TemplateEmailAdmin;
use ClicShopping\Apps\Configuration\TemplateEmail\Classes\Shop\TemplateEmail;
use ClicShopping\Apps\Tools\ActionsRecorder\Classes\Shop\ActionRecorder;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Is;
use ClicShopping\OM\Registry;

class Process extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Mail = Registry::get('Mail');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_PageManager = Registry::get('PageManager');

    $CLICSHOPPING_PageManager->loadDefinitions('Sites/Shop/Contact/contact');

    if (isset($_POST['action'], $_POST['formid']) && ($_POST['action'] == 'process') && ($_POST['formid'] === $_SESSION['sessiontoken'])) {
      $CLICSHOPPING_Hooks->call('Contact', 'PreAction');

      $name = HTML::sanitize($_POST['name']);
      $email_address = HTML::sanitize($_POST['email']);
      $enquiry = HTML::sanitize($_POST['enquiry']);
      $email_subject = HTML::sanitize($_POST['email_subject']);

      $error = false;

      if (isset($_POST['name'])) {
        $name = HTML::sanitize($_POST['name']);
      } else {
        $error = true;
      }

      if (isset($_POST['enquiry'])) {
        $enquiry = HTML::sanitize($_POST['enquiry']);
      } else {
        $error = true;
      }

      if (isset($_POST['email_subject'])) {
        $email_subject = HTML::sanitize($_POST['email_subject']);
      } else {
        $error = true;
      }

      if (isset($_POST['order_id'])) {
        $order_id = HTML::sanitize($_POST['order_id']);
      } else {
        $order_id = 0;
      }

      if (isset($_POST['send_to'])) {
        $send_to = HTML::sanitize($_POST['send_to']);
      } else {
        $send_to = [];
      }

      if (isset($_POST['customer_id'])) {
        $customer_id = HTML::sanitize($_POST['customer_id']);
      } else {
        $customer_id = null;
      }

      if (isset($_POST['customers_telephone'])) {
        $customers_telephone = HTML::sanitize($_POST['customers_telephone']);
      } else {
        $customers_telephone = '';
      }

      if (isset($_POST['customer_agree_privacy'])) {
        $customer_agree_privacy = HTML::sanitize($_POST['customer_agree_privacy']);
      } else {
        $customer_agree_privacy = null;
      }

      if (\defined('DISPLAY_PRIVACY_CONDITIONS') && DISPLAY_PRIVACY_CONDITIONS == 'true') {
        if ($customer_agree_privacy != 'on') {
          $error = true;
          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_agreement_check_error'), 'error');
        }
      }

      if (Is::EmailAddress($email_address) === false) {
        $error = true;
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_PageManager->getDef('entry_email_address_check_error'), 'warning');
      }

      Registry::set('ActionRecorder', new ActionRecorder('ar_contact_us', ($CLICSHOPPING_Customer->isLoggedOn() ? $CLICSHOPPING_Customer->getID() : null), $name));
      $CLICSHOPPING_ActionRecorder = Registry::get('ActionRecorder');

      if (!$CLICSHOPPING_ActionRecorder->canPerform()) {
        $error = true;
        $CLICSHOPPING_ActionRecorder->record(false);
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_PageManager->getDef('error_action_recorder', ['module_action_recorder_contact_us_email_minutes' => (\defined('MODULE_ACTION_RECORDER_CONTACT_US_EMAIL_MINUTES') ? (int)MODULE_ACTION_RECORDER_CONTACT_US_EMAIL_MINUTES : 15)]), 'error');
      }

      $template_email_footer = TemplateEmailAdmin::getTemplateEmailTextFooter();

      if ($CLICSHOPPING_Mail->validateDomainEmail($email_address) === false || $CLICSHOPPING_Mail->excludeEmailDomain($email_address) === true) {
        $error = true;
      }

      if ($error === false) {
        $today = date("Y-m-d H:i:s");
        if (!empty(\defined('CONTACT_DEPARTMENT_LIST') ? CONTACT_DEPARTMENT_LIST : '')) {
          $email_address_department = TemplateEmail::getExtractEmailAddress(CONTACT_DEPARTMENT_LIST);

          if (empty($send_to)) {
            $email_number = 0;
          } else {
            $email_number = $send_to;
          }

          if ($email_number !== 0) {
            $email_address_department = $email_address_department[$email_number];

            if (!\is_null($customer_id)) {
              $num_customer_id = $CLICSHOPPING_PageManager->getDef('entry_customers_id') . ' ' . $customer_id;
            } else {
              $num_customer_id = '';
            }

            if ($CLICSHOPPING_Customer->isLoggedOn() && !empty($order_id)) {
              $message_info_admin = $CLICSHOPPING_PageManager->getDef('entry_information_admin');
            } else {
              $message_info_admin = '';
            }

            $message_to_admin = $email_subject . ' ' . \defined('STORE_NAME') ? STORE_NAME : '' . "\n\n" .
              $message_info_admin . "\n\n" . $CLICSHOPPING_PageManager->getDef('entry_date') . ' ' . $today . "\n" .
              $num_customer_id . "\n" .
              $CLICSHOPPING_PageManager->getDef('entry_order') . ' ' . $order_id . "\n" .
              $CLICSHOPPING_PageManager->getDef('entry_name') . ' ' . $name . "\n" .
              $CLICSHOPPING_PageManager->getDef('entry_email') . ' ' . $email_address . "\n" . $CLICSHOPPING_PageManager->getDef('entry_enquiry_customer_information') . ' ' . $enquiry . "\n\n" .
              $CLICSHOPPING_PageManager->getDef('entry_admin_read_message') . "\n\n";
// aadmin
            $to_addr = $email_address_department;
            $from_name = \defined('STORE_NAME') ? STORE_NAME : '';
            $from_addr = $email_address;
            $to_name = $name;
            $subject = $email_subject;

            $CLICSHOPPING_Mail->addHtml($message_to_admin);
            $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);

// send information to customer
            $message_to_customer = $email_subject . ' ' . \defined('STORE_NAME') ? STORE_NAME : '' . "\n\n" .
              $CLICSHOPPING_PageManager->getDef('entry_date') . ' ' . $today . "\n" .
              $num_customer_id . "\n\n" .
              $CLICSHOPPING_PageManager->getDef('entry_order') . ' ' . $order_id . "\n" .
              $CLICSHOPPING_PageManager->getDef('entry_name') . ' ' . $name . "\n" .
              $CLICSHOPPING_PageManager->getDef('entry_customers_phone') . ' ' . $customers_telephone . "\n" .
              $CLICSHOPPING_PageManager->getDef('entry_email') . ' ' . $email_address . "\n" .
              $CLICSHOPPING_PageManager->getDef('entry_enquiry_customer') . ' ' . $enquiry . "\n\n" .
              $CLICSHOPPING_PageManager->getDef('entry_additional_information') . "\n\n" .
              $template_email_footer;

            $to_addr = $email_address;
            $from_name = \defined('STORE_NAME') ? STORE_NAME : '';
            $from_addr = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
            $to_name = $name;
            $subject = $CLICSHOPPING_PageManager->getDef('entry_email_object_customer');

            $CLICSHOPPING_Mail->addHtml($message_to_customer);
            $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);
          }
        } else {
          $message_to_admin = $email_subject . ' ' . (\defined('STORE_NAME') ? STORE_NAME : '') . "\n\n" .
          $CLICSHOPPING_PageManager->getDef('entry_date') . ' ' . $today . "\n" .
          $CLICSHOPPING_PageManager->getDef('entry_customers_id') . ' ' . $customer_id . "\n\n" .
          $CLICSHOPPING_PageManager->getDef('entry_name') . ' ' . $name . "\n" .
          $CLICSHOPPING_PageManager->getDef('entry_customers_phone') . ' ' . $customers_telephone . "\n" .
          $CLICSHOPPING_PageManager->getDef('entry_email') . ' ' . $email_address . "\n" .
          $CLICSHOPPING_PageManager->getDef('entry_enquiry_customer_information') . ' ' . $enquiry . "\n\n" .
          $CLICSHOPPING_PageManager->getDef('entry_admin_read_message') . "\n\n";

          $to_addr = STORE_OWNER_EMAIL_ADDRESS;
          $from_name = $name;
          $from_addr = $email_address;
          $to_name = STORE_OWNER;
          $subject = $email_subject;

          $message_to_admin = nl2br($message_to_admin);
          $CLICSHOPPING_Mail->addHtml($message_to_admin);

          $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);
// send information to customer
            $message_to_customer = $email_subject . ' ' . \defined('STORE_NAME') ? STORE_NAME : '' . "\n\n" .
            $CLICSHOPPING_PageManager->getDef('entry_date') . ' ' . $today . "\n" .
            $CLICSHOPPING_PageManager->getDef('entry_customers_id') . ' ' . $customer_id . "\n\n" .
            $CLICSHOPPING_PageManager->getDef('entry_name') . ' ' . $name . "\n" .
            $CLICSHOPPING_PageManager->getDef('entry_customers_phone') . ' ' . $customers_telephone . "\n" .
            $CLICSHOPPING_PageManager->getDef('entry_email') . ' ' . $email_address . "\n" .
            $CLICSHOPPING_PageManager->getDef('entry_enquiry_customer') . ' ' . $enquiry . "\n\n" .
            $CLICSHOPPING_PageManager->getDef('entry_additional_information') . "\n\n" . $template_email_footer;

          $CLICSHOPPING_Mail->addHtmlCkeditor($message_to_customer);
          $to_addr = $email_address;
          $from_name = \defined('STORE_NAME') ? STORE_NAME : '';
          $from_addr = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
          $to_name = $name;
          $subject = $CLICSHOPPING_PageManager->getDef('entry_email_object_customer');

          $CLICSHOPPING_Mail->addHtml($message_to_admin);
          $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);
        }

// insert the modification in the database
        if ($CLICSHOPPING_Customer->isLoggedOn()) {
          if ($order_id !== 0) {
            $sql_insert_array = [
              'orders_id' => (int)$order_id,
              'orders_status_invoice_id' => 1,
              'admin_user_name' => '',
              'date_added' => 'now()',
              'customer_notified' => 1,
              'comments' => $enquiry,
              'orders_status_support_id' => 2,
              'evidence' => ''
            ];

            $CLICSHOPPING_Db->save('orders_status_history', $sql_insert_array);
          }
        }

        $CLICSHOPPING_Hooks->call('Contact', 'Process');

        $CLICSHOPPING_ActionRecorder->record();

        CLICSHOPPING::redirect(null, 'Info&Contact&Success');
      } else {
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_PageManager->getDef('entry_error_contact'), 'warning');

        CLICSHOPPING::redirect(null, 'Info&Contact');
      }
    } else {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_PageManager->getDef('entry_error_contact'), 'warning');

      CLICSHOPPING::redirect(null, 'Info&Contact');
    }
  }
}