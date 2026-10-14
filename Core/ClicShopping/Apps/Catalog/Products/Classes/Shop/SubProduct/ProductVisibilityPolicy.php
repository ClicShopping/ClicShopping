<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Classes\Shop\SubProduct;

/**
 * ProductVisibilityPolicy Class
 *
 * Pure, stateless decision policy shared by the front-office product display
 * helpers (buy button, quantity input, price-by-weight). It centralises the
 * recurring visibility gates — public/group order views, price-group view,
 * customer group and the PRICES_LOGGED_IN login rule — that were duplicated
 * and inlined across several ProductsCommon methods (cyclo/NPath hotspots).
 *
 * Extracted from ProductsCommon as part of the front-office Products
 * god-class decomposition. It takes primitives in and returns booleans only:
 * no instance state, no DB, no globals — fully unit-testable. Behaviour is
 * pinned verbatim against the original inline logic by an exhaustive oracle
 * test (unit_test/2026_06_28/product_visibility_policy_test.php).
 *
 * Responsibilities:
 * - Decide whether the buy button must be hidden for the current context
 */
class ProductVisibilityPolicy
{
  /**
   * Decide whether the "buy" button must be hidden for the current product/customer context.
   *
   * Mirrors verbatim the gate chain previously inlined in
   * ProductsCommon::setProductsBuyButton(): the button is suppressed when the
   * login rule forbids it for anonymous visitors, when the public/group order
   * view forbids ordering, or when the price-group view hides the offer.
   *
   * @param string|null $pricesLoggedIn Value of the PRICES_LOGGED_IN constant ('true'/'false') or null when undefined.
   * @param bool $isLoggedIn Whether the customer is currently logged on.
   * @param int $ordersView Product-level public orders_view flag (0 = forbidden).
   * @param int $ordersGroupView Customer-group orders_group_view flag (0 = forbidden).
   * @param int $priceGroupView Customer-group price_group_view flag (0 = hidden).
   * @param int $customersGroupId Current customer group id (0 = public/anonymous group).
   * @return bool True when the buy button must be rendered empty.
   */
  public function hidesBuyButton(
    ?string $pricesLoggedIn,
    bool $isLoggedIn,
    int $ordersView,
    int $ordersGroupView,
    int $priceGroupView,
    int $customersGroupId
  ): bool {
    if ($pricesLoggedIn === 'true' && !$isLoggedIn) {
      return true;
    }

    if ($this->isPublicOrderForbidden($ordersView, $customersGroupId)) {
      return true;
    }

    if ($isLoggedIn
        && ($pricesLoggedIn === 'true' || $pricesLoggedIn === 'false')
        && $this->isGroupOrderForbidden($ordersGroupView, $customersGroupId)) {
      return true;
    }

    return $this->isPriceGroupHidden($priceGroupView, $customersGroupId);
  }

  /**
   * Decide whether the cart quantity input must be rendered for the current
   * product/customer context (before the minimum-order and price-group
   * override gates, which the caller applies on instance/DB values).
   *
   * Mirrors verbatim the A/B branch chain previously inlined in
   * ProductsCommon::setProductsAllowingToInsertQuantity(): a group customer
   * sees the input when the group order view allows it; a public visitor sees
   * it when the public order view allows it AND the PRICES_LOGGED_IN login rule
   * is satisfied. The order-view flags come straight from instance state
   * (mixed, possibly '' / null), so loose comparisons are kept on purpose to
   * preserve the original semantics exactly.
   *
   * @param mixed $customersGroupId Current customer group id (0 = public/anonymous group).
   * @param mixed $ordersGroupView Customer-group orders_group_view flag.
   * @param mixed $ordersView Product-level public orders_view flag.
   * @param string|null $pricesLoggedIn Value of the PRICES_LOGGED_IN constant ('true'/'false') or null when undefined.
   * @param bool $isLoggedIn Whether the customer is currently logged on.
   * @return bool True when the quantity input must be rendered.
   */
  public function showsQuantityInput(
    mixed $customersGroupId,
    mixed $ordersGroupView,
    mixed $ordersView,
    ?string $pricesLoggedIn,
    bool $isLoggedIn
  ): bool {
    if ($customersGroupId != 0 && $ordersGroupView != 0) {
      return true;
    }

    if ($customersGroupId == 0 && $ordersView != 0) {
      if ($pricesLoggedIn == 'false') {
        return true;
      }

      if ($pricesLoggedIn == 'true' && $isLoggedIn) {
        return true;
      }
    }

    return false;
  }

  /**
   * Decide whether the price-by-weight (price/kilo) display must be hidden for
   * the current product/customer context.
   *
   * Mirrors verbatim the three gate overrides previously inlined at the tail of
   * ProductsCommon::setProductsPriceByWeight(): the price is suppressed for
   * anonymous visitors when the login rule requires it, when the
   * "do not display zero price" rule applies to a zero price, or when the
   * price-group view hides the offer. The actual price computation (currency
   * formatting, tax, weight division) stays in the caller. Values come from DB
   * rows / config constants (mixed, possibly '' / null), so loose comparisons
   * are kept on purpose to preserve the original semantics exactly.
   *
   * @param string|null $pricesLoggedIn Value of the PRICES_LOGGED_IN constant ('true'/'false') or null when undefined.
   * @param bool $isLoggedIn Whether the customer is currently logged on.
   * @param string|null $notDisplayPriceZero Value of the NOT_DISPLAY_PRICE_ZERO constant ('true'/'false') or null when undefined.
   * @param mixed $productsPrice The resolved product price (group price or base price).
   * @param mixed $priceGroupView Customer-group price_group_view flag.
   * @param mixed $customersGroupId Current customer group id (0 = public/anonymous group).
   * @return bool True when the price-by-weight display must be rendered empty.
   */
  public function hidesPriceByWeight(
    ?string $pricesLoggedIn,
    bool $isLoggedIn,
    ?string $notDisplayPriceZero,
    mixed $productsPrice,
    mixed $priceGroupView,
    mixed $customersGroupId
  ): bool {
    if ($pricesLoggedIn == 'true' && !$isLoggedIn) {
      return true;
    }

    if ($notDisplayPriceZero == 'false' && $productsPrice == 0) {
      return true;
    }

    if ($priceGroupView == 0 && $customersGroupId != 0) {
      return true;
    }

    return false;
  }

  /**
   * Decide whether the minimum-order-quantity value may be displayed for the
   * current context, mirroring verbatim the visibility branch of
   * ProductsCommon::setProductsMinimumQuantityToTakeAnOrder(): a group customer
   * sees it when the group order view allows it, a public visitor when the
   * public order view allows it, and never when the login rule hides prices
   * from an anonymous visitor. The caller still gates this on the actual
   * minimum quantity being >= 1 (a DB value). Order-view flags come from
   * instance state (mixed, possibly '' / null), so loose comparisons are kept
   * on purpose to preserve the original semantics exactly.
   *
   * @param mixed $ordersGroupView Customer-group orders_group_view flag.
   * @param mixed $ordersView Product-level public orders_view flag.
   * @param mixed $customersGroupId Current customer group id (0 = public/anonymous group).
   * @param string|null $pricesLoggedIn Value of the PRICES_LOGGED_IN constant ('true'/'false') or null when undefined.
   * @param bool $isLoggedIn Whether the customer is currently logged on.
   * @return bool True when the minimum-order quantity may be displayed.
   */
  public function allowsMinimumQuantityDisplay(
    mixed $ordersGroupView,
    mixed $ordersView,
    mixed $customersGroupId,
    ?string $pricesLoggedIn,
    bool $isLoggedIn
  ): bool {
    if ($pricesLoggedIn == 'true' && !$isLoggedIn) {
      return false;
    }

    if ($ordersGroupView != 0 && $customersGroupId != 0) {
      return true;
    }

    if ($ordersView != 0 && $customersGroupId == 0) {
      return true;
    }

    return false;
  }

  /**
   * Public (anonymous group) ordering is forbidden when the product order view
   * is off and the visitor belongs to the public group.
   *
   * @param int $ordersView Product-level public orders_view flag.
   * @param int $customersGroupId Current customer group id.
   * @return bool True when public ordering is forbidden.
   */
  private function isPublicOrderForbidden(int $ordersView, int $customersGroupId): bool
  {
    return $ordersView === 0 && $customersGroupId === 0;
  }

  /**
   * Group ordering is forbidden when the customer-group order view is off and
   * the visitor belongs to a non-public group.
   *
   * @param int $ordersGroupView Customer-group orders_group_view flag.
   * @param int $customersGroupId Current customer group id.
   * @return bool True when group ordering is forbidden.
   */
  private function isGroupOrderForbidden(int $ordersGroupView, int $customersGroupId): bool
  {
    return $ordersGroupView === 0 && $customersGroupId !== 0;
  }

  /**
   * The offer is hidden when the customer-group price view is off and the
   * visitor belongs to a non-public group.
   *
   * @param int $priceGroupView Customer-group price_group_view flag.
   * @param int $customersGroupId Current customer group id.
   * @return bool True when the price-group view hides the offer.
   */
  private function isPriceGroupHidden(int $priceGroupView, int $customersGroupId): bool
  {
    return $priceGroupView === 0 && $customersGroupId !== 0;
  }
}
