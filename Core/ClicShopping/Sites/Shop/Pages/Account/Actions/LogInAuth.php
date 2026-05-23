<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Actions;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
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

    if (!isset($_SESSION['email_address']) || !isset($_SESSION['password'])) {
      unset($_SESSION['email_address']);
      unset($_SESSION['password']);

      CLICSHOPPING::redirect('Account&LogIn');
    } else {
      $email_address = $_SESSION['email_address'];
      $password = $_SESSION['password'];
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

      CLICSHOPPING::redirect(null, 'Info&CookieUsage');
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
      if (!Hash::verify($password, $Qcheck->value('customers_password'))) {
        $error = true;
      } else {
        $_SESSION['customer_id'] = $Qcheck->valueInt('customers_id');
        $error = false;
      }
    }

    if ($error === true && $_SESSION['login_customer_id'] === false) {
      $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('text_login_error'), 'error');

      CLICSHOPPING::redirect(null, 'Account&LogIn');
    }

    // activate the login session or not
    if (isset($_POST['action']) && $_POST['action'] == 'process') {
      if (isset($_POST['email_code'])) {
        $email_code = HTML::sanitize($_POST['email_code']);
        $check = EmailVerification::verifyCode($email_address, $email_code);

        if ($check === true) {
          $array_sql = [
            'customers_id',
            'customers_password',
          ];

          $Qcheck = $CLICSHOPPING_Db->get('customers', $array_sql, ['customers_email_address' => $email_address], null, 1);

          if ($Qcheck->fetch()) {
            CLICSHOPPING::redirect(null, 'Account&LogInAuth&Process');
          }
        } else {
          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('text_email_code_invalid'), 'error');
        }
      } else {
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('text_email_code_required'), 'error');
        }
    } elseif (isset($_GET['action']) && $_GET['action'] == 'resend') {
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