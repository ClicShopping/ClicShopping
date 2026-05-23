<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Sites\ClicShoppingAdmin\Pages\Home\Actions\Orders;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\Apps\Configuration\TemplateEmail\Classes\ClicShoppingAdmin\TemplateEmailAdmin;
use ClicShopping\Apps\Orders\Orders\Classes\ClicShoppingAdmin\OrderAdmin;
use ClicShopping\Apps\Orders\Orders\Classes\Common\EInvoiceService;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;
  private mixed $lang;
  private mixed $db;
  protected int $oID;
  protected int $status;
  protected int $statusInvoice;
  protected string $comments;
  protected $notifyComments;
  protected $notify;
  protected $hooks;

  /**
   * Constructor — resolves dependencies from the Registry and sanitizes POST/GET input.
   */
  public function __construct()
  {
    $this->app = Registry::get('Orders');
    $this->lang = Registry::get('Language');
    $this->db = Registry::get('Db');

    if (isset($_GET['oID'])) $this->oID = HTML::sanitize($_GET['oID']);
    if (isset($_POST['status'])) $this->status = HTML::sanitize($_POST['status']);

    if (isset($_POST['status_invoice'])) $this->statusInvoice = HTML::sanitize($_POST['status_invoice']);
    if (isset($_POST['comments'])) $this->comments = HTML::sanitize($_POST['comments']);

    if (isset($_POST['notify_comments'])) $this->notifyComments = HTML::sanitize($_POST['notify_comments']);
    if (isset($_POST['notify'])) $this->notify = HTML::sanitize($_POST['notify']);

    $this->hooks = Registry::get('Hooks');
  }

  /**
   * Fetches the current order status data needed for change detection and e-invoice processing.
   * Retrieves order status, invoice status, customer contact details, and SIRET information.
   *
   * @return mixed  Fetched row array from the orders table, or false if not found
   */
  private function getCheckStatus()
  {
    $data_array = [
      'customers_name',
      'customers_email_address',
      'orders_status',
      'date_purchased',
      'orders_status_invoice',
      'erp_invoice',
      'customers_siret',
      'customers_company',
    ];

    $QcheckStatus = $this->app->db->get('orders', $data_array, ['orders_id' => (int)$this->oID]);

    $check = $QcheckStatus->fetch();

    return $check;
  }

  /**
   * Sends an update notification email to the customer when the order status changes.
   * Includes optional comments and uses the configured email template.
   * Only called when the 'notify' POST field is set.
   */
  private function getMail()
  {
    $CLICSHOPPING_Mail = Registry::get('Mail');

    $check = $this->getCheckStatus();

    $notify_comments = '';

    if (isset($this->notifyComments)) {
      $notify_comments = $this->app->getDef('email_text_comments_update', ['comment' => nl2br($this->comments)]) . "\n\n";
      $notify_comments = html_entity_decode($notify_comments);
    }

    $template_email_intro_command = TemplateEmailAdmin::getTemplateEmailIntroCommand();
    $template_email_signature = TemplateEmailAdmin::getTemplateEmailSignature();
    $template_email_footer = TemplateEmailAdmin::getTemplateEmailTextFooter();
    $status_order = $this->app->getDef('email_text_new_order_status', ['status' => $this->status]);

    $email_subject = $this->app->getDef('email_text_subject', ['store_name' => STORE_NAME]);

    $email_text = $template_email_intro_command
      . '<br />' . $status_order . '<br />'
      . $this->app->getDef('email_separator') . '<br /><br />'
      . $this->app->getDef('email_text_order_number') . ' ' . $this->oID . '<br /><br />'
      . $this->app->getDef('email_text_invoice_url') . '<br />'
      . CLICSHOPPING::link('Shop/index.php', 'Account&HistoryInfo&order_id=' . $this->oID) . '<br /><br />'
      . $this->app->getDef('email_text_date_ordered') . ' ' . DateTime::toShort($check['date_purchased']) . '<br />'
      . $notify_comments . '<br /><br />'
      . $template_email_signature . '<br /><br />'
      . $template_email_footer;

// Envoie du mail avec gestion des images pour Fckeditor et Imanager.
    $message = html_entity_decode($email_text);
    $message = str_replace('src="/', 'src="' . CLICSHOPPING::getConfig('http_server', 'Shop') . '/', $message);
    $CLICSHOPPING_Mail->addHtmlCkeditor($message);

    $from = STORE_OWNER_EMAIL_ADDRESS;
    $CLICSHOPPING_Mail->send($check['customers_email_address'], $check['customers_name'], null, $from, $email_subject);

    $this->hooks->call('Orders', 'OrderEmail');
  }

  /**
   * Triggers the Chorus Pro electronic invoice submission when conditions are met.
   *
   * Conditions required (all must be true):
   *   1. CHORUSPRO_ENABLED = True
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

    $new_invoice_status = (int)$this->statusInvoice;

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

    // Instantiate OrderAdmin to get complete order data (customer, products, totals)
    $order = new OrderAdmin((int)$this->oID);

    $eInvoice->process(
      (int)$this->oID,
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

    if ((int)$this->status === $paid_order_status
      && (int)($check['orders_status_invoice'] ?? 0) !== EInvoiceService::STATUS_INVOICE
    ) {
      $CLICSHOPPING_MessageStack->add(
        $this->app->getDef('warning_invoice_not_issued'),
        'warning'
      );
    }
  }

  /**
   * Main action — processes the order status update form submission.
   *
   * Steps performed:
   *   1. Validates the order ID and reads current status
   *   2. Updates orders table if status or invoice status changed
   *   3. Inserts a new row in orders_status_history
   *   4. Triggers Chorus Pro transmission if e-invoice slider is set
   *   5. Shows invoice alert if order is paid but invoice not issued
   *   6. Sends customer notification email if 'notify' is set
   *   7. Redirects back to the Orders list
   */
  public function execute(): void
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    if (isset($_GET['Update'])) {
      $order_updated = false;

      if ($this->oID != 0) {
        $check = $this->getCheckStatus();
// verify and update the status if changed
        if (($check['orders_status'] != $this->status) || ($check['orders_status_invoice'] != $this->statusInvoice) || !\is_null($this->comments)) {
          $data_array = [
            'orders_status' => (int)$this->status,
            'orders_status_invoice' => (int)$this->statusInvoice,
            'last_modified' => 'now()'
          ];

          $this->app->db->save('orders', $data_array, ['orders_id' => $this->oID]);

          $customer_notified = isset($this->notify) ? 1 : 0;

          $data_array = [
            'orders_id' => (int)$this->oID,
            'orders_status_id' => (int)$this->status,
            'orders_status_invoice_id' => (int)$this->statusInvoice,
            'admin_user_name' => AdministratorAdmin::getUserAdmin(),
            'date_added' => 'now()',
            'customer_notified' => (int)$customer_notified,
            'comments' => $this->comments,
          ];

          $this->app->db->save('orders_status_history', $data_array);

          $order_updated = true;

          // Trigger Chorus Pro if e-invoice slider was enabled by admin
          $this->processChorusPro($check);

          // Show warning if order is paid but invoice not yet issued
          $this->checkInvoiceAlert($check);
        } else {
          $order_updated = true;
        }
      }

      if ($order_updated === true) {
        $CLICSHOPPING_MessageStack->add($this->app->getDef('success_order_updated'), 'success');
      } else {
        $CLICSHOPPING_MessageStack->add($this->app->getDef('warning_order_not_updated'), 'warning');
      }

      $this->hooks->call('Orders', 'Update');

      if (isset($this->notify)) {
        $this->getMail();
      }

      $this->app->redirect('Orders');
    }
  }
}