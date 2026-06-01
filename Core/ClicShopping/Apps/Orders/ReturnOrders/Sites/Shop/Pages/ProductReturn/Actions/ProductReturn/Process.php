<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\ReturnOrders\Sites\Shop\Pages\ProductReturn\Actions\ProductReturn;

use ClicShopping\Apps\Configuration\TemplateEmail\Classes\ClicShoppingAdmin\TemplateEmailAdmin;
use ClicShopping\Apps\Orders\ReturnOrders\Classes\Shop\ReturnProduct;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Process extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Mail = Registry::get('Mail');

    if (isset($_POST['action']) && ($_POST['action'] == 'process') && isset($_POST['formid']) && ($_POST['formid'] === $_SESSION['sessiontoken'])) {
      if (!isset($_GET['order_id'], $_GET['product_id']) || !is_numeric($_GET['order_id']) || !is_numeric($_GET['product_id'])) {
        CLICSHOPPING::redirect(null, 'Account&History');
      }

      $order_id = (int)HTML::sanitize($_GET['order_id']);
      $products_id = (int)HTML::sanitize($_GET['product_id']);
      $info_customer = ReturnProduct::getInfoCustomer($order_id);

      // Access control: the order must belong to the logged-in customer (prevents IDOR
      // where a customer files a return against someone else's order and leaks their PII).
      if ($info_customer === false || (int)$info_customer['customers_id'] !== (int)$CLICSHOPPING_Customer->getID()) {
        CLICSHOPPING::redirect(null, 'Account&History');
      }

      $product_quantity = HTML::sanitize($_POST['product_quantity'] ?? '');
      $return_reason_id = HTML::sanitize($_POST['return_reason'] ?? '');
      $comment = HTML::sanitize($_POST['comment'] ?? '');
      $return_reason_opened = HTML::sanitize($_POST['return_reason_opened'] ?? '');
      $products_model = $CLICSHOPPING_ProductsCommon->getProductsModel($products_id);

      $sql_data_array = [
        'return_ref' => 'RMA/' . $products_model . '/' . $order_id,
        'order_id' => (int)$order_id,
        'product_id' => (int)$products_id,
        'customer_id' => $CLICSHOPPING_Customer->getID(),
        'customer_firstname' => $CLICSHOPPING_Customer->getFirstName(),
        'customer_lastname' => $CLICSHOPPING_Customer->getLastName(),
        'customer_telephone' => $info_customer['customers_telephone'],
        'customer_email' => $info_customer['customers_email_address'],
        'product_name' => $CLICSHOPPING_ProductsCommon->getProductsName($products_id),
        'product_model' => $CLICSHOPPING_ProductsCommon->getProductsModel($products_id),
        'quantity' => (int)$product_quantity,
        'opened' => (int)$return_reason_opened,
        'return_reason_id' => (int)$return_reason_id,
        'return_action_id' => 1,
        'comment' => $comment,
        'date_added' => 'now()',
        'date_ordered' => $info_customer['date_purchased']
      ];

      $CLICSHOPPING_Db->save('return_orders', $sql_data_array);

//mail
      $templateEmailSignature = TemplateEmailAdmin::getTemplateEmailSignature();
      $templateEmailFooter = TemplateEmailAdmin::getTemplateEmailTextFooter();

      $email_text_subject = stripslashes(CLICSHOPPING::getDef('email_text_subject', ['store_name' => STORE_NAME]));
      $email_text_content = stripslashes(CLICSHOPPING::getDef('email_text_content'));

      $to_addr = $info_customer['customers_email_address'];
      $from_name = STORE_NAME;
      $from_addr = STORE_OWNER_EMAIL_ADDRESS;
      $to_name = STORE_OWNER_EMAIL_ADDRESS;
      $subject = $email_text_subject;

      $CLICSHOPPING_Mail->addHtml($email_text_content);
      $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);

      $email_text_subject_customer = stripslashes(CLICSHOPPING::getDef('email_text_subject_customer', ['store_name' => STORE_NAME]));
      $email_text_content_customer = stripslashes(CLICSHOPPING::getDef('email_text_content'));

      $to_addr = STORE_OWNER_EMAIL_ADDRESS;
      $from_name = STORE_NAME;
      $from_addr = STORE_OWNER_EMAIL_ADDRESS;
      $to_name = $CLICSHOPPING_Customer->getName();
      $subject = $email_text_subject_customer;

      $CLICSHOPPING_Mail->addHtml($email_text_content_customer . '<br />' . $templateEmailSignature . '<br />' . $templateEmailFooter);
      $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);

      $CLICSHOPPING_Hooks->call('ProductReturn', 'Process');

      $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('success_account_updated'), 'success', 'main');

      CLICSHOPPING::redirect(null, 'Account&Main');
    }
  }
}