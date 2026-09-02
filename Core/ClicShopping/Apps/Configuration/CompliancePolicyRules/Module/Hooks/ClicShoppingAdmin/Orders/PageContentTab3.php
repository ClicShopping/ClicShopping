<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\Hooks\ClicShoppingAdmin\Orders;

use ClicShopping\Apps\Configuration\CompliancePolicyRules\Classes\ClicShoppingAdmin\EInvoiceService;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Orders\Orders\Classes\ClicShoppingAdmin\OrderAdmin;

use ClicShopping\Apps\Configuration\CompliancePolicyRules\CompliancePolicyRules as CompliancePolicyRulesApp;

class PageContentTab3 implements HooksInterface
{
  public mixed $app;
  public mixed $db;
  private mixed $language;

  /**
   * Class constructor.
   *
   * Initializes the OrdersStatus application and loads the necessary definitions for the page content tab in the Orders module.
   *
   * @return void
   */
  public function __construct()
  {
    // Registry key 'CompliancePolicyRules' holds the Shared display helper, not this App.
    $this->app = new CompliancePolicyRulesApp();
    $this->language = Registry::get('Language');
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Orders/page_content_tab3');
  }

  /**
   * Generates and returns the HTML and JavaScript content for displaying or updating the order status
   * in the administrative panel of the application.
   *
   * The output includes rendered HTML elements and JavaScript for dynamically injecting the order status
   * into the panel. It retrieves the available order statuses from the database and uses them to populate
   * a select dropdown field. The function also handles checks for the necessary registry objects and
   * ensures the designated order is valid.
   *
   * @return string The HTML and JavaScript content for the order status display, or an empty string if conditions are not met.
   */
  public function display()
  {
    if (!\defined('CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_FRE_STATUS') || CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_FRE_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['oID'])) {
      $oID = HTML::sanitize($_GET['oID']);
      $Qorders = $this->app->db->get('orders', 'orders_id', ['orders_id' => (int)$oID]);

      if ($Qorders->fetch()) {
        if (!Registry::exists('Order')) {
          Registry::set('Order', new OrderAdmin($Qorders->valueInt('orders_id')));
        }

        $order = Registry::get('Order');

        $eInvoiceService = new EInvoiceService();

 //if ($eInvoiceService->isEnabled()) {
        $orders_status_invoice_array = [];

        $QordersStatusInvoice = $this->app->db->prepare('select orders_status_invoice_id,
                                                                  orders_status_invoice_name
                                                           from :table_orders_status_invoice
                                                           where language_id = :language_id
                                                          ');
        $QordersStatusInvoice->bindInt(':language_id', $this->language->getId());
        $QordersStatusInvoice->execute();

        while ($QordersStatusInvoice->fetch()) {
          $orders_invoice_statuses[] = [
            'id' => $QordersStatusInvoice->valueInt('orders_status_invoice_id'),
            'text' => $QordersStatusInvoice->value('orders_status_invoice_name')
          ];

          $orders_status_invoice_array[$QordersStatusInvoice->valueInt('orders_status_invoice_id')] = $QordersStatusInvoice->value('orders_status_invoice_name');
        }

          // Retrieve current numeric invoice status directly from the orders table
          $QinvoiceStatus = $this->app->db->prepare('select orders_status_invoice
                                                                        from :table_orders
                                                                        where orders_id = :orders_id
                                                                        ');
          $QinvoiceStatus->bindInt(':orders_id', $oID);
          $QinvoiceStatus->execute();
          $current_invoice_status_id = $QinvoiceStatus->valueInt('orders_status_invoice');

          $is_b2b = isset($order) ? $eInvoiceService->isB2B($order->customer) : false;
          $already_sent = $eInvoiceService->isAlreadySent($oID);

          $content = '<!-- order CompliancePolicyRules start -->';

          $content .= '
                <div class="row mt-2" id="notifyEInvoice">
                  <div class="col-md-12">
                    <div class="card border-secondary">
                      <!-- Card header: title + portal button -->
                      <div class="card-header d-flex align-items-center gap-2">
                        <strong> ' . $this->app->getDef('card_title') . '</strong>
                        <span class="ms-2 text-muted small">
                          ' . $this->app->getDef('label_invoice_status') . ' : <strong>' . htmlspecialchars($orders_status_invoice_array[$current_invoice_status_id] ?? '—') . '</strong>
                        </span>
                        <a href="' . EInvoiceService::CHORUS_PORTAL_URL . '"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="btn btn-sm btn-primary ms-auto"
                           title="' . $this->app->getDef('button_portal') . '">
                          <i class="bi bi-box-arrow-up-right me-1"></i>' . $this->app->getDef('button_portal') . '
                        </a>
                      </div>

                      <!-- Card body: contextual status message -->
                      <div class="card-body py-2">
           ';
          if (!$is_b2b) {
            $content .= '
                          <span class="badge bg-secondary">' . $this->app->getDef('badge_b2c') . '</span>
                          &nbsp;<span class="text-muted small">' . $this->app->getDef('text_b2c_notice') . '</span>
            ';
          } elseif ($already_sent) {
            $content .= '
                          <span class="badge bg-success">' . $this->app->getDef('badge_transmitted') . '</span>
                          &nbsp;<span class="text-success small">' . $this->app->getDef('text_transmitted_notice') . '</span>
          ';
        } elseif (!in_array($current_invoice_status_id, [EInvoiceService::STATUS_INVOICE, EInvoiceService::STATUS_CANCEL, EInvoiceService::STATUS_CREDIT_NOTE])) {
          $content .= '
                          <span class="badge bg-warning text-dark">' . $this->app->getDef('badge_pending') . '</span>
                          &nbsp;<span class="text-muted small">' . $this->app->getDef('text_pending_notice') . '</span>
        ';
        } else {
        $content .= '
                          <span class="badge bg-info">' . $this->app->getDef('badge_ready') . '</span>
                          &nbsp;<span class="text-muted small">' . $this->app->getDef('text_ready_notice') . '</span>
                        ';
                        }

          // Show the E-Invoice slider only if B2B and not yet transmitted
          if ($is_b2b && !$already_sent) {
          $content .= '
                        <div class="mt-2">
                          <div class="form-group row">
                            <label class="col-5 col-form-label">
                              <strong>' . $this->app->getDef('entry_notify_einvoice') . '</strong>
                            </label>
                            <div class="col-md-5">
                              <ul class="list-group-slider list-group-flush">
                                <li class="list-group-item-slider">
                                  <label class="switch">
                                    <input type="checkbox" name="notify_einvoice" value="1" class="success">
                                    <span class="slider"></span>
                                  </label>
                                </li>
                              </ul>
                            </div>
                          </div>
                        </div>
          ';
          }
          $content .= '                      
                      </div><!-- /card-body -->
                    </div><!-- /card -->
                  </div><!-- /col -->
                </div><!-- /row -->
          ';

          $content .= '<!-- order CompliancePolicyRules end -->';

          $output = <<<EOD


<!-- ######################## -->
<!--  Start order status     -->
<!-- ######################## -->
<script>
$('#ErpOrder').append(
    '{$content}'
);
</script>
<!-- ######################## -->
<!--  End order status      -->
<!-- ######################## -->
EOD;
          return $output;
        }
      }
    //}
  }
}