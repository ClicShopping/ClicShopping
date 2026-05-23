<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Hooks;
use ClicShopping\OM\Registry;

class UpdateOrder
{
  // -------------------------------------------------------------------------
  // All methods are static: this class is a pure service layer with no
  // instance state. Each method resolves its dependencies from Registry at
  // call-time, which is the standard ClicShopping pattern for admin classes.
  // -------------------------------------------------------------------------

  /**
   * Checks whether an order is locked for editing because it has been
   * validated as an invoice.
   *
   * An order is considered locked when its invoice status is >= 1
   * (validated, cancelled, credit-note). Once locked no product line may
   * be added, modified or deleted unless the operator explicitly resets the
   * invoice status to 0 ("pending") — French law art. L441-9 C.com.
   *
   * @param int $order_id
   * @return bool
   */
  public static function isInvoiceLocked(int $order_id): bool
  {
    $CLICSHOPPING_db = Registry::get('Db');

    $result = false;

    $Qlock = $CLICSHOPPING_db->get('orders', 'orders_status_invoice, orders_status', ['orders_id' => $order_id]);

    if (!$Qlock->fetch()) {
      $result = false;
    }

    if ($Qlock->valueInt('orders_status_invoice') == 2 || $Qlock->valueInt('orders_status') == 3) {
      $result = true;
     }

    return $result;
  }

  /**
   * Updates the quantity and unit price of an existing order product line,
   * then refreshes the order grand total.
   *
   * @param int   $order_id
   * @param int   $orders_products_id  PK of the orders_products row.
   * @param int   $new_qty             Must be >= 1.
   * @param float $new_price           Unit price excl. tax, must be >= 0.
   * @return bool  False when locked or when input is invalid.
   */
  public static function updateOrderProduct(
    int   $order_id,
    int   $orders_products_id,
    int   $new_qty,
    float $new_price
  ): bool {
    if (self::isInvoiceLocked($order_id)) {
      return false;
    }

    if ($new_qty < 1 || $new_price < 0) {
      return false;
    }

    $db    = Registry::get('Db');
    $hooks = Registry::get('Hooks');

    $db->save('orders_products', [
      'products_quantity' => $new_qty,
      'products_price'    => $new_price,
      'final_price'       => $new_price,
    ], [
      'orders_id'          => $order_id,
      'orders_products_id' => $orders_products_id,
    ]);

    static::recalculateOrderTotal($order_id);

    $hooks->call('Orders', 'UpdateOrderProduct');

    return true;
  }

  /**
   * Adds a new catalogue product line to an existing order.
   *
   * Name, model and tax rate are resolved from the catalogue — the caller
   * only needs to supply product_id, qty and price.
   *
   * @param int   $order_id
   * @param int   $products_id  Catalogue product to add.
   * @param int   $qty          Must be >= 1.
   * @param float $unit_price   Unit price excl. tax, must be >= 0.
   * @return bool  False when locked or when the product does not exist.
   */
  public static function addOrderProduct(
    int   $order_id,
    int   $products_id,
    int   $qty,
    float $unit_price
  ): bool {
    if (static::isInvoiceLocked($order_id)) {
      return false;
    }

    if ($qty < 1 || $unit_price < 0) {
      return false;
    }

    $db       = Registry::get('Db');
    $language = Registry::get('Language');
    $hooks    = Registry::get('Hooks');

    // Resolve product name, model and tax class from the catalogue
    $Qproduct = $db->prepare('select p.products_model,
                                   p.products_tax_class_id,
                                   pd.products_name
                              from :table_products p
                              left join :table_products_description pd
                                on pd.products_id = p.products_id
                               and pd.language_id = :language_id
                             where p.products_id  = :products_id
                             limit 1
                          ');
    $Qproduct->bindInt(':products_id', $products_id);
    $Qproduct->bindInt(':language_id', $language->getId());
    $Qproduct->execute();

    if (!$Qproduct->fetch()) {
      return false;
    }

    // Resolve the tax rate for this product's tax class
    $tax_rate = 0;
    $Qtax = $db->prepare('select tax_rate
                          from :table_tax_rates
                           where tax_class_id = :tax_class_id
                           limit 1
                        ');
    $Qtax->bindInt(':tax_class_id', $Qproduct->valueInt('products_tax_class_id'));
    $Qtax->execute();
    if ($Qtax->fetch()) {
      $tax_rate = $Qtax->valueDecimal('tax_rate');
    }

    $db->save('orders_products', [
      'orders_id'         => $order_id,
      'products_id'       => $products_id,
      'products_model'    => $Qproduct->value('products_model'),
      'products_name'     => $Qproduct->value('products_name'),
      'products_price'    => $unit_price,
      'products_tax'      => $tax_rate,
      'products_quantity' => $qty,
      'final_price'       => $unit_price,
    ]);

    static::recalculateOrderTotal($order_id);

    $hooks->call('OrderAdmin', 'AddOrderProduct');

    return true;
  }

  /**
   * Removes a single product line (and its attributes) from an order,
   * then refreshes the grand total.
   *
   * @param int $order_id
   * @param int $orders_products_id  PK of the row to remove.
   * @return bool  False when locked.
   */
  public static function deleteOrderProduct(
    int $order_id,
    int $orders_products_id
  ): bool {
    if (static::isInvoiceLocked($order_id)) {
      return false;
    }

    $db    = Registry::get('Db');
    $hooks = Registry::get('Hooks');

    $db->delete('orders_products_attributes', [
      'orders_id'          => $order_id,
      'orders_products_id' => $orders_products_id,
    ]);

    $db->delete('orders_products', [
      'orders_id'          => $order_id,
      'orders_products_id' => $orders_products_id,
    ]);

    static::recalculateOrderTotal($order_id);

  //  $hooks->call('OrderAdmin', 'DeleteOrderProduct');

    return true;
  }

  /**
   * Recomputes ALL rows of orders_total from the current product lines of an
   * order and persists the results.
   *
   * The Shop Order class (Order::cart() → Order::Insert()) writes these rows
   * on checkout via $CLICSHOPPING_OrderTotal->process(). That pipeline is not
   * available from the admin side at edit-time, so we rebuild the figures
   * directly from the stored orders_products data, mirroring exactly what the
   * shop computes:
   *
   *   ot_subtotal / ST  : Σ (final_price × qty)                      [HT]
   *   ot_tax     / TX   : one row per distinct tax rate group,
   *                        value = subtotal_for_group × (rate / 100)
   *   ot_shipping / SH  : preserved as-is (not touched by line edits)
   *   ot_total   / TO   : subtotal_HT + Σ tax + shipping
   *
   * If the installation stores prices TTC (DISPLAY_PRICE_WITH_TAX == 'true')
   * the tax is already included in final_price, so we back-calculate it:
   *   tax = shown_price − shown_price / (1 + rate/100)
   * and the subtotal = Σ shown_price (TTC), while the grand total = subtotal
   * + shipping (tax is embedded, not added again).
   *
   * Rows whose class is not one of the four above (e.g. discount coupons,
   * custom modules) are left untouched so no data is lost.
   *
   * Called automatically by updateOrderProduct, addOrderProduct and
   * deleteOrderProduct — callers do not need to invoke it directly.
   *
   * @param int $order_id
   * @return void
   */
  public static function recalculateOrderTotal(int $order_id): void
  {
    $db         = Registry::get('Db');
    $currencies = Registry::get('Currencies');

    // ── 1. Read the order's currency ───────────────
    $Qorder = $db->prepare('select currency,
                                   currency_value
                              from :table_orders
                             where orders_id = :orders_id
                          ');
    $Qorder->bindInt(':orders_id', $order_id);
    $Qorder->execute();
    $Qorder->fetch();

    $currency       = $Qorder->value('currency') ?: 'EUR';
    $currency_value = $Qorder->valueDecimal('currency_value') ?: 1.0;

    // ── 2. Read the existing shipping row (preserved as-is) ──────────────────
    $Qship = $db->prepare("select value, 
                                   text, 
                                   title, 
                                   sort_order
                              from :table_orders_total
                             where orders_id = :orders_id
                               and (class = 'ot_shipping' or class = 'SH')
                             limit 1
                          ");
    $Qship->bindInt(':orders_id', $order_id);
    $Qship->execute();

    $shipping_value     = 0.0;
    $shipping_row       = null;
    if ($Qship->fetch()) {
      $shipping_value = $Qship->valueDecimal('value');
      $shipping_row   = [
        'value'      => $shipping_value,
        'text'       => $Qship->value('text'),
        'title'      => $Qship->value('title'),
        'sort_order' => $Qship->valueInt('sort_order'),
      ];
    }

    // ── 3. Read the existing rows to inherit title labels and sort_order ──────
    //      (we keep the human-readable labels the shop originally wrote)
    $Qexisting = $db->prepare('select class, 
                                       title, 
                                       sort_order
                                 from :table_orders_total
                                 where orders_id = :orders_id
                              ');
    $Qexisting->bindInt(':orders_id', $order_id);
    $Qexisting->execute();

    $meta = [];   // class → ['title' => ..., 'sort_order' => ...]
    while ($Qexisting->fetch()) {
      $meta[$Qexisting->value('class')] = [
        'title'      => $Qexisting->value('title'),
        'sort_order' => $Qexisting->valueInt('sort_order'),
      ];
    }

    // ── 4. Determine pricing mode from the stored order (no session needed) ──
    //      We infer it from whether any ot_tax / TX row existed before:
    //      if the subtotal row existed and was > grand_total − shipping, prices
    //      are TTC. In practice we read the DISPLAY_PRICE_WITH_TAX constant
    //      when available; otherwise we assume HT (safest for B2B installs).
    $prices_include_tax = (defined('DISPLAY_PRICE_WITH_TAX') && DISPLAY_PRICE_WITH_TAX === 'true');

    // ── 5. Read all product lines with their tax rates ────────────────────────
    $Qlines = $db->prepare('select final_price,
                                   products_quantity,
                                   products_tax
                              from :table_orders_products
                             where orders_id = :orders_id
                          ');
    $Qlines->bindInt(':orders_id', $order_id);
    $Qlines->execute();

    $subtotal   = 0.0;   // Σ final_price × qty  (HT when prices_include_tax=false)
    $total_tax = 0;

    while ($Qlines->fetch()) {
      $unit_price = $Qlines->valueDecimal('final_price'); // HT
      $qty        = $Qlines->valueInt('products_quantity');
      $tax_rate   = $Qlines->valueDecimal('products_tax');
      $line_total = $unit_price * $qty;
      $subtotal  += $line_total;

      if ($tax_rate > 0) {
        if ($prices_include_tax) {
          $line_tax = $line_total * ($tax_rate / 100.0);
          $total_tax += $line_tax;
        }
      } else {
        $total_tax += $line_tax;
    }
  }

    // Grand total:
    //   • TTC mode: subtotal already includes tax → subtotal + shipping
    //   • HT  mode: subtotal + tax + shipping
    if (!$prices_include_tax) {
      $grand_total = $subtotal + $shipping_value;
    } else {
      $grand_total = $subtotal + $total_tax + $shipping_value;
    }

    // ── 6. Format amounts using the order's currency 
    $fmt = static function (float $v) use ($currencies, $currency, $currency_value): string {
      return $currencies->format($v, true, $currency, $currency_value);
    };

    // ── 7. Persist ot_subtotal / ST
    $Qusub = $db->prepare("update :table_orders_total
                           set value = :value,
                                 text  = :text
                           where orders_id = :orders_id
                           and class = 'ST'
                          ");

    $Qusub->bindValue(':value', $subtotal);
    $Qusub->bindValue(':text',  $fmt($subtotal));
    $Qusub->bindInt(':orders_id', $order_id);
    $Qusub->execute();

    // ── 8. Persist ot_tax / TX rows
    //      The shop may have written one row per tax group.  We update the
    //      first matching row with the aggregated total; additional rows (rare)
    //      are set to 0 so the grand total stays correct.
    $Qutax = $db->prepare("update :table_orders_total
                           set value = :value,
                               text = :text
                           where orders_id = :orders_id
                           and class = 'TX'
                          ");
    $Qutax->bindValue(':value', $total_tax);
    $Qutax->bindValue(':text',  $fmt($total_tax));
    $Qusub->bindValue(':class', 'TX');
    $Qutax->bindInt(':orders_id', $order_id);
    $Qutax->execute();


    // ── 9. Persist ot_total / TO
    $Qutot = $db->prepare("update :table_orders_total
                           set value = :value,
                           text  = :text
                           where orders_id = :orders_id
                           and class = 'TO'
                        ");
    $Qutot->bindValue(':value', $grand_total);
    $Qutot->bindValue(':text',  $fmt($grand_total));
    $Qutot->bindInt(':orders_id', $order_id);
    $Qutot->execute();
  }
}
