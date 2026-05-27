<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\ProductsAttributes\Classes\ClicShoppingAdmin;

use ClicShopping\Apps\Catalog\ProductsAttributes\ProductsAttributes as ProductsAttributesApp;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

/**
 * ProductsAttributesInlineAdmin centralises the persistence logic shared
 * by the Insert and Update hooks of the inline attributes tab. It walks
 * the posted rows array and performs per-row insert / update / delete,
 * including the optional per-row image upload.
 */
class ProductsAttributesInlineAdmin
{
  public mixed $app;

  public function __construct()
  {
    if (!Registry::exists('ProductsAttributes')) {
      Registry::set('ProductsAttributes', new ProductsAttributesApp());
    }

    $this->app = Registry::get('ProductsAttributes');
  }

  /**
   * Walk $_POST['products_attributes_inline'] and persist each row against
   * the given products_id. A no-op when the form did not include the tab.
   */
  public function savePostedRows(int $products_id): void
  {
    if ($products_id <= 0) {
      return;
    }

    if (empty($_POST['products_attributes_inline']) || !\is_array($_POST['products_attributes_inline'])) {
      return;
    }

    foreach ($_POST['products_attributes_inline'] as $index => $row) {
      if (!\is_array($row)) {
        continue;
      }

      $this->processRow($products_id, (string)$index, $row);
    }
  }

  /**
   * Decide insert / update / delete for one posted row.
   *
   * @param array<string, mixed> $row
   */
  public function processRow(int $products_id, string $index, array $row): void
  {
    $id = (int)($row['id'] ?? 0);
    $deleted = !empty($row['_delete']) && (string)$row['_delete'] !== '0';

    if ($deleted) {
      if ($id > 0) {
        $this->app->db->delete('products_attributes', [
          'products_attributes_id' => $id,
          'products_id' => $products_id,
        ]);
      }
      return;
    }

    $options_id = (int)($row['options_id'] ?? 0);
    $values_id = (int)($row['options_values_id'] ?? 0);

    if ($options_id <= 0 || $values_id <= 0) {
      return;
    }

    $image = (string)($row['image_existing'] ?? '');
    $file_field = 'products_attributes_inline_image_' . $index;

    if (isset($_FILES[$file_field]) && !empty($_FILES[$file_field]['tmp_name'])) {
      $admin = new ProductsAttributesAdmin();
      $uploaded = $admin->uploadImage($file_field);
      if (!empty($uploaded)) {
        $image = $uploaded;
      }
    }

    $price_prefix = ($row['price_prefix'] ?? '+') === '-' ? '-' : '+';
    $status = (int)($row['status'] ?? 1) === 1 ? 1 : 0;

    $data = [
      'products_id' => $products_id,
      'options_id' => $options_id,
      'options_values_id' => $values_id,
      'options_values_price' => (float)($row['value_price'] ?? 0),
      'price_prefix' => $price_prefix,
      'products_options_sort_order' => (int)($row['sort_order'] ?? 1),
      'products_attributes_reference' => HTML::sanitize((string)($row['reference'] ?? '')),
      'customers_group_id' => (int)($row['customers_group_id'] ?? 0),
      'products_attributes_image' => $image !== '' ? $image : null,
      'status' => $status,
    ];

    if ($id > 0) {
      $this->app->db->save('products_attributes', $data, [
        'products_attributes_id' => $id,
        'products_id' => $products_id,
      ]);
    } else {
      $this->app->db->save('products_attributes', $data);
    }
  }
}
