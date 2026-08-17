<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Apps;
use ClicShopping\OM\Hooks;
use ClicShopping\OM\Registry;
use ClicShopping\OM\OrderTotalRecalcContext;
use ClicShopping\OM\OrderTotalSequence;
use ClicShopping\OM\Interfaces\OrderTotalAdminRecalculable;

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
   * Re-runs the installed OrderTotal modules that implement {@see OrderTotalAdminRecalculable},
   * replacing their orders_total rows with amounts recomputed against the edited order. Driven by
   * MODULE_ORDER_TOTAL_INSTALLED so it is app-agnostic (OrderTotal, Marketing, custom) and open/closed
   * — a new module opts in by implementing the contract, no change here.
   *
   * A module is recomputed ONLY when the order already carries a row of its class (so it was applied
   * at checkout) — editing never adds a module the order never had. Modules that do not implement the
   * contract, or return null, keep their stored rows (handled by the caller's keep-value + sign path).
   *
   * @param mixed                   $db
   * @param int                     $order_id
   * @param array                   $meta      class → ['title','sort_order'] of the order's current rows.
   * @param OrderTotalRecalcContext $context
   * @return float Net tax the recomputed modules report as removed (a discount reports a negative).
   */
  private static function recalculateContractModules(mixed $db, int $order_id, array $meta, OrderTotalRecalcContext $context): float
  {
    if (!defined('MODULE_ORDER_TOTAL_INSTALLED') || MODULE_ORDER_TOTAL_INSTALLED === null) {
      return 0.0;
    }

    $has_sign  = self::ordersTotalHasSignColumn($db);
    $has_rank  = self::ordersTotalHasRankColumn($db);
    $tax_delta = 0.0;

    foreach (explode(';', (string)MODULE_ORDER_TOTAL_INSTALLED) as $moduleRef) {
      if (!str_contains($moduleRef, '\\')) {
        continue;
      }

      // The base, the tax and the grand total are computed by this method itself — never through
      // the module contract. Their FAMILY says so; no list of codes to keep in sync here.
      if (!OrderTotalSequence::isOptionalLine(OrderTotalSequence::rank(trim($moduleRef)))) {
        continue;
      }

      $class = Apps::getModuleClass($moduleRef, 'OrderTotal');
      if (empty($class) || !class_exists($class) || !is_subclass_of($class, OrderTotalAdminRecalculable::class)) {
        continue;
      }

      try {
        $module = new $class();
      } catch (\Throwable $e) {
        continue; // cannot instantiate admin-side → keep the stored rows (fallback)
      }

      $code = $module->code ?? '';

      // Only recompute a module that was actually applied to this order.
      if ($code === '' || !isset($meta[$code])) {
        continue;
      }

      $result = $module->recalculateForOrder($context);
      if ($result === null) {
        continue; // module declined to recompute → keep the stored rows (fallback)
      }

      $tax_delta += (float)($result['taxDelta'] ?? 0.0);

      $sort_order = $meta[$code]['sort_order'] ?? 0;
      $db->delete('orders_total', ['orders_id' => $order_id, 'class' => $code]);

      foreach (($result['rows'] ?? []) as $row) {
        $data = [
          'orders_id'  => $order_id,
          'class'      => $code,
          'title'      => $row['title'],
          'text'       => $row['text'],
          'value'      => (float)$row['value'],
          'sort_order' => $sort_order,
        ];
        if ($has_sign) {
          $data['total_sign'] = (int)($row['sign'] ?? 1);
        }
        // Carry the order's own rank forward: rewriting the row must not re-place it at whatever
        // position the configuration holds today. Always valued (0 = unknown) — see OrderWriter.
        if ($has_rank) {
          $data['total_rank'] = (int)($meta[$code]['total_rank'] ?? 0);
        }
        $db->save('orders_total', $data);
      }
    }

    return $tax_delta;
  }

  /**
   * Net move of the TAXABLE BASE by the rows computed BEFORE the tax: Σ (total_sign × value) over
   * the rows whose fiscal rank enters the base (reductions, shipping and other charges — see
   * OM\OrderTotalSequence). This is what makes the tax bear on the reduced base in the back office
   * exactly as it does at checkout.
   *
   * The rank stored ON THE ORDER wins, so re-editing an old order reproduces ITS sequence; a row
   * from before the rank column falls back to the rank its module declares today, and a row whose
   * module is gone contributes nothing rather than a guess.
   *
   * @param mixed $db
   * @param int   $order_id
   * @return float
   */
  private static function taxableBaseDelta(mixed $db, int $order_id): float
  {
    // An unknown rank NEVER moves the base: missing a move is a wrong amount, inventing one is a
    // wrong amount the merchant cannot explain.
    return self::sumRowsByRank($db, $order_id, static fn(?int $rank): bool => OrderTotalSequence::entersTaxableBase($rank));
  }

  /**
   * Σ (sign × value) over the orders_total rows whose fiscal rank satisfies $keep.
   *
   * The rank stored ON THE ORDER wins, so re-editing an old order reproduces ITS sequence; a row
   * written before the column existed falls back to the rank its module declares today, and a row
   * whose module is unknown has no rank at all — each caller says what to do with that.
   *
   * @param mixed    $db
   * @param int      $order_id
   * @param callable $keep  fn(?int $rank): bool
   * @return float
   */
  private static function sumRowsByRank(mixed $db, int $order_id, callable $keep): float
  {
    $has_sign = self::ordersTotalHasSignColumn($db);
    $has_rank = self::ordersTotalHasRankColumn($db);
    $declared = self::declaredByInstalledModules();

    $columns = 'class, value' . ($has_sign ? ', total_sign' : '') . ($has_rank ? ', total_rank' : '');
    $Q = $db->prepare("select $columns
                         from :table_orders_total
                        where orders_id = :orders_id
                      ");
    $Q->bindInt(':orders_id', $order_id);
    $Q->execute();

    $sum = 0.0;

    while ($Q->fetch()) {
      $module = $declared[$Q->value('class')] ?? null;

      $rank = $has_rank && $Q->valueInt('total_rank') > 0
        ? $Q->valueInt('total_rank')
        : ($module['rank'] ?? null);

      if (!$keep($rank)) {
        continue;
      }

      $sign = $has_sign ? $Q->valueInt('total_sign') : ($module['sign'] ?? 1);

      $sum += ($sign < 0 ? -1 : 1) * $Q->valueDecimal('value');
    }

    return $sum;
  }

  /**
   * What the INSTALLED order-total modules declare about themselves, keyed by module code:
   * the fiscal rank they compute at and the sign of the line they print.
   *
   * Read by reflection, never instantiated — reading a module is a diagnosis, not a run. This is
   * the fallback for rows written before the columns existed: what a module IS, is the module's
   * own declaration, never a list of codes maintained here (a shop's own module would be absent
   * from such a list and its discount would silently turn into a charge).
   *
   * @return array<string, array{rank: int|null, sign: int}>
   */
  private static function declaredByInstalledModules(): array
  {
    static $declared = null;

    if ($declared !== null) {
      return $declared;
    }

    $declared = [];

    if (!defined('MODULE_ORDER_TOTAL_INSTALLED') || MODULE_ORDER_TOTAL_INSTALLED === null) {
      return $declared;
    }

    foreach (explode(';', (string)MODULE_ORDER_TOTAL_INSTALLED) as $moduleRef) {
      $moduleRef = trim($moduleRef);

      if (!str_contains($moduleRef, '\\')) {
        continue;
      }

      $sign = 1;
      $class = Apps::getModuleClass($moduleRef, 'OrderTotal');

      if (\is_string($class) && class_exists($class)) {
        $sign = (int)((new \ReflectionClass($class))->getDefaultProperties()['total_sign'] ?? 1);
      }

      $declared[substr(strrchr($moduleRef, '\\'), 1)] = [
        'rank' => OrderTotalSequence::rank($moduleRef),
        'sign' => $sign,
      ];
    }

    return $declared;
  }

  /**
   * Net contribution of the optional OrderTotal rows (discounts / surcharges / fees / custom) to
   * the grand total: Σ (total_sign × value) over every row that is not a base component.
   *
   * The sign comes from the total_sign column when it exists, otherwise from what the module
   * declares. Stored values are never modified — this only reads them.
   *
   * @param mixed $db
   * @param int   $order_id
   * @return float
   */
  private static function sumOptionalTotalRows(mixed $db, int $order_id): float
  {
    // A row this platform cannot place (no rank, unknown module — e.g. a line imported from another
    // shop) is counted: it is on the invoice, so it is in the total. Only a row PROVEN structural
    // is left out, because the grand total is built from those separately.
    return self::sumRowsByRank(
      $db,
      $order_id,
      static fn(?int $rank): bool => $rank === null || OrderTotalSequence::isOptionalLine($rank)
    );
  }

  /**
   * Whether orders_total carries the total_sign column (added by the 4.33 migration). Cached per
   * request so the schema is probed at most once.
   *
   * @param mixed $db
   * @return bool
   */
  private static function ordersTotalHasSignColumn(mixed $db): bool
  {
    static $exists = null;

    if ($exists === null) {
      $Q = $db->prepare("SHOW COLUMNS FROM :table_orders_total LIKE 'total_sign'");
      $Q->execute();
      $exists = (bool)$Q->fetch();
    }

    return $exists;
  }

  /**
   * Whether orders_total carries the total_rank column (SQL-21 migration). Cached per request so
   * the schema is probed at most once.
   *
   * @param mixed $db
   * @return bool
   */
  private static function ordersTotalHasRankColumn(mixed $db): bool
  {
    static $exists = null;

    if ($exists === null) {
      $Q = $db->prepare("SHOW COLUMNS FROM :table_orders_total LIKE 'total_rank'");
      $Q->execute();
      $exists = (bool)$Q->fetch();
    }

    return $exists;
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
  /**
   * Whether orders carries the orders_prices_include_tax column yet (SQL-14 migration).
   * Cached for the request so we probe the schema at most once.
   *
   * @param mixed $db
   * @return bool
   */
  private static function ordersHasTaxConventionColumn(mixed $db): bool
  {
    static $exists = null;

    if ($exists === null) {
      $Q = $db->prepare("SHOW COLUMNS FROM :table_orders LIKE 'orders_prices_include_tax'");
      $Q->execute();
      $exists = (bool)$Q->fetch();
    }

    return $exists;
  }

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
   *   ST : Σ shown_price (HT or TTC, per the order's tax mode)
   *   TX : rebuilt — one row per distinct tax rate (incl. a 0% line
   *        for exempt products), mirroring cart()/TX's tax_groups
   *   SH : preserved as-is (not touched by line edits)
   *   TO : subtotal + Σ tax + shipping (or subtotal + shipping in TTC /
   *                        group-tax modes where the tax is embedded/not charged)
   *
   * HT vs TTC is decided from the ORDER's own customer group (group_tax) and the
   * global DISPLAY_PRICE_WITH_TAX — NOT from Registry::get('Customer') (empty in
   * back-office). In TTC mode the tax is back-calculated (tax = shown − shown/(1+rate)).
   *
   * Double-tax jurisdictions (Canada, DISPLAY_DOUBLE_TAXE == 'true') delegate the
   * GST/PST/HST split to the shared Tax::computeDoubleTaxRows(), the same helper the
   * checkout TX module uses, so both render identical tax lines.
   *
   * Optional module rows (discounts / surcharges / fees / custom) are included in the grand total
   * by their sign × value (+1 charge, -1 credit). A module that implements
   * {@see \ClicShopping\OM\Interfaces\OrderTotalAdminRecalculable} is re-run against the edited order
   * so percentage/option amounts (e.g. CustomerDiscount, Surcharge) rescale; others keep their stored
   * value (line-178 fallback). Driven by MODULE_ORDER_TOTAL_INSTALLED (app-agnostic), a module is only
   * recomputed when the order already carries a row of its class.
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

    //1. Read the order's currency + customer + its own tax convention
    $has_convention = self::ordersHasTaxConventionColumn($db);

    $Qorder = $db->prepare('select currency,
                                   currency_value,
                                   customers_id,
                                   customers_group_id'
      . ($has_convention ? ',
                                   orders_prices_include_tax' : '') . '
                              from :table_orders
                             where orders_id = :orders_id
                          ');
    $Qorder->bindInt(':orders_id', $order_id);
    $Qorder->execute();
    $Qorder->fetch();

    $currency       = $Qorder->value('currency') ?: 'EUR';
    $currency_value = $Qorder->valueDecimal('currency_value') ?: 1.0;
    $customers_id   = $Qorder->valueInt('customers_id');

    $stored_convention = $has_convention ? $Qorder->value('orders_prices_include_tax') : null;

    //2. Read the existing shipping row (preserved as-is)
    $Qship = $db->prepare("select value, 
                                   text, 
                                   title, 
                                   sort_order
                              from :table_orders_total
                             where orders_id = :orders_id
                               and class = 'SH'
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
    //      and, when the column exists, the fiscal rank the order was computed with, so re-editing
    //      reproduces ITS sequence instead of the configuration of the day.
    $has_rank = self::ordersTotalHasRankColumn($db);

    $Qexisting = $db->prepare('select class,
                                       title,
                                       sort_order' . ($has_rank ? ',
                                       total_rank' : '') . '
                                 from :table_orders_total
                                 where orders_id = :orders_id
                              ');
    $Qexisting->bindInt(':orders_id', $order_id);
    $Qexisting->execute();

    $meta = [];         // class → ['title' => ..., 'sort_order' => ..., 'total_rank' => ...]
    $stored_ranks = []; // class → fiscal rank stored on this order
    while ($Qexisting->fetch()) {
      $class = $Qexisting->value('class');

      $meta[$class] = [
        'title'      => $Qexisting->value('title'),
        'sort_order' => $Qexisting->valueInt('sort_order'),
      ];

      if ($has_rank && $Qexisting->valueInt('total_rank') > 0) {
        $meta[$class]['total_rank'] = $Qexisting->valueInt('total_rank');
        $stored_ranks[$class] = $Qexisting->valueInt('total_rank');
      }
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

    // The convention is a property of the ORDER, so the stored value wins: re-deriving it from
    // today's DISPLAY_PRICE_WITH_TAX silently converts a past order (and leaves its tax-line title
    // contradicting the new amounts). Falls back to the derivation only for orders placed before
    // the column existed — never from Registry::get('Customer'), empty in back-office.
    $decimals     = (int)($currencies->currencies[DEFAULT_CURRENCY]['decimal_places'] ?? 2);
    $order_is_ttc = ($stored_convention !== null && $stored_convention !== '')
      ? (bool)(int)$stored_convention
      : (((defined('DISPLAY_PRICE_WITH_TAX') && DISPLAY_PRICE_WITH_TAX == 'true') && $customers_group_id == 0)
        || ($customers_group_id != 0 && ($group_tax['group_tax'] ?? '') == 'true'));

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

      // Accumulate the tax per distinct rate so TX keeps one line per rate
      // (like Order::cart()'s tax_groups), instead of collapsing everything to one row.
      // Zero-rate lines are kept too: cart()/TX writes a "0%" line (value 0) for exempt products, so we mirror that instead of dropping it.
      $key = (string)$tax_rate;
      
      if (!isset($tax_by_rate[$key])) {
        $tax_by_rate[$key] = ['rate' => $tax_rate, 'tax' => 0.0];
      }
      $tax_by_rate[$key]['tax'] += $line_tax;
    }

    // ── 5b. HT base the tax bears on, taken BEFORE any module runs: on a tax-inclusive order the
    //        tax sits inside the subtotal, and applying the rates to it charges the tax twice.
    $taxable_base_ht = $order_is_ttc ? $subtotal - $total_tax : $subtotal;

    $double_taxe = defined('DISPLAY_DOUBLE_TAXE') && DISPLAY_DOUBLE_TAXE == 'true';
    $dt_rows     = null;

    // Resolve the order's delivery zone once — used by the double-tax split AND passed in the
    // recompute context (a module such as LowOrderFee needs the delivery country/zone).
    [$dt_country_id, $dt_zone_id] = self::resolveOrderZone($db, $order_id);

    // ─5c. Re-run installed OrderTotal modules that implement the admin-recalc contract, so
    //        percentage / option modules (e.g. CustomerDiscount) rescale against the EDITED order
    //        instead of staying frozen at their checkout amount. App-agnostic (MODULE_ORDER_TOTAL_
    //        INSTALLED). A module is only recomputed when the order already carries a row of its
    //        class (it was applied at checkout) — editing never introduces a module the order never
    //        had. Non-contract modules (e.g. session-only DiscountCoupon) keep their stored rows.
    //        The context carries the GROSS tax; modules report a taxDelta (a discount reduces it).
    $tax_delta = self::recalculateContractModules($db, $order_id, $meta, new OrderTotalRecalcContext(
      subtotal:          $subtotal,
      tax:               $total_tax,
      shipping:          $shipping_value,
      taxGroups:         [],
      currency:          $currency,
      currencyValue:     $currency_value,
      customersId:       $customers_id,
      customersGroupId:  $customers_group_id,
      deliveryCountryId: $dt_country_id,
      deliveryZoneId:    $dt_zone_id,
      decimals:          $decimals,
      orderId:           $order_id,
      pricesIncludeTax:  $order_is_ttc,
      storedRanks:       $stored_ranks
    ));

    // ── 5d. The split is computed AFTER the modules, on the base they leave: a reduction ranked
    //        before the tax lowers it, a charge raises it — the rows carry the rank they were
    //        computed at. A zone that declares no rate is not a split-tax jurisdiction and the
    //        normal per-rate lines are kept: the shop-wide setting must not erase an ordinary VAT.
    if ($double_taxe && $total_tax > 0) {
      $dt = Tax::computeDoubleTaxRows(
        $dt_country_id,
        $dt_zone_id,
        $taxable_base_ht + self::taxableBaseDelta($db, $order_id),
        ['taxable' => $total_tax], // single positive group = "there is taxable content"
        $currency,
        $currency_value
      );

      if ($dt['jurisdiction']) {
        $dt_rows   = $dt['rows'];
        $total_tax = $dt['tax_total']; // keep the grand total consistent with the split rows
      } else {
        $double_taxe = false;
      }
    }

    // A commercial discount is levied on the NET (post-discount) base, so it removes tax (taxDelta<0).
    // Apply it to the tax total AND proportionally to each rate row, so TX + TO match the checkout
    // invoice (tax on the net) instead of the gross. Non-double-tax path (TX rebuilt from tax_by_rate);
    // on the split path the reduced base above already did it.
    if (abs($tax_delta) > 0.0001 && $total_tax > 0 && !$double_taxe) {
      $net_tax = max(0.0, $total_tax + $tax_delta);
      $factor  = $net_tax / $total_tax;
      foreach ($tax_by_rate as $key => $group) {
        $tax_by_rate[$key]['tax'] = $group['tax'] * $factor;
      }
      $total_tax = $net_tax;
    }

    //6. Grand total — same branching as cart(), split or not: a tax-inclusive order already carries
    //   its tax in the subtotal, whatever the number of lines the tax is DISPLAYED on.
    if ($order_is_ttc
      || ($customers_group_id != 0 && ($group_tax['group_order_taxe'] ?? 0) == 1)) {
      $grand_total = $subtotal;
    } else {
      $grand_total = $subtotal + $total_tax;
    }

    // 6b. Every other line of the order — discounts, fees, surcharges, and the SHIPPING, which is a
    //        charge like any other. Each contributes total_sign × value (+1 charge, -1 credit), and
    //        which lines qualify comes from their fiscal rank, not from a list of class codes. The
    //        shipping used to be added separately here: two paths for one line, and the day the
    //        other path stopped excluding it, the order paid its delivery twice.
    $grand_total += self::sumOptionalTotalRows($db, $order_id);

    // ── 6. Format amounts using the order's currency
    $fmt = static function (float $v) use ($currencies, $currency, $currency_value): string {
      return $currencies->format($v, true, $currency, $currency_value);
    };

    // ── 7. Persist the subtotal / ST
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

    // ── 8. Persist the tax / TX rows — one row per distinct non-zero tax rate,
    //      exactly like Order::cart()/TX which emits one line per tax group.
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

    // Rewriting the tax lines must keep the rank the order was computed at — and value it even when
    // it is unknown: under STRICT_ALL_TABLES an omitted NOT NULL column loses the row silently.
    $tx_row = static function (string $title, string $text, float $value) use ($order_id, $tx_sort_order, $meta, $has_rank): array {
      $row = [
        'orders_id'  => $order_id,
        'class'      => 'TX',
        'title'      => $title,
        'text'       => $text,
        'value'      => $value,
        'sort_order' => $tx_sort_order,
      ];

      if ($has_rank) {
        $row['total_rank'] = (int)($meta['TX']['total_rank'] ?? 0);
      }

      return $row;
    };

    $db->delete('orders_total', ['orders_id' => $order_id, 'class' => 'TX']);

    if ($dt_rows !== null) {
      // Double-tax: one row per GST/PST/HST line, as produced by the shared helper.
      foreach ($dt_rows as $row) {
        $db->save('orders_total', $tx_row($row['title'], $row['text'], (float)$row['value']));
      }
    } else {
      // Single-tax: one row per distinct rate (incl. a 0% line for exempt products).
      foreach ($tax_by_rate as $group) {
        $db->save('orders_total', $tx_row(
          self::resolveTaxTitle($db, $group['rate'], $existing_tx_titles),
          $fmt($group['tax']),
          (float)$group['tax']
        ));
      }
    }

    // ── 9. Persist the grand total / TO
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
