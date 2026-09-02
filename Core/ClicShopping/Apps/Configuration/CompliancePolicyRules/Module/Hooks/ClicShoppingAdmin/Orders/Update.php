<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\Hooks\ClicShoppingAdmin\Orders;

use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\Apps\Orders\Orders\Classes\ClicShoppingAdmin\OrderAdmin;
use ClicShopping\Apps\Configuration\CompliancePolicyRules\Classes\ClicShoppingAdmin;
use ClicShopping\Apps\Configuration\CompliancePolicyRules\CompliancePolicyRules as CompliancePolicyRulesApp;

class Update implements HooksInterface
{
  private int $orderId;
  private mixed $app;
  private int $status;
  private int $statusInvoice;

  public function __construct()
  {
    $this->orderId = HTML::sanitize($_GET['oID']);
    $this->app = new CompliancePolicyRulesApp();
    $this->status = isset($_POST['status']) ? (int)HTML::sanitize($_POST['status']) : 0;
    $this->statusInvoice = isset($_POST['status_invoice']) ? (int)HTML::sanitize($_POST['status_invoice']) : 0;

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Orders/order');
  }

  /**
   * Triggers the Chorus Pro electronic invoice submission when conditions are met.
   *
   * Conditions required (all must be true):
   *   1. CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_FRE_CHORUS_PRO_ENABLED = True
   *   2. The admin has checked the "E-Invoice" slider (notify_einvoice POST field is set)
   *   3. The invoice status has actually changed to a new value
   *   4. The new invoice status is actionable: STATUS_INVOICE(2), STATUS_CANCEL(3), STATUS_CREDIT_NOTE(4)
   *
   * Instantiates a full OrderAdmin object to get all required order data,
   * then delegates to EInvoiceService::process().
   *
   * @param array $check  Current order data from getCheckStatus()
   */
  private function processChorusPro(array $check): void
  {
    $eInvoice = new EInvoiceService();

    if (!$eInvoice->isEnabled()) {
      return;
    }

    // Only act if the admin explicitly enabled the e-invoice slider
    if (!isset($_POST['notify_einvoice'])) {
      return;
    }

    $new_invoice_status = $this->statusInvoice;

    // Do not re-process if the invoice status has not changed
    if ((int)$check['orders_status_invoice'] === $new_invoice_status) {
      return;
    }

    // Only process actionable statuses
    if (!in_array($new_invoice_status, [
      EInvoiceService::STATUS_INVOICE,
      EInvoiceService::STATUS_CANCEL,
      EInvoiceService::STATUS_CREDIT_NOTE,
    ])) {
      return;
    }

    $order = new OrderAdmin((int)$this->orderId);

    $eInvoice->process(
      (int)$this->orderId,
      $order->customer,
      $order->info,
      $order->products,
      $order->totals,
      $new_invoice_status
    );
  }

  /**
   * Displays a MessageStack warning when the order is in a paid/confirmed status
   * but the invoice has not yet been issued (orders_status_invoice != STATUS_INVOICE).
   *
   * This reminds the administrator to:
   *   1. Regenerate and send the PDF invoice to the customer
   *   2. Transmit the electronic invoice to Chorus Pro via the status tab
   *
   * Note: $paid_order_status = 3 corresponds to the "paid/confirmed" order status
   * in the default ClicShopping configuration. Adjust this value if your shop uses
   * a different status ID for confirmed/paid orders.
   *
   * @param array $check  Current order data from getCheckStatus()
   */
  private function checkInvoiceAlert(array $check): void
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    // Status 3 = paid/confirmed order — adjust to match your configuration
    $paid_order_status = 3;

    if ($this->status === $paid_order_status && (int)($check['orders_status_invoice'] ?? 0) !== EInvoiceService::STATUS_INVOICE
    ) {
      $CLICSHOPPING_MessageStack->add(
        $this->app->getDef('warning_invoice_not_issued'),
        'warning'
      );
    }
  }

  /**
   * Add a new comment inside the status history
   * @return void
   */
  private function statusComment() :void
  {
    $comment = $this->app->getDef('comments_chorus_pro', ['order_id' => $this->orderId, 'send_date' => DateTime::getNow()]) . "\n\n";
    $customer_notified = 0;

    $data_array = [
      'orders_id' => $this->orderId,
      'orders_status_id' => $this->status,
      'orders_status_invoice_id' => $this->statusInvoice,
      'admin_user_name' => AdministratorAdmin::getUserAdmin(),
      'date_added' => 'now()',
      'customer_notified' => $customer_notified,
      'comments' => $comment,
    ];

    $this->app->db->save('orders_status_history', $data_array);
  }

  /**
   * @param array $check
   * @return void
   */
  private function ChorusPro(array $check):void
  {
    // Trigger Chorus Pro if e-invoice slider was enabled by admin
    $this->processChorusPro($check);
    $this->statusComment();

    // Show warning if order is paid but invoice not yet issued
    $this->checkInvoiceAlert($check);
  }

  /**
   * Executes the main process by calling the cron job and clearing the currency cache.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['Update']) && !is_null($this->orderId) && $this->orderId !== 0) {
      $check = OrderAdmin::checkStatusId($this->orderId);
      $this->ChorusPro($check);
    }
  }
}