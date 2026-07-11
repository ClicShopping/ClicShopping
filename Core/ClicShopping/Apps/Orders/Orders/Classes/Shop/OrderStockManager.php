<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Classes\Shop;

use ClicShopping\OM\Registry;

use function defined;
use function is_array;
use function is_null;

/**
 * Applies the post-checkout stock side effects of an order: decrements catalogue stock
 * per product line (honouring B2B fixed-group quantities, download products and the
 * STOCK_* settings), disables depleted products, fires the store-owner stock alerts,
 * and increments products_ordered for the best-sellers list.
 *
 * Extracted verbatim from {@see Order::process()} to give the stock concern a single
 * responsibility. Inputs are explicit (order id, the order's product array for the legacy
 * attributes lookup, and the notifier used for the alert e-mails) so a future agentic
 * adapter can drive stock application without going through the whole Order lifecycle.
 */
class OrderStockManager
{
  private mixed $db;
  private mixed $hooks;

  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->hooks = Registry::get('Hooks');
  }

  /**
   * Runs the per-line stock update loop for a persisted order.
   *
   * @param int $order_id The persisted order whose product lines drive the stock update.
   * @param OrderNotifier $notifier Fires the reorder-level / sold-out alerts at the original points.
   * @return void
   */
  public function applyForOrder(int $order_id, OrderNotifier $notifier): void
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Prod = Registry::get('Prod');

    $Qproducts = $this->db->prepare('select products_id,
                                            products_quantity
                                      from :table_orders_products
                                      where orders_id = :orders_id
                                     ');
    $Qproducts->bindInt(':orders_id', $order_id);
    $Qproducts->execute();

    while ($Qproducts->fetch()) {
// Stock Update
      if (\defined('STOCK_LIMITED') && STOCK_LIMITED == 'true') {
        if (\defined('DOWNLOAD_ENABLED') && DOWNLOAD_ENABLED == 'true') {
          // Detect whether the product line is downloadable (has a download filename);
          // such virtual lines must not have their stock decremented.
          $Qstock = $this->db->prepare('select p.products_quantity,
                                                pad.products_attributes_filename
                                          from :table_products p
                                          left join :table_products_attributes pa on p.products_id = pa.products_id
                                          left join :table_products_attributes_download pad on pa.products_attributes_id = pad.products_attributes_id
                                          where p.products_id = :products_id
                                        ');
          $Qstock->bindInt(':products_id', $CLICSHOPPING_Prod::getProductID($Qproducts->valueInt('products_id')));
          $Qstock->execute();
        } else {
          $Qstock = $this->db->prepare('select products_quantity,
                                                products_quantity_alert
                                        from :table_products
                                        where products_id = :products_id
                                        ');

          $Qstock->bindInt(':products_id', $CLICSHOPPING_Prod::getProductID($Qproducts->valueInt('products_id')));
          $Qstock->execute();
        }

        if ($Qstock->fetch() !== false) {
// do not decrement quantities if products_attributes_filename exists (virtual/downloadable
// product). NOTE: the Db layer returns '' — not null — for a NULL column, so this must test
// empty(), not is_null(), otherwise every line (including virtual ones) would decrement — B5.
          if ((\defined('DOWNLOAD_ENABLED') && DOWNLOAD_ENABLED != 'true') || empty($Qstock->value('products_attributes_filename'))) {
// select the good qty in B2B ti decrease the stock. See shopping_cart top display out stock or not
            if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
              $QproductsQuantityCustomersGroup = $this->db->prepare('select products_quantity_fixed_group
                                                                        from :table_products_groups
                                                                        where products_id = :products_id
                                                                        and customers_group_id =  :customers_group_id
                                                                       ');
              $QproductsQuantityCustomersGroup->bindInt(':products_id', $CLICSHOPPING_Prod::getProductID($Qproducts->valueInt('products_id')));
              $QproductsQuantityCustomersGroup->bindInt(':customers_group_id', (int)$CLICSHOPPING_Customer->getCustomersGroupID());
              $QproductsQuantityCustomersGroup->execute();

              $products_quantity_customers_group = $QproductsQuantityCustomersGroup->fetch();

// do the exact qty in function the customer group and product
              $products_quantity_customers_group = $products_quantity_customers_group['products_quantity_fixed_group'];
            } else {
              $products_quantity_customers_group = 1;
            }

            if (\defined('STOCK_ALLOW_CHECKOUT') && STOCK_ALLOW_CHECKOUT == 'false') {
              $stock_left = $Qstock->valueInt('products_quantity') - ($Qproducts->valueInt('products_quantity') * $products_quantity_customers_group);
            } else {
              $stock_left = $Qstock->valueInt('products_quantity');
            }
          } else {
            $stock_left = $Qstock->valueInt('products_quantity');
          }

          if ($stock_left != $Qstock->valueInt('products_quantity')) {
            $this->db->save('products', ['products_quantity' => $stock_left], ['products_id' => $CLICSHOPPING_Prod::getProductID($Qproducts->valueInt('products_id'))]);
          }

          if (($stock_left < 1) && (\defined('STOCK_ALLOW_CHECKOUT') && STOCK_ALLOW_CHECKOUT == 'false')) {
            $this->db->save('products', ['products_status' => 0], ['products_id' => $CLICSHOPPING_Prod::getProductID($Qproducts->valueInt('products_id'))]);
          }

// alert an email if the product stock is < stock reorder level
// Alert by mail if a product is 0 or < 0
          $notifier->sendStockWarningAlert($order_id);
// Email alert when a product is exhausted
          $notifier->sendProductsSoldOutAlert($order_id);
        }
      }

// Update products_ordered (for bestsellers list)
      $Qupdate = $this->db->prepare('update :table_products
                                       set products_ordered = products_ordered + :products_ordered
                                       where products_id = :products_id');
      $Qupdate->bindInt(':products_ordered', $Qproducts->valueInt('products_quantity'));
      $Qupdate->bindInt(':products_id', $Qproducts->valueInt('products_id'));
      $Qupdate->execute();
    } // end while

    // Extension seam: let observers react once stock has been applied for the order.
    $this->hooks->call('OrderStockManager', 'Applied', ['order_id' => $order_id]);
  }
}
