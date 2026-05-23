<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\ReturnOrders\Sites\Shop\Pages\ProductReturnHistoryInfo\Actions\ProductReturnHistoryInfo;

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
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Mail = Registry::get('Mail');

    if (isset($_POST['action']) && ($_POST['action'] == 'process') && isset($_POST['formid']) && ($_POST['formid'] === $_SESSION['sessiontoken'])) {
      $rId = HTML::sanitize($_GET['rId']);
      $oID = HTML::sanitize($_GET['oId']);
      $comment = HTML::sanitize($_POST['comment']);

      $info_customer = ReturnProduct::getInfoCustomer($oID);

      $Qreturn = $CLICSHOPPING_Db->prepare('select return_id,
                                                     return_status_id
                                              from :table_return_orders_history 
                                              where return_id = :return_id
                                              order by return_status_id asc
                                              limit 1
                                             ');
      $Qreturn->bindInt(':return_id', $rId);

      $Qreturn->execute();

      $return_status_id = $Qreturn->valueInt('return_status_id');

      $sql_data_array = [
        'return_id' => $rId,
        'return_status_id' => $return_status_id,
        'notify' => 0,
        'comment' => $comment,
        'date_added' => 'now()',
      ];

      $CLICSHOPPING_Db->save('return_orders_history', $sql_data_array);
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

      $CLICSHOPPING_Mail->addHtml($email_text_content_customer . '<br />' . $templateEmailSignature . '<br />>' . $templateEmailFooter);
      $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);

      $CLICSHOPPING_Hooks->call('ProductReturnInfoHistory', 'Process');

      $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('success_account_updated'), 'success', 'main');

      CLICSHOPPING::redirect(null, 'Account&Main');
    }
  }
}
