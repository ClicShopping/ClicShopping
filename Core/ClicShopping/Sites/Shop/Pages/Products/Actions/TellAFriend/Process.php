<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Products\Actions\TellAFriend;

use ClicShopping\Apps\Configuration\TemplateEmail\Classes\Shop\TemplateEmail;
use ClicShopping\Apps\Tools\ActionsRecorder\Classes\Shop\ActionRecorder;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Is;
use ClicShopping\OM\Registry;
use function defined;

class Process extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Mail = Registry::get('Mail');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    if (isset($_POST['action']) && ($_POST['action'] == 'process') && isset($_POST['formid']) && ($_POST['formid'] === $_SESSION['sessiontoken'])) {
      $error = false;

      $CLICSHOPPING_Hooks->call('TellAFriend', 'PreAction');

      $to_email_address = HTML::sanitize($_POST['to_email_address']);
      $to_name = HTML::sanitize($_POST['to_name']);
      $from_email_address = HTML::sanitize($_POST['from_email_address']);
      $from_name = HTML::sanitize($_POST['from_name']);
      $message = HTML::sanitize($_POST['message']);

      $customer_agree_privacy = HTML::sanitize($_POST['customer_agree_privacy']);

      if (DISPLAY_PRIVACY_CONDITIONS == 'true') {
        if ($customer_agree_privacy != 'on') {
          $error = true;

          $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('entry_agreement_check_error'), 'error', 'friend');
        }
      }

      if (empty($from_name)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_from_name'), 'error', 'friend');
      }

      if (Is::EmailAddress($from_email_address) === false) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_from_address'), 'error', 'friend');
      }

      if (empty($to_name)) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_to_name'), 'error', 'friend');
      }

      if (Is::EmailAddress($to_email_address) === false) {
        $error = true;

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_to_address'), 'error', 'friend');
      }

      Registry::set('ActionRecorder', new ActionRecorder('ar_tell_a_friend', ($CLICSHOPPING_Customer->isLoggedOn() ? $CLICSHOPPING_Customer->getID() : null), $from_name));
      $CLICSHOPPING_ActionRecorder = Registry::get('ActionRecorder');

      if (!$CLICSHOPPING_ActionRecorder->canPerform()) {
        $error = true;

        $CLICSHOPPING_ActionRecorder->record(false);

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_action_recorder', ['module_action_recorder_tell_a_friend_email_minutes' => (defined('MODULE_ACTION_RECORDER_TELL_A_FRIEND_EMAIL_MINUTES') ? (int)MODULE_ACTION_RECORDER_TELL_A_FRIEND_EMAIL_MINUTES : 15)]), 'danger', 'friend');
      }

      if (Is::EmailAddress($to_email_address) === false) {
        $error = true;
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_to_address'), 'error', 'friend');
      }

      if ($error === false) {
        $email_subject = CLICSHOPPING::getDef('text_email_subject', ['from_name' => $from_name, 'store_name' => STORE_NAME]);
        $email_body = CLICSHOPPING::getDef('text_email_intro', ['to_name' => $to_name, 'from_name' => $from_name, 'products_name' => $CLICSHOPPING_ProductsCommon->getProductsName(), 'store_name' => STORE_NAME]) . "\n\n";

        if (!empty($message)) {
          $email_body .= $message . "\n\n";
        }

        $email_body .= CLICSHOPPING::getDef('text_email_link', ['url_product' => CLICSHOPPING::link(null, 'products&Product&products_id=' . (int)$CLICSHOPPING_ProductsCommon->getID())]) . "\n\n";
        $email_body .= CLICSHOPPING::getDef('text_email_signature', ['store_name' => STORE_NAME . "\n" . HTTP::getShopUrlDomain() . "\n", 'store_owner_email_address' => STORE_OWNER_EMAIL_ADDRESS]);

        $email_body .= TemplateEmail::getTemplateEmailSignature();

        $to_addr = $to_email_address;
        $from_addr = $from_email_address;
        $subject = $email_subject;

        $CLICSHOPPING_Mail->addHtml($email_body);
        $CLICSHOPPING_Mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);

        $CLICSHOPPING_ActionRecorder->record();

        $CLICSHOPPING_Hooks->call('TellAFriend', 'Process');

        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('text_email_successful_sent', ['products_name' => $CLICSHOPPING_ProductsCommon->getProductsName(), 'to_name' => HTML::outputProtected($to_name)]), 'success', 'header');

        CLICSHOPPING::redirect(null, 'Products&Description&products_id=' . (int)$CLICSHOPPING_ProductsCommon->getID());
      }
    }
  }
}