<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Actions;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\EmailVerification;

class LogInAuth extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Language = Registry::get('Language');

    $this->page->setFile('login_auth.php');

    if (defined('EMAIL_VERIFICATION_ENABLED_SHOP') && EMAIL_VERIFICATION_ENABLED_SHOP == 'False') {
      CLICSHOPPING::redirect('Account&LogIn');
    }

    if (!isset($_SESSION['email_address'], $_SESSION['login_auth_customer_id'])) {
      unset($_SESSION['email_address'], $_SESSION['login_auth_customer_id']);

      CLICSHOPPING::redirect('Account&LogIn');
    } else {
      $email_address = $_SESSION['email_address'];
    }

    // redirect the customer to a friendly cookie-must-be-enabled page if cookies are disabled (or the session has not started)
    if (Registry::get('Session')->hasStarted() === false) {
      if (!isset($_GET['cookie_test'])) {
        $all_get = CLICSHOPPING::getAllGET([
          'Account',
          'LogInAuth',
          'Process'
        ]);

        CLICSHOPPING::redirect(null, 'Account&LogInAuth&' . $all_get . (empty($all_get) ? '' : '&') . 'cookie_test=1');
      }

      CLICSHOPPING::redirect(null, 'Info&Cookies');
    }

    $CLICSHOPPING_Language->loadDefinitions('login_auth');
// Check if email exists
      $array_sql = [
        'customers_id',
        'customers_password'
      ];

    $Qcheck = $CLICSHOPPING_Db->get('customers', $array_sql, ['customers_email_address' => $email_address], null, 1);

// login content module must return $login_customer_id as an integer after successful customer authentication
    $_SESSION['login_customer_id'] = false;
    $error = false;

    if ($Qcheck->fetch() === false) {
      $error = true;
    } else {
      if ($Qcheck->valueInt('customers_id') !== (int)$_SESSION['login_auth_customer_id']) {
        $error = true;
      } else {
        $_SESSION['customer_id'] = $Qcheck->valueInt('customers_id');
        $error = false;
      }
    }

    if ($error === true) {
      $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('text_login_error'), 'error');

      CLICSHOPPING::redirect(null, 'Account&LogIn');
    }

    // The code itself is verified by LogInAuth/Process, on the request that opens the session.
    if (isset($_GET['action']) && $_GET['action'] == 'resend') {
      if (EmailVerification::sendVerificationCode($email_address)) {
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('success_email_verification_code_sent'), 'success');
      } else {
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_email_verification_failed'), 'error');
      }
    } else if (isset($_GET['action']) && $_GET['action'] == 'logoff') {
      unset($_SESSION['email_code']);
      CLICSHOPPING::redirect(null, 'Account&LogOff');
    } else {
      // first visit send email
      if (!isset($_SESSION['email_code']) || $_SESSION['email_code'] !== true) {
        if (EmailVerification::sendVerificationCode($email_address)) {
          $_SESSION['email_code'] = true;
        } else {
          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_email_verification_failed'), 'error');
          CLICSHOPPING::redirect(null, 'Account&LogIn');
        }
      }
    }

    $this->page->data['content'] = $CLICSHOPPING_Template->getTemplateFiles('login_auth');

    $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('navbar_title'), CLICSHOPPING::link(null, 'Account&LogInAuth'));
  }
}