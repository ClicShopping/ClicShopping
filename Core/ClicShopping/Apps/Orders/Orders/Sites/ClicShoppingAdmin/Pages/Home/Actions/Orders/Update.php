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

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;
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
    // Typed properties must always be initialized: reading an uninitialized typed
    // property is a fatal Error in PHP 8 (not just a warning).
    $this->oID = isset($_GET['oID']) ? (int)HTML::sanitize($_GET['oID']) : 0;
    $this->status = isset($_POST['status']) ? (int)HTML::sanitize($_POST['status']) : 0;
    $this->statusInvoice = isset($_POST['status_invoice']) ? (int)HTML::sanitize($_POST['status_invoice']) : 0;
    $this->comments = isset($_POST['comments']) ? HTML::sanitize($_POST['comments']) : '';

    if (isset($_POST['notify_comments'])) $this->notifyComments = HTML::sanitize($_POST['notify_comments']);
    if (isset($_POST['notify'])) $this->notify = HTML::sanitize($_POST['notify']);

    $this->hooks = Registry::get('Hooks');
  }

  /**
   * Sends an update notification email to the customer when the order status changes.
   * Includes optional comments and uses the configured email template.
   * Only called when the 'notify' POST field is set.
   */
  private function getMail()
  {
    $CLICSHOPPING_Mail = Registry::get('Mail');

    $check = OrderAdmin::checkStatusId($this->oID);

    $notify_comments = '';

    if (isset($this->notifyComments)) {
      $notify_comments = $this->app->getDef('email_text_comments_update', ['comment' => nl2br($this->comments)]) . "\n\n";
      $notify_comments = html_entity_decode($notify_comments);
    }

    $template_email_intro_command = TemplateEmailAdmin::getTemplateEmailIntroCommand();
    $template_email_signature = TemplateEmailAdmin::getTemplateEmailSignature();
    $template_email_footer = TemplateEmailAdmin::getTemplateEmailTextFooter();

    // The status must be displayed by its readable name (e.g. "En cours"), not the
    // numeric ID submitted by the form, otherwise the customer receives "Nouveau statut : 3".
    $status_name = (string)$this->status;

    $CLICSHOPPING_Language = Registry::get('Language');

    $Qstatus = $this->app->db->prepare('select orders_status_name
                                         from :table_orders_status
                                         where orders_status_id = :orders_status_id
                                           and language_id = :language_id
                                       ');
    $Qstatus->bindInt(':orders_status_id', (int)$this->status);
    $Qstatus->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
    $Qstatus->execute();

    if ($Qstatus->fetch() !== false) {
      $status_name = $Qstatus->value('orders_status_name');
    }

    $status_order = $this->app->getDef('email_text_new_order_status', ['status' => $status_name]);

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

    $message = html_entity_decode($email_text);
    $message = str_replace('src="/', 'src="' . CLICSHOPPING::getConfig('http_server', 'Shop') . '/', $message);
    $CLICSHOPPING_Mail->addHtmlCkeditor($message);

    $from = STORE_OWNER_EMAIL_ADDRESS;
    $CLICSHOPPING_Mail->send($check['customers_email_address'], $check['customers_name'], null, $from, $email_subject);

    $this->hooks->call('Orders', 'OrderEmail');
  }


  /**
   * Main action — processes the order status update form submission.
   *
   * Steps performed:
   *   1. Validates the order ID and reads current status
   *   2. Updates orders table if status or invoice status changed
   *   3. Inserts a new row in orders_status_history
   *   4. Calls the Orders/Update hooks (e-invoice transmission lives there)
   *   5. Sends customer notification email if 'notify' is set
   *   6. Redirects back to the Orders list
   */
  public function execute(): void
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    if (isset($_GET['Update'])) {
      $order_updated = false;

      if ($this->oID != 0) {
        $check = OrderAdmin::checkStatusId($this->oID);

        if (($check['orders_status'] != $this->status) || ($check['orders_status_invoice'] != $this->statusInvoice) || ($this->comments !== '')) {
          $data_array = [
            'orders_status' => $this->status,
            'orders_status_invoice' => $this->statusInvoice,
            'last_modified' => 'now()'
          ];

          $this->app->db->save('orders', $data_array, ['orders_id' => $this->oID]);

          $customer_notified = isset($this->notify) ? 1 : 0;

          $data_array = [
            'orders_id' => $this->oID,
            'orders_status_id' => $this->status,
            'orders_status_invoice_id' => $this->statusInvoice,
            'admin_user_name' => AdministratorAdmin::getUserAdmin(),
            'date_added' => 'now()',
            'customer_notified' => (int)$customer_notified,
            'comments' => $this->comments,
          ];

          $this->app->db->save('orders_status_history', $data_array);

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