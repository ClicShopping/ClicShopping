<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Actions\PasswordForgotten;

use ClicShopping\Apps\Configuration\TemplateEmail\Classes\Shop\TemplateEmail;
use ClicShopping\Apps\Tools\ActionsRecorder\Classes\Shop\ActionRecorder;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Is;
use ClicShopping\OM\Registry;
use function defined;

class Process extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Mail = Registry::get('Mail');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    if (isset($_GET['action']) && ($_GET['action'] == 'process') && isset($_POST['formid']) && ($_POST['formid'] === $_SESSION['sessiontoken'])) {
      $email_address = HTML::sanitize($_POST['email_address']);

      if (Is::EmailAddress($email_address) === true && !empty($email_address)) {
        $Qcheck = $CLICSHOPPING_Db->prepare('select customers_id,
                                                      customers_firstname,
                                                      customers_lastname,
                                                      member_level
                                                from :table_customers
                                                where customers_email_address = :customers_email_address
                                                and customer_guest_account = 0
                                                limit 1
                                              ');

        $Qcheck->bindValue(':customers_email_address', $email_address);
        $Qcheck->execute();

        if ($Qcheck->fetch() !== false) {
          if ($Qcheck->valueInt('member_level') == 1) {
            Registry::set('ActionRecorder', new ActionRecorder('ar_reset_password', $Qcheck->valueInt('customers_id'), $email_address));
            $CLICSHOPPING_ActionRecorder = Registry::get('ActionRecorder');

            if ($CLICSHOPPING_ActionRecorder->canPerform()) {
              $CLICSHOPPING_ActionRecorder->record();

              $reset_key = Hash::getRandomString(40);

              $CLICSHOPPING_Db->save('customers_info',
                ['password_reset_key' => $reset_key, 'password_reset_date' => 'now()'],
                ['customers_info_id' => $Qcheck->valueInt('customers_id')]
              );

              $reset_key_url = CLICSHOPPING::link(null, 'Account&PasswordReset&account=' . urlencode($email_address) . '&key=' . $reset_key);

              if (str_contains($reset_key_url, '&amp;')) {
                $reset_key_url = str_replace('&amp;', '&', $reset_key_url);
              }

              $array = ['store_name' => STORE_NAME,
                'store_owner_email_address' => STORE_OWNER_EMAIL_ADDRESS,
                'reset_url' => $reset_key_url
              ];

              $message = CLICSHOPPING::getDef('email_password_reset_body', $array);

              $email_password_reminder_body = $message . '</ br>';
              $email_password_reminder_body .= TemplateEmail::getTemplateEmailTextFooter() . '</ br>';
              $email_password_reminder_body .= TemplateEmail::getTemplateEmailSignature();

              $email_subject = CLICSHOPPING::getDef('email_password_reset_subject', ['store_name' => STORE_NAME]);

              $to_addr = $email_address;
              $from_name = STORE_NAME;
              $from_addr = STORE_OWNER_EMAIL_ADDRESS;
              $to_name = Hash::displayDecryptedDataText($Qcheck->value('customers_firstname')) . ' ' . Hash::displayDecryptedDataText($Qcheck->value('customers_lastname'));
              $subject = $email_subject;

              $CLICSHOPPING_Mail->addHtml($email_password_reminder_body);
              $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);

              $password_reset_initiated = true;
            } else {
              $password_reset_initiated = false;

              $CLICSHOPPING_ActionRecorder->record(false);

              $message_array = ['module_action_recorder_reset_password_minutes' => (defined('MODULE_ACTION_RECORDER_RESET_PASSWORD_MINUTES') ? (int)MODULE_ACTION_RECORDER_RESET_PASSWORD_MINUTES : 5)];

              $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_action_recorder', $message_array), 'error');
            }

            $CLICSHOPPING_Hooks->call('PasswordForgotten', 'Process');

            CLICSHOPPING::redirect(null, 'Account&PasswordForgotten&Success&reset=' . $password_reset_initiated);
          } else {
            $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('text_no_email_address_found'), 'error');
          }
        } else {
          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('text_no_email_address_found'), 'error');
        }
      }
    }
  }
}