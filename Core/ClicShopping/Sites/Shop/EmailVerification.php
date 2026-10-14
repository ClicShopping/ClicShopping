<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\Apps\Configuration\TemplateEmail\Classes\Shop\TemplateEmail;

/**
 * Email Verification Handler
 *
 * Manages email verification for customers including:
 * - Code generation and validation
 * - Email sending with verification codes
 * - Status checking and verification state management
*/

 class EmailVerification
{
/**
 * Checks if email verification is globally enabled
 *
 * @return bool
 */
  public static function isEnabled(): bool
  {
    return defined('EMAIL_VERIFICATION_ENABLED_SHOP') && EMAIL_VERIFICATION_ENABLED_SHOP == 'True';
  }

   /**
    * Checks if email verification is enabled for a specific administrator
    *
    * @param string $email_address
    * @return bool
    */
  public static function isEnabledForCustomer(string $email_address): bool
  {
    if (!self::isEnabled()) {
      return false;
    }

    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->prepare('select email_verification 
                                         from :table_customers 
                                         where customers_email_address = :customers_email_address
                                        ');
    $Qcheck->bindValue(':customers_email_address', $email_address);
    $Qcheck->execute();

    if ($Qcheck->fetch() !== false && $Qcheck->valueInt('email_verification') === 1) {
      return true;
    }

    return false;
  }

   /**
    * Generates a verification code and sends it via email
    *
    * @param string $email_address
    * @return bool Success or failure of the sending process
    */
  public static function sendVerificationCode(string $email_address): bool
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Mail = Registry::get('Mail');

    $code_length = defined('EMAIL_VERIFICATION_CODE_LENGTH') ? (int)EMAIL_VERIFICATION_CODE_LENGTH : 6;
    $verification_code = HTML::generateRandomNumber($code_length);

    $expiry_minutes = defined('EMAIL_VERIFICATION_CODE_EXPIRY') ? (int)EMAIL_VERIFICATION_CODE_EXPIRY : 15;
    $expiry_time = date('Y-m-d H:i:s', time() + ($expiry_minutes * 60));

    $update_array = [
      'email_verification_code' => $verification_code,
      'email_verification_expiry' => $expiry_time
    ];

    $CLICSHOPPING_Db->save('customers', $update_array, ['customers_email_address' => $email_address]);

    $body_subject = CLICSHOPPING::getDef('email_verification_subject', ['store_name' => \defined('STORE_NAME') ? STORE_NAME : '']);

    $text_array = [
      'store_name' => \defined('STORE_NAME') ? STORE_NAME : '',
      'verification_code' => $verification_code,
      'expiry_minutes' => $expiry_minutes,
      'remote_address' => $_SERVER['REMOTE_ADDR']
    ];

    $email_body = CLICSHOPPING::getDef('email_verification_body', $text_array) . "\n";
    $email_body .= TemplateEmail::getTemplateEmailSignature() . "\n";
    $email_body .= TemplateEmail::getTemplateEmailTextFooter();

    $to_addr = $email_address;
    $from_name = \defined('STORE_OWNER') ? STORE_OWNER : '';
    $from_addr = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
    $to_name = null;
    $subject = $body_subject;

    $CLICSHOPPING_Mail->addHtml($email_body);

    return $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);
  }

   /**
    * Verifies if a verification code is valid
    *
    * @param string $email_address
    * @param string $code Verification code to check
    * @return bool
    */
  public static function verifyCode(string $email_address, string $code): bool
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->prepare('select email_verification_code, 
                                                email_verification_expiry 
                                         from :table_customers 
                                         where customers_email_address = :customers_email_address
                                        ');
    $Qcheck->bindValue(':customers_email_address', $email_address);
    $Qcheck->execute();

    if ($Qcheck->fetch() !== false) {
      $stored_code = $Qcheck->value('email_verification_code');
      $expiry_time = $Qcheck->value('email_verification_expiry');

      // Vérifier si le code est valide et n'a pas expiré
      if ($stored_code === $code && strtotime($expiry_time) - time() > 0) {
        $update_array = [
          'email_verification_code' => null,
          'email_verification_expiry' => null
        ];

        $CLICSHOPPING_Db->save('customers', $update_array, ['customers_email_address' => $email_address]);
        return true;
      }
    }

    return false;
  }
}
