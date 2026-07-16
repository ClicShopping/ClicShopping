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

use ClicShopping\Sites\Shop\Tax;

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
   * Resolves the display title for a TX (tax) line of a given rate.
   *
   * Prefers an existing TX row title that already carries the rate's canonical
   * description (keeps the exact label the shop wrote, tag included); otherwise
   * falls back to the tax_rates description, then to a formatted percentage.
   *
   * @param mixed  $db
   * @param float  $rate            Tax rate as a percentage (e.g. 20.0).
   * @param array  $existingTitles  Titles of the order's current TX rows.
   * @return string
   */
  private static function resolveTaxTitle(mixed $db, float $rate, array $existingTitles): string
  {
    $Qdesc = $db->prepare('select tax_description
                             from :table_tax_rates
                            where tax_rate = :rate
                            limit 1
                          ');
    $Qdesc->bindValue(':rate', $rate);
    $Qdesc->execute();
    $desc = $Qdesc->fetch() ? (string)$Qdesc->value('tax_description') : '';

    if ($desc !== '') {
      foreach ($existingTitles as $title) {
        if (stripos((string)$title, $desc) !== false) {
          return (string)$title;
        }
      }

      return $desc;
    }

    return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') . '%';
  }

  /**
   * Resolves an order's delivery (country_id, zone_id) from the stored delivery
   * country/state NAMES — needed to feed Tax::computeDoubleTaxRows() for double-tax
   * (Canada) jurisdictions. The orders table keeps only the textual names, so we map
   * them back to ids the same way the checkout does when the zone id is unknown.
   *
   * @param mixed $db
   * @param int   $order_id
   * @return array{0:int,1:int}  [country_id, zone_id] — either may be 0 if unresolved.
   */
  private static function resolveOrderZone(mixed $db, int $order_id): array
  {
    $Qorder = $db->prepare('select delivery_country, delivery_state
                              from :table_orders
                             where orders_id = :orders_id
                             limit 1
                          ');
    $Qorder->bindInt(':orders_id', $order_id);
    $Qorder->execute();
    $Qorder->fetch();

    $country_name = (string)$Qorder->value('delivery_country');
    $state_name   = (string)$Qorder->value('delivery_state');

    $country_id = 0;
    if ($country_name !== '') {
      $Qc = $db->prepare('select countries_id from :table_countries where countries_name = :name limit 1');
      $Qc->bindValue(':name', $country_name);
      $Qc->execute();
      if ($Qc->fetch()) {
        $country_id = $Qc->valueInt('countries_id');
      }
    }

    $zone_id = 0;
    if ($country_id > 0 && $state_name !== '') {
      $Qz = $db->prepare('select zone_id from :table_zones where zone_name = :name and zone_country_id = :country limit 1');
      $Qz->bindValue(':name', $state_name);
      $Qz->bindInt(':country', $country_id);
      $Qz->execute();
      if ($Qz->fetch()) {
        $zone_id = $Qz->valueInt('zone_id');
      }
    }

    return [$country_id, $zone_id];
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
   *   ot_subtotal / ST  : Σ shown_price (HT or TTC, per the order's tax mode)
   *   ot_tax     / TX   : rebuilt — one row per distinct tax rate (incl. a 0% line
   *                        for exempt products), mirroring cart()/ot_tax's tax_groups
   *   ot_shipping / SH  : preserved as-is (not touched by line edits)
   *   ot_total   / TO   : subtotal + Σ tax + shipping (or subtotal + shipping in TTC /
   *                        group-tax modes where the tax is embedded/not charged)
   *
   * HT vs TTC is decided from the ORDER's own customer group (group_tax) and the
   * global DISPLAY_PRICE_WITH_TAX — NOT from Registry::get('Customer') (empty in
   * back-office). In TTC mode the tax is back-calculated (tax = shown − shown/(1+rate)).
   *
   * Double-tax jurisdictions (Canada, DISPLAY_DOUBLE_TAXE == 'true') delegate the
   * GST/PST/HST split to the shared Tax::computeDoubleTaxRows(), the same helper the
   * checkout ot_tax module uses, so both render identical tax lines.
   *
   * Rows whose class is not ST/TX/SH/TO (e.g. discount coupons, custom modules) are
   * left untouched so no data is lost.
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
                                   currency_value,
                                   customers_group_id
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

    // ── 4. Customer group of the order — drives the same tax/total branching as cart() ──
    $customers_group_id = $Qorder->valueInt('customers_group_id');
    $group_tax = false;

    if ($customers_group_id != 0) {
      $QgroupTax = $db->prepare('select group_order_taxe,
                                        group_tax
                                   from :table_customers_groups
                                  where customers_group_id = :customers_group_id
                                ');
      $QgroupTax->bindInt(':customers_group_id', $customers_group_id);
      $QgroupTax->execute();
      $group_tax = $QgroupTax->fetch();
    }

    // ── 5. Read the product lines and mirror Order::cart()'s per-line computation ──
    //      The subtotal is the sum of TTC "shown" line totals (addTax × qty), exactly
    //      like the shop — so admin-recomputed totals equal the checkout totals.
    $Qlines = $db->prepare('select final_price,
                                   products_quantity,
                                   products_tax
                              from :table_orders_products
                             where orders_id = :orders_id
                          ');
    $Qlines->bindInt(':orders_id', $order_id);
    $Qlines->execute();

    $subtotal    = 0.0;
    $total_tax   = 0.0;
    $tax_by_rate = [];   // (string)rate => ['rate' => float, 'tax' => float] — one entry per distinct non-zero rate

    // Decide HT vs TTC from the ORDER's own customer group — NOT from  Registry::get('Customer')
    $decimals     = (int)($currencies->currencies[DEFAULT_CURRENCY]['decimal_places'] ?? 2);
    $order_is_ttc = ((defined('DISPLAY_PRICE_WITH_TAX') && DISPLAY_PRICE_WITH_TAX == 'true') && $customers_group_id == 0)
      || ($customers_group_id != 0 && ($group_tax['group_tax'] ?? '') == 'true');

    while ($Qlines->fetch()) {
      $unit_price = $Qlines->valueDecimal('final_price'); // stored HT
      $qty        = $Qlines->valueInt('products_quantity');
      $tax_rate   = $Qlines->valueDecimal('products_tax');

      // Line total in the order's own tax mode — identical to Tax::addTax()'s TTC/HT branches, but driven by the order group instead of the session customer.
      $line_unit = $order_is_ttc
        ? round($unit_price, $decimals) + Tax::calculate($unit_price, $tax_rate)
        : round($unit_price, $decimals);
      $shown_price = $line_unit * $qty;
      $subtotal += $shown_price;

      if ($order_is_ttc) {
        // TTC: back-calculate the embedded tax (same expression as cart()).
        $line_tax = $shown_price - ($shown_price / (float)(($tax_rate < 10) ? '1.0' . str_replace('.', '', (string)$tax_rate) : '1.' . str_replace('.', '', (string)$tax_rate)));
      } else {
        // HT: tax added on top.
        $line_tax = ($tax_rate / 100) * $shown_price;
      }

      $total_tax += $line_tax;

      // Accumulate the tax per distinct rate so ot_tax/TX keeps one line per rate
      // (like Order::cart()'s tax_groups), instead of collapsing everything to one row.
      // Zero-rate lines are kept too: cart()/ot_tax writes a "0%" line (value 0) for exempt products, so we mirror that instead of dropping it.
      $key = (string)$tax_rate;
      
      if (!isset($tax_by_rate[$key])) {
        $tax_by_rate[$key] = ['rate' => $tax_rate, 'tax' => 0.0];
      }
      $tax_by_rate[$key]['tax'] += $line_tax;
    }

    // ── 5b. Double-tax jurisdictions (Canada GST/PST/HST) — delegate the split to the  shared Tax::computeDoubleTaxRows() so the admin renders exactly the same
    //        tax lines as the checkout ot_tax module. Only when there is taxable content.
    $double_taxe = defined('DISPLAY_DOUBLE_TAXE') && DISPLAY_DOUBLE_TAXE == 'true';
    $dt_rows     = null;

    if ($double_taxe && $total_tax > 0) {
      [$dt_country_id, $dt_zone_id] = self::resolveOrderZone($db, $order_id);
      $dt = Tax::computeDoubleTaxRows(
        $dt_country_id,
        $dt_zone_id,
        $subtotal,
        $shipping_value,
        ['taxable' => $total_tax], // single positive group = "there is taxable content"
        $currency,
        $currency_value
      );
      $dt_rows   = $dt['rows'];
      $total_tax = $dt['tax_total']; // keep the grand total consistent with the split rows
    }

    // ── 6. Grand total — same branching as cart(). Double-tax always adds tax on top (Order::info['total'] = subtotal + tax + shipping), like ot_tax does.
    if ($double_taxe) {
      $grand_total = $subtotal + $total_tax + $shipping_value;
    } elseif ($order_is_ttc
      || ($customers_group_id != 0 && ($group_tax['group_order_taxe'] ?? 0) == 1)) {
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

    // ── 8. Persist ot_tax / TX rows — one row per distinct non-zero tax rate,
    //      exactly like Order::cart()/ot_tax which emits one line per tax group.
    //      The former code UPDATE-ed EVERY TX row to the aggregate, so a multi-rate
    //      order had each tax line inflated to the full total. We rebuild the rows
    //      from the per-rate breakdown; $total_tax still drives the grand total.
    $Qtitles = $db->prepare("select title
                               from :table_orders_total
                              where orders_id = :orders_id
                                and class = 'TX'
                            ");
    $Qtitles->bindInt(':orders_id', $order_id);
    $Qtitles->execute();
    $existing_tx_titles = [];
    while ($Qtitles->fetch()) {
      $existing_tx_titles[] = $Qtitles->value('title');
    }

    $tx_sort_order = $meta['TX']['sort_order'] ?? 0;

    $db->delete('orders_total', ['orders_id' => $order_id, 'class' => 'TX']);

    if ($dt_rows !== null) {
      // Double-tax: one row per GST/PST/HST line, as produced by the shared helper.
      foreach ($dt_rows as $row) {
        $db->save('orders_total', [
          'orders_id'  => $order_id,
          'class'      => 'TX',
          'title'      => $row['title'],
          'text'       => $row['text'],
          'value'      => $row['value'],
          'sort_order' => $tx_sort_order,
        ]);
      }
    } else {
      // Single-tax: one row per distinct rate (incl. a 0% line for exempt products).
      foreach ($tax_by_rate as $group) {
        $db->save('orders_total', [
          'orders_id'  => $order_id,
          'class'      => 'TX',
          'title'      => self::resolveTaxTitle($db, $group['rate'], $existing_tx_titles),
          'text'       => $fmt($group['tax']),
          'value'      => $group['tax'],
          'sort_order' => $tx_sort_order,
        ]);
      }
    }

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
