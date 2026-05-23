<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Actions\LogInAuth;

use ClicShopping\Apps\Configuration\TemplateEmail\Classes\Shop\TemplateEmail;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class Process extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  private mixed $customer;
  private mixed $db;

  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->customer = Registry::get('Customer');
  }

  /**
   * Send mail
   * @return void
   */
  private function sentEmail(): void
  {
    $CLICSHOPPING_Mail = Registry::get('Mail');

    if (CONFIGURATION_EMAIL_CUSTOMER_SECURITY == 'true') {
      $body_subject = CLICSHOPPING::getDef('email_text_warning_login_subject', ['store_name' => STORE_NAME]);

      $email_body = CLICSHOPPING::getDef('email_text_warning_login_message', ['store_name' => STORE_NAME]);
      $email_body .= TemplateEmail::getTemplateEmailSignature() . "\n";
      $email_body .= TemplateEmail::getTemplateEmailTextFooter();

      $to_addr = $this->customer->getEmailAddress();

      $from_name = STORE_OWNER;
      $from_addr = STORE_OWNER_EMAIL_ADDRESS;
      $to_name = NULL;
      $subject = $body_subject;

      $CLICSHOPPING_Mail->addHtml($email_body);
      $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);
    }
  }

  public function execute()
  {
    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
    $CLICSHOPPING_NavigationHistory = Registry::get('NavigationHistory');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    $error =false;

    $CLICSHOPPING_Hooks->call('LogInAuth', 'postProcess');

    if (defined('EMAIL_VERIFICATION_ENABLED_SHOP') && EMAIL_VERIFICATION_ENABLED_SHOP == 'False') {
      CLICSHOPPING::redirect(null, 'Account&LogIn');
    }

    if(!isset($_SESSION['email_code'])) {
      $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_email_verification_failed'), 'error');
      CLICSHOPPING::redirect(null, 'Account&LogInAuth');
    }

    // redirect the customer to a friendly cookie-must-be-enabled page if cookies are disabled (or the session has not started)
    if (Registry::get('Session')->hasStarted() === false) {
      if (!isset($_GET['cookie_test'])) {
        $all_get = CLICSHOPPING::getAllGET();

        CLICSHOPPING::redirect(null, 'Account&LogInAuth&' . $all_get . (empty($all_get) ? '' : '&') . 'cookie_test=1');
      }

      CLICSHOPPING::redirect(null, 'Info&Cookies');
    }

    if (!isset($_SESSION['email_address']) || !isset($_SESSION['password'])) {
      unset($_SESSION['email_address']);
      unset($_SESSION['password']);
      CLICSHOPPING::redirect('Account&LogIn');
    }

// activate the login session or not
    if (isset($_SESSION['email_address']) && isset($_SESSION['password'])) {
      $array_sql =  ['customers_id'];

      $Qcheck = $this->db->get('customers', $array_sql, ['customers_email_address' => $_SESSION['email_address']], null, 1);

      if ($Qcheck->valueInt('customers_id') === 0) {
        $error = true;
      } else {
        $login_customer_id = $Qcheck->valueInt('customers_id');
        $_SESSION['login_customer_id'] = $login_customer_id;
      }
    } else {
      CLICSHOPPING::redirect(null, 'Account&LogInAuth');
    }

    if (isset($_SESSION['login_customer_id'])) {
      $login_customer_id = $_SESSION['login_customer_id'];
    } else {
      $login_customer_id = 0;
    }

    if (is_numeric($login_customer_id) && ($login_customer_id > 0) && $error === false) {
      if ($login_customer_id > 0) {
        $this->customer->setData($login_customer_id);
	
        if (isset($_SESSION['email_code'])) {
          unset($_SESSION['email_code']);
        }
      }

      $Qupdate = $this->db->prepare('update :table_customers_info
                                      set customers_info_date_of_last_logon = now(),
                                          customers_info_number_of_logons = customers_info_number_of_logons+1,
                                          password_reset_key = null,
                                          password_reset_date = null
                                      where customers_info_id = :customers_info_id
                                    ');
      $Qupdate->bindInt(':customers_info_id', $login_customer_id);
      $Qupdate->execute();

      $this->sentEmail();
    } else {
      $this->sentEmail();
      CLICSHOPPING::redirect(null, 'Account&LogIn');
    }

// restore cart contents
    $CLICSHOPPING_ShoppingCart->getRestoreContents();

    $CLICSHOPPING_NavigationHistory->removeCurrentPage();

    $CLICSHOPPING_Hooks->call('LogInAuth', 'Process');

    if ($CLICSHOPPING_NavigationHistory->hasSnapshot()) {
      CLICSHOPPING::redirect(null, 'Account&Main');
    } else {
      CLICSHOPPING::redirect();
    }
  }
}