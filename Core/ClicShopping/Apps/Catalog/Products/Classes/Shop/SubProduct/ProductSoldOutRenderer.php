<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Classes\Shop\SubProduct;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * ProductSoldOutRenderer Class
 *
 * Renders the "sold out" / pre-order display for a product based on its stock
 * level and the store stock/price configuration. Extracted verbatim from
 * ProductsCommon (setProductsSoldOut — cyclo 32 — + getProductButtonSoldOut) as
 * the first step of the front-office Products god-class decomposition. db +
 * customer come from the Registry; the product id is resolved by the caller (the
 * public getProductsSoldOut delegator) and passed in.
 *
 * Responsibilities:
 * - Decide, for a given product, whether/how a sold-out indicator is shown
 * - Build the sold-out button HTML
 */
class ProductSoldOutRenderer
{
  private mixed $db;
  private mixed $customer;

  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->customer = Registry::get('Customer');
  }

  /**
   * Determines the "sold out" representation of a product based on stock level
   * and configuration. The id is resolved by the caller.
   *
   * @param int $id The product id
   * @param string|null $button_type Optional button type
   * @return string The sold-out representation, or '' when none applies
   */
  public function setProductsSoldOut(int $id, $button_type = null): string
  {
    $product_sold_out = '';

    $QproductSoldOut = $this->db->prepare('select products_quantity
                                              from :table_products
                                              where products_id = :products_id
                                              and products_quantity < 1
                                             ');

    $QproductSoldOut->bindInt(':products_id', $id);
    $QproductSoldOut->execute();

    if ($QproductSoldOut->fetch()) {
      if (\defined('STOCK_CHECK') && STOCK_CHECK == 'true' && \defined('STOCK_ALLOW_CHECKOUT') && STOCK_ALLOW_CHECKOUT == 'false' && \defined('PRICES_LOGGED_IN') && PRICES_LOGGED_IN == 'false') {
        $product_sold_out = $this->getProductButtonSoldOut($button_type);
      } elseif (\defined('PRICES_LOGGED_IN') && PRICES_LOGGED_IN == 'true' && $this->customer->getCustomersGroupID() == 0 && !$this->customer->isLoggedOn() && \defined('STOCK_CHECK') && STOCK_CHECK == 'true' && \defined('STOCK_ALLOW_CHECKOUT') && STOCK_ALLOW_CHECKOUT == 'false') {
        $product_sold_out = ' ';
      } elseif (\defined('PRICES_LOGGED_IN') && PRICES_LOGGED_IN == 'true' && $this->customer->getCustomersGroupID() != 0 && \defined('STOCK_CHECK') && STOCK_CHECK == 'true' && \defined('STOCK_ALLOW_CHECKOUT') && STOCK_ALLOW_CHECKOUT == 'false') {
        $product_sold_out = $this->getProductButtonSoldOut($button_type);
      } elseif (\defined('PRICES_LOGGED_IN') && PRICES_LOGGED_IN == 'true' && $this->customer->getCustomersGroupID() == 0 && $this->customer->isLoggedOn() && \defined('STOCK_CHECK') && STOCK_CHECK == 'true' && \defined('STOCK_ALLOW_CHECKOUT') && STOCK_ALLOW_CHECKOUT == 'false') {
        $product_sold_out = $this->getProductButtonSoldOut($button_type);
      }
    }

    return $product_sold_out;
  }

  /**
   * Generates the HTML for a "sold out" button based on the specified or default
   * button type.
   *
   * @param string|null $button_type Optional CSS classes for the button.
   * @return string The HTML string for the "sold out" button, or '' when pre-order is disallowed.
   */
  private function getProductButtonSoldOut($button_type = null): string
  {
    $product_button_sold_out = '';

    if (is_null($button_type)) {
      $button_type = 'btn-warning btn-sm';
    }

    if (\defined('PRE_ORDER_AUTORISATION') && PRE_ORDER_AUTORISATION == 'false') {
      $product_button_sold_out = '<button type="button" class="btn ' . $button_type . '">' . CLICSHOPPING::getDef('button_sold_out') . '</button>';
    }

    return $product_button_sold_out;
  }
}
