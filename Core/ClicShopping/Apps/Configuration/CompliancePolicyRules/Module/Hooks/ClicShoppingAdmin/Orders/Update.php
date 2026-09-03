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
use ClicShopping\Apps\Orders\Orders\Classes\Common\OrdersStatus;
use ClicShopping\Apps\Configuration\CompliancePolicyRules\Classes\ClicShoppingAdmin\EInvoiceService;
use ClicShopping\Apps\Configuration\CompliancePolicyRules\CompliancePolicyRules as CompliancePolicyRulesApp;

class Update implements HooksInterface
{
  private int $orderId;
  private mixed $app;
  private int $status;
  private int $statusInvoice;

  public function __construct()
  {
    $this->orderId = isset($_GET['oID']) ? (int)HTML::sanitize($_GET['oID']) : 0;
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
   * @return bool True when the invoice was handed to EInvoiceService
   */
  private function processChorusPro(): bool
  {
    $eInvoice = new EInvoiceService();

    if (!$eInvoice->isEnabled()) {
      return false;
    }

    // Only act if the admin explicitly enabled the e-invoice slider
    if (!isset($_POST['notify_einvoice'])) {
      return false;
    }

    $new_invoice_status = $this->statusInvoice;

    // The action saved the new status BEFORE calling this hook, so the orders row already carries
    // it: comparing against it would always match and nothing would ever be sent. The previous
    // state is the history row written before the one this update just added.
    if ($this->previousInvoiceStatus() === $new_invoice_status) {
      return false;
    }

    // Only process actionable statuses
    if (!in_array($new_invoice_status, [
      EInvoiceService::STATUS_INVOICE,
      EInvoiceService::STATUS_CANCEL,
      EInvoiceService::STATUS_CREDIT_NOTE,
    ])) {
      return false;
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

    return true;
  }

  /**
   * Displays a MessageStack warning when the order is in a paid/confirmed status
   * but the invoice has not yet been issued (orders_status_invoice != STATUS_INVOICE).
   *
   * This reminds the administrator to:
   *   1. Regenerate and send the PDF invoice to the customer
   *   2. Transmit the electronic invoice to Chorus Pro via the status tab
   *
   * The order status that owes an invoice is the delivered one; the id lives in
   * {@see OrdersStatus}, never in a literal here.
   *
   * @param array $check  Current order data from getCheckStatus()
   */
  private function checkInvoiceAlert(array $check): void
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    if ($this->status === OrdersStatus::DELIVERED
      && (int)($check['orders_status_invoice'] ?? 0) !== EInvoiceService::STATUS_INVOICE
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
    if ($this->processChorusPro() === true) {
      $this->statusComment();
    }

    // Show warning if order is paid but invoice not yet issued
    $this->checkInvoiceAlert($check);
  }

  /**
   * Invoice status carried by the history row preceding the one this update wrote.
   *
   * @return int Previous invoice status, or STATUS_ORDER when the order has no earlier history
   */
  private function previousInvoiceStatus(): int
  {
    $Qprevious = $this->app->db->prepare('select orders_status_invoice_id
                                            from :table_orders_status_history
                                            where orders_id = :orders_id
                                            order by orders_status_history_id desc
                                            limit 1 offset 1
                                          ');
    $Qprevious->bindInt(':orders_id', $this->orderId);
    $Qprevious->execute();

    if ($Qprevious->fetch() === false) {
      return EInvoiceService::STATUS_ORDER;
    }

    return $Qprevious->valueInt('orders_status_invoice_id');
  }

  /**
   * Executes the main process by calling the cron job and clearing the currency cache.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['Update']) && $this->orderId !== 0) {
      $check = OrderAdmin::checkStatusId($this->orderId);
      $this->ChorusPro($check);
    }
  }
}