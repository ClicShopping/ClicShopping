<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Actions\PasswordReset;

use ClicShopping\Apps\Configuration\TemplateEmail\Classes\Shop\TemplateEmail;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use function strlen;

class Process extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Mail = Registry::get('Mail');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (isset($_GET['action']) && ($_GET['action'] == 'process') && isset($_POST['formid']) && ($_POST['formid'] === $_SESSION['sessiontoken'])) {
      $error = false;
      $email_address = HTML::sanitize($_GET['account']);
      $key = HTML::sanitize($_GET['key']);

      $Qcheck = $CLICSHOPPING_Db->prepare('select c.customers_id,
                                                    c.customers_email_address,
                                                    ci.password_reset_key,
                                                    ci.password_reset_date
                                             from :table_customers c,
                                                  :table_customers_info ci
                                             where c.customers_email_address = :customers_email_address
                                             and c.customers_id = ci.customers_info_id
                                             and c.customer_guest_account = 0
                                             limit 1
                                           ');

      $Qcheck->bindValue(':customers_email_address', $email_address);
      $Qcheck->execute();

      $password_new = HTML::sanitize($_POST['password']);
      $password_confirmation = HTML::sanitize($_POST['confirmation']);

      if ((strlen($password_new) < ENTRY_PASSWORD_MIN_LENGTH) && !isset($key)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_password_new_error', ['min_length' => ENTRY_PASSWORD_MIN_LENGTH]), 'error');

      } elseif (($password_new != $password_confirmation) && !isset($key)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_password_new_error_not_matching'), 'error');
      }

      if ($error === false) {
        $CLICSHOPPING_Db->save('customers', ['customers_password' => Hash::encrypt($password_new)], ['customers_id' => (int)$Qcheck->valueInt('customers_id')]);

        $sql_array = [
          'customers_info_date_account_last_modified' => 'now()',
          'password_reset_key' => 'null',
          'password_reset_date' => 'null'
        ];

        $CLICSHOPPING_Db->save('customers_info', $sql_array, ['customers_info_id' => (int)$Qcheck->valueInt('customers_id')]);

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('success_password_reset'), 'success');

        $email_text_body = ' <br />' . TemplateEmail::getTemplateEmailTextFooter();
        $email_text_body .= ' <br />' . TemplateEmail::getTemplateEmailSignature();

        $to_addr = $email_address;
        $from_name = STORE_NAME;
        $from_addr = STORE_OWNER_EMAIL_ADDRESS;
        $to_name =  Hash::displayDecryptedDataText($Qcheck->value('customers_firstname')) . ' ' .  Hash::displayDecryptedDataText($Qcheck->value('customers_lastname'));
        $subject = CLICSHOPPING::getDef('text_email_subject', ['store_name' => STORE_NAME]);

        $CLICSHOPPING_Mail->addHtml(CLICSHOPPING::getDef('text_email_body', ['store_name' => STORE_NAME]) . $email_text_body);
        $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);

        $CLICSHOPPING_Customer->reset();

        $CLICSHOPPING_Hooks->call('PasswordReset', 'Process');

        CLICSHOPPING::redirect(null, 'Account&LogIn');
      }
    }
  }
}