<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\OrdersStatus\Module\Hooks\ClicShoppingAdmin\Orders;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\OrdersStatus\OrdersStatus as OrdersStatusApp;

class PageContentTab3 implements HooksInterface
{
  public mixed $app;

  /**
   * Class constructor.
   *
   * Initializes the OrdersStatus application and loads the necessary definitions for the page content tab in the Orders module.
   *
   * @return void
   */
  public function __construct()
  {

    if (!Registry::exists('OrdersStatus')) {
      Registry::set('OrdersStatus', new OrdersStatusApp());
    }

    $this->app = Registry::get('OrdersStatus');
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
  public function display(): string
  {
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Orders = Registry::get('Orders');

    if (!\defined('CLICSHOPPING_APP_ORDERS_STATUS_OU_STATUS') || CLICSHOPPING_APP_ORDERS_STATUS_OU_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['oID'])) {
      $oID = HTML::sanitize($_GET['oID']);
      $orders_statuses = [];
      $orders_status_array = [];

      $QordersStatus = $this->app->db->prepare('select orders_status_id,
                                                          orders_status_name
                                                    from :table_orders_status
                                                    where language_id = :language_id
                                                    ');
      $QordersStatus->bindInt(':language_id', $CLICSHOPPING_Language->getId());
      $QordersStatus->execute();

      while ($QordersStatus->fetch() !== false) {
        $orders_statuses[] = [
          'id' => $QordersStatus->valueInt('orders_status_id'),
          'text' => $QordersStatus->value('orders_status_name')
        ];

        $orders_status_array[$QordersStatus->valueInt('orders_status_id')] = $QordersStatus->value('orders_status_name');
      }

      $Qorders = $this->app->db->get('orders', 'orders_id', ['orders_id' => (int)$oID]);

      if ($Qorders->fetch()) {
        if (!Registry::exists('OrdersStatus')) {
          Registry::set('Order', new OrderAdmin($Qorders->valueInt('orders_id')));
        }

        $order = Registry::get('Order');

        $content = '<!-- order status start -->';
        $content .= '<span class="col-md-6">';
        $content .= '<span class="col-md-2"><strong>' . $CLICSHOPPING_Orders->getDef('entry_status') . '</strong></span> ';
        $content .= '<span class="col-md-4">';
        $content .= HTML::selectField('status', $orders_statuses, $order->info['orders_status']);
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