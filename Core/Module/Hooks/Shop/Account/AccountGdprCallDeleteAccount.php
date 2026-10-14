<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Account;

use ClicShopping\Apps\Customers\Customers\Classes\Shared\AccountDeletionRequest;
use ClicShopping\Apps\Customers\Customers\Classes\Shared\CustomerDataEraser;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

class AccountGdprCallDeleteAccount
{
  /**
   * Two steps, both from the GDPR form. Ticking the box e-mails a confirmation number and deletes
   * nothing; typing that number back erases the account through the single erasure point.
   *
   * The request step does not redirect, so the other GDPR hooks of the same submission still run.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    Registry::get('Language')->loadDefinitions('modules/modules_account_customers/ac_account_customers_gdpr');

    $customers_id = (int)$CLICSHOPPING_Customer->getID();

    if (isset($_POST['delete_account_code']) && $_POST['delete_account_code'] !== '') {
      if (!AccountDeletionRequest::confirm($customers_id, HTML::sanitize($_POST['delete_account_code']))) {
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('module_account_customers_gdpr_delete_code_invalid', ['attempts' => AccountDeletionRequest::MAX_ATTEMPTS]), 'error');

        return;
      }

      // Re-checked: an order may have been placed since the request.
      if ($this->hasOpenOrders($customers_id)) {
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('module_account_customers_gdpr_text_error_delete'), 'error');

        return;
      }

      $this->mail($customers_id, CLICSHOPPING::getDef('module_account_customers_gdpr_email_text_subject'), CLICSHOPPING::getDef('module_account_customers_gdpr_email_text_message'));

      CustomerDataEraser::erase($customers_id);

      $CLICSHOPPING_Customer->reset();
      Registry::get('ShoppingCart')->reset();
      Registry::get('Session')->kill();

      CLICSHOPPING::redirect();
    }

    if (isset($_POST['delete_customers_account_checkbox'])) {
      if ($this->hasOpenOrders($customers_id)) {
        $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('module_account_customers_gdpr_text_error_delete'), 'error');

        return;
      }

      $code = AccountDeletionRequest::arm($customers_id);
      $link = HTTP::getShopUrlDomain() . CLICSHOPPING::getConfig('bootstrap_file') . '?Account&Gdpr';

      $this->mail($customers_id, CLICSHOPPING::getDef('module_account_customers_gdpr_delete_code_email_subject'), CLICSHOPPING::getDef('module_account_customers_gdpr_delete_code_email_message', [
        'code' => $code,
        'minutes' => AccountDeletionRequest::LIFETIME_MINUTES,
        'link' => $link,
      ]));

      $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('module_account_customers_gdpr_delete_code_sent', ['minutes' => AccountDeletionRequest::LIFETIME_MINUTES]), 'success');
    }
  }

  /**
   * An order still counted as a sale and not yet delivered blocks the deletion. Cancelled and
   * refunded orders (revenue_sign <= 0) no longer do; 3 is the platform's delivered status.
   *
   * @param int $customers_id Customer id
   * @return bool
   */
  private function hasOpenOrders(int $customers_id): bool
  {
    $Qcheck = Registry::get('Db')->prepare('select count(*) as count
                                            from :table_orders o
                                            join :table_orders_status os on os.orders_status_id = o.orders_status
                                                                        and os.language_id = :language_id
                                            where o.customers_id = :customers_id
                                            and os.revenue_sign = 1
                                            and o.orders_status <> 3');
    $Qcheck->bindInt(':customers_id', $customers_id);
    $Qcheck->bindInt(':language_id', Registry::get('Language')->getId());
    $Qcheck->execute();

    return $Qcheck->valueInt('count') > 0;
  }

  /**
   * @param int $customers_id Customer id
   * @param string $subject E-mail subject
   * @param string $body HTML body
   * @return void
   */
  private function mail(int $customers_id, string $subject, string $body): void
  {
    $Qcustomer = Registry::get('Db')->get('customers', ['customers_email_address', 'customers_firstname', 'customers_lastname'], ['customers_id' => $customers_id]);

    if ($Qcustomer->fetch() === false) {
      return;
    }

    $CLICSHOPPING_Mail = Registry::get('Mail');
    $to_name = Hash::displayDecryptedDataText($Qcustomer->value('customers_firstname')) . ' ' . Hash::displayDecryptedDataText($Qcustomer->value('customers_lastname'));

    $CLICSHOPPING_Mail->addHtml(html_entity_decode($body));
    $CLICSHOPPING_Mail->send($Qcustomer->value('customers_email_address'), STORE_NAME, STORE_OWNER_EMAIL_ADDRESS, $to_name, $subject);
  }
}
