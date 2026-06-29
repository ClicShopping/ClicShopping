<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin\SubProductAdmin;

use ClicShopping\OM\HTML;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

/**
 * ProductSaveDataBuilder Class
 *
 * Maps the admin product-edit form ($_POST) to the base `products` table row,
 * extracted verbatim from ProductsAdmin::save() (the worst NPath hotspot) as the
 * first step of the Products god-class decomposition. Self-contained: it only
 * reads $_POST and static helpers (no ProductsAdmin instance state). The caller
 * adds the file/image fields and persists the row.
 *
 * Responsibilities:
 * - Resolve the product model / SKU / EAN identifiers from the form
 * - Normalise the boolean/flag fields and assemble the base data array
 */
class ProductSaveDataBuilder
{
  use ProductsDebugTrait;

  /**
   * Builds the base `products` row from the submitted form (without the
   * file/image fields, which the caller adds).
   *
   * @return array The product data array ready to be augmented and saved
   */
  public function build(): array
  {
    if (isset($_POST['products_date_available']) && !empty($_POST['products_date_available'])) {
      $products_date_available = HTML::sanitize($_POST['products_date_available']);
      $products_date_available = (date('Y-m-d') < $products_date_available) ? $products_date_available : 'null';
    } else {
      $products_date_available = null;
    }

    if (isset($_POST['products_view']) && HTML::sanitize($_POST['products_view']) == 1) {
      $products_view = 1;
    } else {
      $products_view = 0;
    }

    if (isset($_POST['orders_view']) && HTML::sanitize($_POST['orders_view']) == 1) {
      $orders_view = 1;
    } else {
      $orders_view = 0;
    }

// display price / kg
    if (isset($_POST['products_price_kilo']) && HTML::sanitize($_POST['products_price_kilo']) == 1) {
      $products_price_kilo = 1;
    } else {
      $products_price_kilo = 0;
    }

// display products online
    if (isset($_POST['products_only_online']) && HTML::sanitize($_POST['products_only_online']) == 1) {
      $products_only_online = 1;
    } else {
      $products_only_online = 0;
    }

// display products store (physical)
    if (isset($_POST['products_only_shop']) && HTML::sanitize($_POST['products_only_shop']) == 1) {
      $products_only_shop = 1;
    } else {
      $products_only_shop = 0;
    }

// display products file public or private
    if (isset($_POST['products_download_public']) && HTML::sanitize($_POST['products_download_public']) == 1) {
      $products_download_public = 1;
    } else {
      $products_download_public = 0;
    }

// manual price B2B
    if (isset($_POST['products_percentage']) && $_POST['products_percentage'] == 'on') {
      $products_percentage = 0;
    } else {
      $products_percentage = 1;
    }

    if (MODE_B2B_B2C == 'False') {
      $products_view = 1;
      $orders_view = 1;
      $products_percentage = 1;
    }

    $products_model = $this->getProductModel();

    $products_sku = $this->getProductSKU();
    $products_ean = $this->getProductEAN();

    if (isset($_POST['products_status'])) {
      $products_status = HTML::sanitize($_POST['products_status']);
    } else {
      $products_status = 0;
    }

    $sql_data_array = [
      'products_quantity' => (int)HTML::sanitize($_POST['products_quantity'] ?? ''),
      'products_ean' => HTML::sanitize($products_ean),
      'products_model' => HTML::sanitize($products_model),
      'products_sku' => HTML::sanitize($products_sku),
      'products_price' => (float)HTML::sanitize($_POST['products_price'] ?? ''),
      'products_date_available' => $products_date_available,
      'products_weight' => (float)HTML::sanitize($_POST['products_weight'] ?? ''),
      'products_price_kilo' => HTML::sanitize($products_price_kilo),
      'products_status' => (int)HTML::sanitize($products_status),
      'products_percentage' => (int)$products_percentage,
      'products_view' => (int)$products_view,
      'orders_view' => (int)$orders_view,
      'products_tax_class_id' => (int)HTML::sanitize($_POST['products_tax_class_id'] ?? ''),
      'products_min_qty_order' => (int)($_POST['products_min_qty_order'] ?? ''),
      'admin_user_name' => AdministratorAdmin::getUserAdmin(),
      'products_only_online' => (int)HTML::sanitize($products_only_online),
      'products_cost' => (float)HTML::sanitize($_POST['products_cost'] ?? ''),
      'products_handling' => (float)HTML::sanitize($_POST['products_handling'] ?? ''),
      'products_packaging' => (int)HTML::sanitize($_POST['products_packaging'] ?? ''),
      'products_sort_order' => (int)HTML::sanitize($_POST['products_sort_order'] ?? ''),
      'products_quantity_alert' => (int)HTML::sanitize($_POST['products_quantity_alert'] ?? ''),
      'products_only_shop' => (int)HTML::sanitize($products_only_shop),
      'products_download_public' => (int)HTML::sanitize($products_download_public),
      'products_type' => HTML::sanitize($_POST['products_type'] ?? ''),
      'products_jan' => HTML::sanitize($_POST['products_jan'] ?? ''),
      'products_isbn' => HTML::sanitize($_POST['products_isbn'] ?? ''),
      'products_mpn' => HTML::sanitize($_POST['products_mpn'] ?? ''),
      'products_upc' => HTML::sanitize($_POST['products_upc'] ?? '')
    ];

    $this->debugLog('build', $sql_data_array);

    return $sql_data_array;
  }

  /**
   * Resolves the product model from the form, generating a random one (with the
   * optional CONFIGURATION_PREFIX_MODEL prefix) when none is provided.
   *
   * @return string The product model
   */
  public function getProductModel(): string
  {
    if (empty($_POST['products_model'])) {
      $model = HTML::generateRandomNumber(9); //create a random number

      $products_model = \defined('CONFIGURATION_PREFIX_MODEL') ? CONFIGURATION_PREFIX_MODEL . $model : '';
    } else {
      $products_model = HTML::sanitize($_POST['products_model']);
    }

    return $products_model;
  }

  /**
   * Retrieves the SKU of the product based on user input or default model value.
   * @return string The product SKU
   */
  public function getProductSKU(): string
  {
    if (empty($_POST['products_sku'])) {
      $products_sku = $this->getProductModel();
    } elseif ($_POST['products_sku'] != $this->getProductModel()) {
      $products_sku = HTML::sanitize($_POST['products_sku']);
    } else {
      $products_sku = $this->getProductModel();
    }

    return $products_sku;
  }

  /**
   * Retrieve the EAN of the product. If none is provided, uses the SKU as the
   * fallback. Sanitizes the provided EAN if it differs from the product SKU.
   *
   * @return string The EAN of the product.
   */
  public function getProductEAN(): string
  {
    if (empty($_POST['products_ean'])) {
      $products_ean = $this->getProductSKU();
    } elseif ($_POST['products_ean'] != $this->getProductSKU()) {
      $products_ean = HTML::sanitize($_POST['products_ean']);
    } else {
      $products_ean = $this->getProductSKU();
    }

    return $products_ean;
  }
}
