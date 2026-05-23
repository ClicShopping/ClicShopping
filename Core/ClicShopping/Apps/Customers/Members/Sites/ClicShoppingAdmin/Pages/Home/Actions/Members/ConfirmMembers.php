<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Customers\Members\Sites\ClicShoppingAdmin\Pages\Home\Actions\Members;

use ClicShopping\Apps\Configuration\TemplateEmail\Classes\ClicShoppingAdmin\TemplateEmailAdmin;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

class ConfirmMembers extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Members = Registry::get('Members');
    $CLICSHOPPING_Mail = Registry::get('Mail');

// insert by default in the first group
    $customers_group_id = 1;

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_GET['cID'])) $customers_id = HTML::sanitize($_GET['cID']);

    $QdefaultCustomerGroup = $CLICSHOPPING_Members->db->prepare('select customers_group_id,
                                                                           group_order_taxe,
                                                                           group_payment_unallowed,
                                                                           group_shipping_unallowed
                                                                     from :table_customers_groups
                                                                     where customers_group_id = :customers_group_id
                                                                    ');
    $QdefaultCustomerGroup->bindInt(':customers_group_id', $customers_group_id);
    $QdefaultCustomerGroup->execute();

    $sql_data_array = [
      'member_level' => '1',
      'customers_group_id' => (int)$customers_group_id,
      'customers_options_order_taxe' => $QdefaultCustomerGroup->valueInt('group_order_taxe')
    ];

    $CLICSHOPPING_Members->db->save('customers', $sql_data_array, ['customers_id' => (int)$customers_id]);

    $QcheckCustomer = $CLICSHOPPING_Members->db->prepare('select customers_id,
                                                                   customers_firstname,
                                                                   customers_lastname,
                                                                   customers_password,
                                                                   customers_email_address
                                                             from :table_customers
                                                             where customers_id = :customers_id
                                                            ');
    $QcheckCustomer->bindInt(':customers_id', $customers_id);
    $QcheckCustomer->execute();

// Cryptage du mot de passe
    $newpass = Hash::getRandomString(ENTRY_PASSWORD_MIN_LENGTH);

    $crypted_password = Hash::encrypt($newpass);

    $Qupdate = $CLICSHOPPING_Members->db->prepare('update :table_customers
                                                     set customers_password = :customers_password
                                                     where customers_id = :customers_id
                                                   ');

    $Qupdate->bindValue(':customers_password', $crypted_password);
    $Qupdate->bindInt(':customers_id', (int)$QcheckCustomer->valueInt('customers_id'));

    $Qupdate->execute();

    if (!empty(COUPON_CUSTOMER_B2B)) {
      $email_coupon = $CLICSHOPPING_Members->getDef('email_text_coupon') . ' ' . COUPON_CUSTOMER_B2B;
      $email_coupon = html_entity_decode(Hash::displayDecryptedDataText($email_coupon));
    } else {
      $email_coupon = '';
    }

    $text_password_body = html_entity_decode($CLICSHOPPING_Members->getDef('email_password_reminder_body', [
        'store_name' => STORE_NAME,
        'store_owner_email_address' => STORE_OWNER_EMAIL_ADDRESS,
        'url' => HTTP::getShopUrlDomain(),
        'password' => $newpass,
        'username' => $QcheckCustomer->value('customers_email_address')
      ]
    )
    );

    $email_text_subject = html_entity_decode($CLICSHOPPING_Members->getDef('email_text_subject', ['store_name' => STORE_NAME]));
    $email_text_confirm = html_entity_decode($CLICSHOPPING_Members->getDef('email_text_confirm',
      ['store_name' => STORE_NAME,
        'store_name_address' => STORE_NAME_ADDRESS,
        'store_ownler_email_address' => STORE_OWNER_EMAIL_ADDRESS
      ]
    )
    );

    $email_signature = TemplateEmailAdmin::getTemplateEmailSignature();
    $email_warning = TemplateEmailAdmin::getTemplateEmailTextFooter();
    $email_text = $email_text_confirm . $email_coupon . $email_signature . $email_warning;

// mails avec le mot de passe
    $to_addr = $QcheckCustomer->value('customers_email_address');
    $from_name = STORE_NAME;
    $from_addr = STORE_OWNER_EMAIL_ADDRESS;
    $to_name = Hash::displayDecryptedDataText($QcheckCustomer->value('customers_firstname'));
    $subject = STORE_NAME;

    $CLICSHOPPING_Mail->addHtml($email_text);
    $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);

    $to_addr = $QcheckCustomer->value('customers_email_address');
    $from_name = STORE_NAME;
    $from_addr = STORE_OWNER_EMAIL_ADDRESS;
    $to_name = Hash::displayDecryptedDataText($QcheckCustomer->value('customers_firstname')) . ' ' . Hash::displayDecryptedDataText($QcheckCustomer->value('customers_lastname'));
    $subject = $email_text_subject;

    $CLICSHOPPING_Mail->addHtml('<br />' . nl2br(sprintf($text_password_body, $QcheckCustomer->value('customers_email_address'), $newpass)));
    $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);

    $CLICSHOPPING_Members->redirect('Members&page=' . $page);
  }
}