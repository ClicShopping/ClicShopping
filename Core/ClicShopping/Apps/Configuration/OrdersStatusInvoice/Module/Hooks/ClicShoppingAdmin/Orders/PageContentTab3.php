<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\OrdersStatusInvoice\Module\Hooks\ClicShoppingAdmin\Orders;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\OrdersStatusInvoice\OrdersStatusInvoice as OrdersStatusInvoiceApp;
use ClicShopping\Apps\Orders\Orders\Classes\ClicShoppingAdmin\OrderAdmin;

class PageContentTab3 implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  public mixed $order;

  /**
   * Constructs the object and initializes the OrdersStatusInvoice application.
   *
   * This method checks if the 'OrdersStatusInvoice' object is registered in the registry.
   * If it is not registered, it creates and registers a new OrdersStatusInvoiceApp instance.
   * Then it retrieves the 'OrdersStatusInvoice' object from the registry and loads the
   * necessary language definitions for the specific module and hook.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('OrdersStatusInvoice')) {
      Registry::set('OrdersStatusInvoice', new OrdersStatusInvoiceApp());
    }

    $this->app = Registry::get('OrdersStatusInvoice');

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Orders/page_content_tab3');
  }

  /**
   * Generates and returns the HTML and JavaScript needed to display a dropdown for the invoice status
   * in the order details page, if the corresponding configuration settings and conditions are met.
   *
   * @return false|string Returns the generated HTML and JavaScript code as a string if conditions are met, otherwise false.
   */
  public function display()
  {
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Orders = Registry::get('Orders');

    if (!\defined('CLICSHOPPING_APP_ORDERS_STATUS_INVOICE_OI_STATUS') || CLICSHOPPING_APP_ORDERS_STATUS_INVOICE_OI_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['oID'])) {
      $oID = HTML::sanitize($_GET['oID']);

      $orders_invoice_statuses = [];
      $orders_status_invoice_array = [];

      $QordersStatusInvoice = $CLICSHOPPING_Orders->db->prepare('select orders_status_invoice_id,
                                                                          orders_status_invoice_name
                                                                   from :table_orders_status_invoice
                                                                   where language_id = :language_id
                                                                  ');
      $QordersStatusInvoice->bindInt(':language_id', $CLICSHOPPING_Language->getId());
      $QordersStatusInvoice->execute();

      while ($QordersStatusInvoice->fetch()) {
        $orders_invoice_statuses[] = [
          'id' => $QordersStatusInvoice->valueInt('orders_status_invoice_id'),
          'text' => $QordersStatusInvoice->value('orders_status_invoice_name')
        ];

        $orders_status_invoice_array[$QordersStatusInvoice->valueInt('orders_status_invoice_id')] = $QordersStatusInvoice->value('orders_status_invoice_name');
      }

      $Qorders = $this->app->db->get('orders', 'orders_id', ['orders_id' => (int)$oID]);

      if ($Qorders->fetch()) {
        if (!Registry::exists('Order')) {
          Registry::set('Order', new OrderAdmin($Qorders->valueInt('orders_id')));
        }

        $order = Registry::get('Order');

        $content = '<!-- order status start -->';
        $content .= '<div class="col-md-6">';
        $content .= '<span class="col-md-2"><strong>' . $CLICSHOPPING_Orders->getDef('entry_status_invoice') . '</strong></span> ';
        $content .= '<span class="col-md-4">';
        $content .= HTML::selectField('status_invoice', $orders_invoice_statuses, $order->info['orders_status_invoice']);
        $content .= '</span>';
        $content .= '</span>';
        $content .= '<!-- order status end -->';

        $output = <<<EOD
<!-- ######################## -->
<!--  Start order status     -->
<!-- ######################## -->
<script>
$('#entryStatus').prepend(
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
  }
}