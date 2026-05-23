<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Account;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class AccountGdprCallDeleteAccount
{

  /**
   * Executes the customer account deletion process. Validates the customer’s request to delete their account, performs
   * checks on the conditions for deletion, and removes all associated customer data from the database if applicable.
   * Sends an email notification upon successful deletion and redirects or displays error messages if deletion cannot proceed.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
    $CLICSHOPPING_Mail = Registry::get('Mail');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    if (isset($_POST['delete_customers_account_checkbox'])) {
      $delete_customers_account_checkbox = 1;
    } else {
      $delete_customers_account_checkbox = '0';
    }

    if ($delete_customers_account_checkbox === 1) {
      $process = false;

      $Qcheck = $CLICSHOPPING_Db->prepare('select count(orders_status) as count
                                            from :table_orders
                                            where customers_id = :customers_id
                                            and orders_status <> 3
                                          ');
      $Qcheck->bindValue(':customers_id', $CLICSHOPPING_Customer->getID());
      $Qcheck->execute();

      if ($Qcheck->valueInt('count') != 0) {
        $process = true;
      }

      if ($process === false) {
        $QcustomerEmail = $CLICSHOPPING_Db->prepare('select customers_email_address,
                                                              customers_firstname,
                                                              customers_lastname
                                                       from :table_customers
                                                       where customers_id = :customers_id
                                                      ');
        $QcustomerEmail->bindValue(':customers_id', $CLICSHOPPING_Customer->getID());
        $QcustomerEmail->execute();

        $text_email = html_entity_decode(CLICSHOPPING::getDef('module_account_customers_gdpr_email_text_message')) . "\n";

        $to_addr = $QcustomerEmail->value('customers_email_address');
        $from_name = STORE_NAME;
        $from_addr = STORE_OWNER_EMAIL_ADDRESS;
        $to_name = Hash::displayDecryptedDataText($QcustomerEmail->value('customers_firstname')) . ' ' . Hash::displayDecryptedDataText($QcustomerEmail->value('customers_lastname'));
        $subject = CLICSHOPPING::getDef('email_text_subject');

        $CLICSHOPPING_Mail->addHtml($text_email);
        $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);

        $Qdelete = $CLICSHOPPING_Db->prepare('delete
                                                from :table_customers
                                                where customers_id = :customers_id
                                              ');
        $Qdelete->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
        $Qdelete->execute();

        $Qdelete = $CLICSHOPPING_Db->prepare('delete
                                                from :table_customers_basket
                                                where customers_id = :customers_id
                                              ');
        $Qdelete->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
        $Qdelete->execute();


        $Qdelete = $CLICSHOPPING_Db->prepare('delete
                                            from :table_customers_basket_attributes
                                            where customers_id = :customers_id
                                          ');
        $Qdelete->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
        $Qdelete->execute();


        $Qdelete = $CLICSHOPPING_Db->prepare('delete
                                                from :table_customers_info
                                                where customers_info_id = :customers_id
                                              ');
        $Qdelete->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
        $Qdelete->execute();


        $Qdelete = $CLICSHOPPING_Db->prepare('delete
                                                from :table_address_book
                                                where customers_id = :customers_id
                                              ');
        $Qdelete->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
        $Qdelete->execute();

        $Qdelete = $CLICSHOPPING_Db->prepare('delete
                                                from :table_action_recorder
                                                where user_name = :user_name
                                              ');
        $Qdelete->bindValue(':user_name', $CLICSHOPPING_Customer->getEmailAddress());
        $Qdelete->execute();

        $Qcheck = $CLICSHOPPING_Db->prepare('select reviews_id
                                              from :table_reviews
                                              where customers_id = :customers_id
                                             ');
        $Qcheck->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
        $Qcheck->execute();

        while ($Qcheck->fetch()) {
          $Qdelete = $CLICSHOPPING_Db->prepare('delete
                                                   from :table_reviews
                                                   where customers_id = :customers_id
                                                 ');
          $Qdelete->bindInt(':reviews_id', $Qcheck->valueInt('reviews_id'));
          $Qdelete->execute();

          $Qdelete = $CLICSHOPPING_Db->prepare('delete
                                                   from :table_reviews_description
                                                   where reviews_id = :reviews_id
                                                ');
          $Qdelete->bindInt(':reviews_id', $Qcheck->valueInt('reviews_id'));
          $Qdelete->execute();
        }

        $CLICSHOPPING_Customer->reset();
        $CLICSHOPPING_ShoppingCart->reset();

        CLICSHOPPING::redirect();

      } else {
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('module_account_customers_gdpr_text_error_delete'), 'error');
        CLICSHOPPING::redirect(null, 'Account&Delete');
      }
    }
  }
}
