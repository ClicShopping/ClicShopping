<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

/**
 * Immutable order figures + identity handed to an OrderTotal module so it can recompute its
 * line(s) for an order being edited in back-office — WITHOUT reading the session or any global
 * ($_SESSION, Registry('Customer'), Registry('Order')). This is what lets a percentage/option
 * module (e.g. CustomerDiscount) recompute against the ORDER's customer instead of the empty
 * admin session.
 *
 * @see \ClicShopping\OM\Interfaces\OrderTotalAdminRecalculable
 */
final class OrderTotalRecalcContext
{
  public function __construct(
    public readonly float  $subtotal,
    public readonly float  $tax,
    public readonly float  $shipping,
    public readonly array  $taxGroups,
    public readonly string $currency,
    public readonly float  $currencyValue,
    public readonly int    $customersId,
    public readonly int    $customersGroupId,
    public readonly int    $deliveryCountryId = 0,
    public readonly int    $deliveryZoneId = 0,
    public readonly int    $decimals = 2,
    // Order being recomputed — lets a module reload its own persisted per-order state
    // (e.g. DiscountCoupon reads discount_coupons_to_orders to find the applied coupon).
    public readonly int    $orderId = 0,
    // Whether the order's prices are tax-included (DISPLAY_PRICE_WITH_TAX / group tax): drives the
    // HT base a discount is legally computed on.
    public readonly bool   $pricesIncludeTax = false,
  ) {}
}
