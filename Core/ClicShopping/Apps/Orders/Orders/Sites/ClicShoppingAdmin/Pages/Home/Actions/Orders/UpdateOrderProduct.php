<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Sites\ClicShoppingAdmin\Pages\Home\Actions\Orders;

use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\Apps\Orders\Orders\Classes\ClicShoppingAdmin\UpdateOrder as UpdateOrderService;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class UpdateOrderProduct extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  private mixed $db;
  private mixed $messageStack;
  private mixed $hooks;

  // Order context
  private int $order_id = 0;

  // Sub-action payload (populated selectively per sub-action)
  private int   $orders_products_id = 0;
  private int   $new_qty            = 0;
  private float $new_price          = 0.0;
  private int   $new_products_id    = 0;

  // Legacy quantity-update payload (kept for backward compatibility)
  private mixed $products_id;
  private mixed $quantity;
  private mixed $orders_products_name;

  public function __construct()
  {
    $this->app          = Registry::get('Orders');
    $this->db           = Registry::get('Db');
    $this->messageStack = Registry::get('MessageStack');
    $this->hooks        = Registry::get('Hooks');

    $this->app->loadDefinitions('ClicShoppingAdmin/main');

    // Shared: order id from GET
    if (isset($_GET['oID'])) {
      $this->order_id = (int)HTML::sanitize($_GET['oID']);
    }

    // Sub-action payloads from POST
    if (isset($_POST['orders_products_id'])) {
      $this->orders_products_id = (int)HTML::sanitize($_POST['orders_products_id']);
    }
    if (isset($_POST['new_qty'])) {
      $this->new_qty = (int)HTML::sanitize($_POST['new_qty']);
    }
    if (isset($_POST['new_price'])) {
      $this->new_price = (float)HTML::sanitize($_POST['new_price']);
    }
    if (isset($_POST['new_products_id'])) {
      $this->new_products_id = (int)HTML::sanitize($_POST['new_products_id']);
    }

    // Legacy fields (kept so existing UpdateOrder calls from other pages still work)
    if (isset($_POST['products_id']))           $this->products_id          = HTML::sanitize($_POST['products_id']);
    if (isset($_POST['quantity']))              $this->quantity             = HTML::sanitize($_POST['quantity']);
    if (isset($_POST['orders_products_name']))  $this->orders_products_name = HTML::sanitize($_POST['orders_products_name']);
  }

  // ---------------------------------------------------------------------------
  // History helper
  // ---------------------------------------------------------------------------

  /**
   * Inserts a row in orders_status_history to record the modification.
   * Reads the current order status and invoice status so the history entry
   * always reflects the real state at the time of the edit.
   *
   * @param string $comment  Free-text description of what changed.
   * @return void
   */
  private function recordHistory(string $comment): void
  {
    $QstatusOrder = $this->db->prepare('select orders_status,
                                               orders_status_invoice
                                          from :table_orders
                                         where orders_id = :orders_id
                                      ');
    $QstatusOrder->bindInt(':orders_id', $this->order_id);
    $QstatusOrder->execute();

    if (!$QstatusOrder->fetch()) {
      return;
    }

    $this->db->save('orders_status_history', [
      'orders_id'                => $this->order_id,
      'orders_status_id'         => $QstatusOrder->valueInt('orders_status'),
      'orders_status_invoice_id' => $QstatusOrder->valueInt('orders_status_invoice'),
      'admin_user_name'          => AdministratorAdmin::getUserAdmin(),
      'date_added'               => 'now()',
      'customer_notified'        => 0,
      'comments'                 => $comment . "\n" . 'Date : ' . DateTime::getNow('Y-m-d H:i:s'),
    ]);
  }

  // ---------------------------------------------------------------------------
  // Stock guard (unchanged logic from original class, bug-fixed)
  // ---------------------------------------------------------------------------

  /**
   * Returns false and adds a warning message when stock is insufficient.
   * Only active when STOCK_CHECK == 'true'.
   *
   * @param int $products_id
   * @param int $qty
   * @return bool
   */
  private function checkStock(int $products_id, int $qty): bool
  {
    if (STOCK_CHECK !== 'true') {
      return true;
    }

    $Qstock = $this->db->prepare('select products_quantity
                                    from :table_products
                                   where products_id = :products_id
                                ');
    $Qstock->bindInt(':products_id', $products_id);
    $Qstock->execute();

    if (!$Qstock->fetch()) {
      return true; // unknown product — let the caller decide
    }

    $stock_left = $Qstock->valueInt('products_quantity');

    if (($stock_left - $qty) < 0) {
      $this->messageStack->add($this->app->getDef('warning_order_stock_not_updated'), 'warning');
      return false;
    }

    return true;
  }

  // ---------------------------------------------------------------------------
  // Main dispatcher
  // ---------------------------------------------------------------------------

  public function execute(): void
  {
    if (!isset($_GET['Orders'], $_GET['UpdateOrderProduct'])) {
      return;
    }

    if ($this->order_id <= 0) {
      $this->app->redirect('Orders');
      return;
    }

    // ── Guard: invoice-locked orders may not be modified ──────────────────
    if (UpdateOrderService::isInvoiceLocked($this->order_id)) {
      $this->messageStack->add($this->app->getDef('error_order_invoice_locked'), 'error');
      $this->app->redirect('Edit&oID=' . $this->order_id. '#tab2');
      return;
    }

    match (true) {
      isset($_GET['UpdateProduct'])  => $this->handleUpdateProduct(),
      isset($_GET['AddProduct'])     => $this->handleAddProduct(),
      isset($_GET['DeleteProduct'])  => $this->handleDeleteProduct(),
      default                        => $this->handleLegacyUpdate(),
    };

    $this->hooks->call('Orders', 'UpdateOrderProduct');

    $this->app->redirect('Edit&oID=' . $this->order_id . '#tab2');
  }

  // ---------------------------------------------------------------------------
  // Sub-action handlers
  // ---------------------------------------------------------------------------

  /**
   * Updates qty + price of an existing product line.
   * Form fields expected: orders_products_id, new_qty, new_price.
   */
  private function handleUpdateProduct(): void
  {
    if ($this->orders_products_id <= 0 || $this->new_qty < 1 || $this->new_price < 0) {
      $this->messageStack->add($this->app->getDef('warning_order_not_updated'), 'warning');
      return;
    }

    $ok = UpdateOrderService::updateOrderProduct(
      $this->order_id,
      $this->orders_products_id,
      $this->new_qty,
      $this->new_price
    );

    if ($ok) {
      $this->recordHistory(
        $this->app->getDef('text_info_product_updated', [
          'orders_products_id' => $this->orders_products_id,
          'new_qty'            => $this->new_qty,
          'new_price'          => $this->new_price,
        ])
      );
      $this->messageStack->add($this->app->getDef('success_order_updated'), 'success');
    } else {
      $this->messageStack->add($this->app->getDef('warning_order_not_updated'), 'warning');
    }
  }

  /**
   * Adds a new product line to the order.
   * Form fields expected: new_products_id, new_qty, new_price.
   */
  private function handleAddProduct(): void
  {
    if ($this->new_products_id <= 0 || $this->new_qty < 1 || $this->new_price < 0) {
      $this->messageStack->add($this->app->getDef('warning_order_not_updated'), 'warning');
      return;
    }

    // Optional stock check before adding the line
    if (!$this->checkStock($this->new_products_id, $this->new_qty)) {
      return;
    }

    $ok = UpdateOrderService::addOrderProduct(
      $this->order_id,
      $this->new_products_id,
      $this->new_qty,
      $this->new_price
    );

    if ($ok) {
      $this->recordHistory(
        $this->app->getDef('text_info_product_added', [
          'products_id' => $this->new_products_id,
          'new_qty'     => $this->new_qty,
          'new_price'   => $this->new_price,
        ])
      );
      $this->messageStack->add($this->app->getDef('success_order_updated'), 'success');
    } else {
      $this->messageStack->add($this->app->getDef('warning_order_not_updated'), 'warning');
    }
  }

  /**
   * Deletes a product line from the order.
   * Form fields expected: orders_products_id.
   */
  private function handleDeleteProduct(): void
  {
    if ($this->orders_products_id <= 0) {
      $this->messageStack->add($this->app->getDef('warning_order_not_updated'), 'warning');
      return;
    }

    $ok = UpdateOrderService::deleteOrderProduct(
      $this->order_id,
      $this->orders_products_id
    );

    if ($ok) {
      $this->recordHistory(
        $this->app->getDef('text_info_product_deleted', [
          'orders_products_id' => $this->orders_products_id,
        ])
      );
      $this->messageStack->add($this->app->getDef('success_order_updated'), 'success');
    } else {
      $this->messageStack->add($this->app->getDef('warning_order_not_updated'), 'warning');
    }
  }

  /**
   * Legacy quantity-only update (backward compatibility with older form).
   * Kept so any external page that POSTs to Orders&UpdateOrder without a
   * sub-action key continues to work.
   *
   * Form fields expected (legacy): orders_products_id, quantity, products_id,
   *                                orders_products_name.
   */
  private function handleLegacyUpdate(): void
  {
    $qty        = (int)($this->quantity ?? 0);
    $op_id      = (int)($this->orders_products_id ?? 0);
    $product_id = (int)($this->products_id ?? 0);

    if ($qty < 1 || $op_id <= 0) {
      $this->messageStack->add($this->app->getDef('warning_order_not_updated'), 'warning');
      return;
    }

    if (!$this->checkStock($product_id, $qty)) {
      return;
    }

    // Retrieve the current unit price for this line so we preserve it
    $Qprice = $this->db->prepare('select final_price
                                  from :table_orders_products
                                   where orders_id = :orders_id
                                   and orders_products_id = :orders_products_id
                                   limit 1
                                ');
    $Qprice->bindInt(':orders_id', $this->order_id);
    $Qprice->bindInt(':orders_products_id', $op_id);
    $Qprice->execute();

    $current_price = $Qprice->fetch() ? $Qprice->valueDecimal('final_price') : 0.0;

    $ok = UpdateOrderService::updateOrderProduct(
      $this->order_id,
      $op_id,
      $qty,
      $current_price
    );

    if ($ok) {
      $this->recordHistory(
        $this->app->getDef('text_info_new_quantity', [
          'new_quantity'  => $qty,
          'products_name' => $this->orders_products_name ?? '',
        ])
      );
      $this->messageStack->add($this->app->getDef('success_order_updated'), 'success');
    } else {
      $this->messageStack->add($this->app->getDef('warning_order_not_updated'), 'warning');
    }
  }
}
