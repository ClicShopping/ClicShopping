<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Actions\LogIn;

use ClicShopping\Apps\Configuration\TemplateEmail\Classes\Shop\TemplateEmail;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class Process extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  /**
   * Send mail
   * @return void
   */
  private static function sentEmail(): void
  {
    $CLICSHOPPING_Mail = Registry::get('Mail');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (CONFIGURATION_EMAIL_CUSTOMER_SECURITY == 'true') {
      $body_subject = CLICSHOPPING::getDef('email_text_warning_login_subject', ['store_name' => STORE_NAME]);

      $email_body = CLICSHOPPING::getDef('email_text_warning_login_message', ['store_name' => STORE_NAME]);
      $email_body .= TemplateEmail::getTemplateEmailSignature() . "\n";
      $email_body .= TemplateEmail::getTemplateEmailTextFooter();

      $to_addr = $CLICSHOPPING_Customer->getEmailAddress();

      $from_name = STORE_OWNER;
      $from_addr = STORE_OWNER_EMAIL_ADDRESS;
      $to_name = NULL;
      $subject = $body_subject;

      $CLICSHOPPING_Mail->addHtml($email_body);
      $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);

      $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
    $CLICSHOPPING_NavigationHistory = Registry::get('NavigationHistory');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    // redirect the customer to a friendly cookie-must-be-enabled page if cookies are disabled (or the session has not started)
    if (Registry::get('Session')->hasStarted() === false) {
      if (!isset($_GET['cookie_test'])) {
        $all_get = CLICSHOPPING::getAllGET();

        CLICSHOPPING::redirect(null, 'Account&LogIn&' . $all_get . (empty($all_get) ? '' : '&') . 'cookie_test=1');
      }

      CLICSHOPPING::redirect(null, 'Info&Cookies');
    }

    if (isset($_SESSION['login_customer_id'])) {
      $login_customer_id = $_SESSION['login_customer_id'];
    } else {
      $login_customer_id = 0;
    }

    if (is_numeric($login_customer_id) && ($login_customer_id > 0)) {
      if ($login_customer_id > 0) {
        $CLICSHOPPING_Customer->setData($login_customer_id);
      }

      $Qupdate = $CLICSHOPPING_Db->prepare('update :table_customers_info
                                              set customers_info_date_of_last_logon = now(),
                                                  customers_info_number_of_logons = customers_info_number_of_logons+1,
                                                  password_reset_key = null,
                                                  password_reset_date = null
                                              where customers_info_id = :customers_info_id
                                            ');
      $Qupdate->bindInt(':customers_info_id', $login_customer_id);
      $Qupdate->execute();

      self::sentEmail();

    } else {
      $this->sentEmail();
      CLICSHOPPING::redirect(null, 'Account&LogIn');
    }

// restore cart contents
    $CLICSHOPPING_ShoppingCart->getRestoreContents();

    $CLICSHOPPING_NavigationHistory->removeCurrentPage();

    $CLICSHOPPING_Hooks->call('Login', 'Process');

    if ($CLICSHOPPING_NavigationHistory->hasSnapshot()) {
      CLICSHOPPING::redirect(null, 'Account&Main');
    } else {
      CLICSHOPPING::redirect();
    }
  }
}