<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Interfaces;

use ClicShopping\OM\OrderTotalRecalcContext;

/**
 * Opt-in contract that an OrderTotal module (any app) implements to be recomputed by the admin order
 * recalc (UpdateOrder::recalculateOrderTotal). It lets percentage / option-driven modules (e.g.
 * CustomerDiscount = customer %) recompute against the edited order instead of being kept at their
 * frozen checkout value.
 *
 * A module that does NOT implement this contract — or returns null — is handled by the caller's
 * keep-value + total_sign fallback (its stored orders_total rows are preserved). This keeps the loop
 * open/closed: a new module becomes admin-recalc-aware just by implementing the contract, no core edit.
 *
 * Implementations MUST be side-effect free: read only from the passed context (never $_SESSION,
 * Registry('Order') or Registry('Customer')), and MUST NOT mutate global state.
 */
interface OrderTotalAdminRecalculable
{
  /**
   * Recompute this module's orders_total line(s) from the order context.
   *
   * Returns both the display rows AND the module's net effect on the ORDER TAX (`taxDelta`): a
   * commercial discount reduces the taxable base, so it reports a NEGATIVE taxDelta (the tax it
   * removes) — the admin recalc applies it so the recomputed tax + total match the checkout
   * (tax on the net, not the gross). A module that does not touch the tax reports taxDelta = 0.
   *
   * @param OrderTotalRecalcContext $context Order figures + identity (no session/globals).
   * @return array{rows: array<int, array{title: string, text: string, value: float, sign: int}>, taxDelta: float}|null
   *         rows: recomputed lines (sign +1 charge / -1 credit), [] when no line for this order.
   *         taxDelta: change to apply to the order tax (<0 reduces tax; 0 = no tax effect).
   *         null: cannot be recomputed admin-side (caller keeps the stored rows).
   */
  public function recalculateForOrder(OrderTotalRecalcContext $context): ?array;
}
