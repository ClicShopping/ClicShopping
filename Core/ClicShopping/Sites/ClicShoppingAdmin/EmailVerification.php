<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\ClicShoppingAdmin;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\Apps\Configuration\TemplateEmail\Classes\ClicShoppingAdmin\TemplateEmailAdmin;

class EmailVerification
{

/**
* Checks if a user exists and if email verification is enabled
*
* @param string $username Username to check
* @return array|bool User details or false if not found
*/
  public static function checkuser(string $username): array|bool
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->prepare('select user_name,
                                                email_verification_code,
                                                email_verification_expiry
                                         from :table_administrators 
                                         where user_name = :user_name 
                                         and status = 1
                                         and email_verification = 1
                                        ');
    $Qcheck->bindValue(':user_name', $username);
    $Qcheck->execute();

    if (!empty($Qcheck->value('user_name'))) {
      $array = [
        'user_name' => $Qcheck->value('user_name'),
        'email_verification_code' => $Qcheck->value('email_verification_code'),
        'email_verification_expiry' => $Qcheck->value('email_verification_expiry')
      ];

      return $array;
    }

    return false;
  }

/**
 * Checks if email verification is globally enabled
 *
 * @return bool
 */
public static function isEnabled(): bool
{
  return defined('EMAIL_VERIFICATION_ENABLED_ADMIN') && EMAIL_VERIFICATION_ENABLED_ADMIN === 'True';
}

/**
 * Checks if email verification is enabled for a specific administrator
 *
 * @param string $username Administrator's username
 * @return bool
 */
public static function isEnabledForAdmin(string $username): bool
  {
    if (!self::isEnabled()) {
      return false;
    }

    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->prepare('select email_verification 
                                         from :table_administrators 
                                         where user_name = :user_name 
                                         and status = 1
                                        ');
    $Qcheck->bindValue(':user_name', $username);
    $Qcheck->execute();

    if ($Qcheck->fetch() !== false) {
      return true;
    }

    return false;
  }

/**
* Generates a verification code and sends it via email
*
* @param string $username Administrator's username
* @return bool Success or failure of the sending process
*/
  public static function sendVerificationCode(string $username): bool
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Mail = Registry::get('Mail');

    $code_length = defined('EMAIL_VERIFICATION_CODE_LENGTH') ? (int)EMAIL_VERIFICATION_CODE_LENGTH : 6;
    $code_length = max(4, min(8, $code_length)); // Limiter entre 4 et 8

    $verification_code = HTML::generateRandomNumber();

    $expiry_minutes = defined('EMAIL_VERIFICATION_CODE_EXPIRY') ? (int)EMAIL_VERIFICATION_CODE_EXPIRY : 15;
    $expiry_time = date('Y-m-d H:i:s', time() + ($expiry_minutes * 60));

    $update_array = [
      'email_verification_code' => $verification_code,
      'email_verification_expiry' => $expiry_time
    ];

    $CLICSHOPPING_Db->save('administrators', $update_array, ['user_name' => $username]);

    $body_subject = CLICSHOPPING::getDef('email_verification_subject', ['store_name' => STORE_NAME]);

    $text_array = [
      'store_name' => STORE_NAME,
      'verification_code' => $verification_code,
      'expiry_minutes' => $expiry_minutes,
      'remote_address' => $_SERVER['REMOTE_ADDR']
    ];

    $email_body = CLICSHOPPING::getDef('email_verification_body', $text_array) . "\n";
    $email_body .= TemplateEmailAdmin::getTemplateEmailSignature() . "\n";
    $email_body .= TemplateEmailAdmin::getTemplateEmailTextFooter();

    $to_addr = $username;
    $from_name = STORE_OWNER;
    $from_addr = STORE_OWNER_EMAIL_ADDRESS;
    $to_name = null;
    $subject = $body_subject;

    $CLICSHOPPING_Mail->addHtml($email_body);

    return $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);
  }

 /**
   * Verifies if a verification code is valid
   *
   * @param string $username Administrator's username
   * @param string $code Verification code to check
   * @return bool
   */
  public static function verifyCode(string $username, string $code): bool
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $admin_check = self::checkuser($username);

    if (!empty($admin_check['user_name'])) {
      $stored_code = $admin_check['email_verification_code'];
      $expiry_time = $admin_check['email_verification_expiry'];

      // Vérifier si le code est valide et n'a pas expiré
      if ($stored_code === $code && strtotime($expiry_time) - time() > 0) {
        $update_array = [
          'email_verification_code' => null,
          'email_verification_expiry' => null
        ];

        $CLICSHOPPING_Db->save('administrators', $update_array, ['user_name' => $username]);
        return true;
      }
    }

    return false;
  }
}
