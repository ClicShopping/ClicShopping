<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin\SubProductAdmin;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

/**
 * ProductDescriptionSaver Class
 *
 * Persists the per-language `products_description` rows from the admin
 * product-edit form, extracted verbatim from ProductsAdmin::saveProductsDescription()
 * (a high-NPath hotspot — the ten per-field isset() ternaries) as part of the
 * Products god-class decomposition. Self-contained: db + language list come from
 * the Registry, like the parent class.
 *
 * Responsibilities:
 * - For each language, map the description form fields and insert/update the row
 */
class ProductDescriptionSaver
{
  private mixed $db;
  private mixed $lang;

  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->lang = Registry::get('Language');
  }

  /**
   * Save the description details of a product for every language.
   *
   * @param int $id The product id
   * @param string $action 'Insert' or 'Update'
   * @return void
   */
  public function save(int $id, string $action): void
  {
    $languages = $this->lang->getLanguages();

    for ($i = 0, $n = count($languages); $i < $n; $i++) {
      $language_id = $languages[$i]['id'];

      $sql_data_array = [
        'products_name' => HTML::sanitize($_POST['products_name'][$language_id]),
        'products_description' => $_POST['products_description'][$language_id] ?? '',
        'products_seo_url' => isset($_POST['products_seo_url'][$language_id]) ? HTML::sanitize(strip_tags($_POST['products_seo_url'][$language_id])) : '',
        'products_head_title_tag' => isset($_POST['products_head_title_tag'][$language_id]) ? HTML::sanitize(strip_tags($_POST['products_head_title_tag'][$language_id])) : '',
        'products_head_desc_tag' => isset($_POST['products_head_desc_tag'][$language_id]) ? HTML::sanitize($_POST['products_head_desc_tag'][$language_id]) : '',
        'products_head_keywords_tag' => isset($_POST['products_head_keywords_tag'][$language_id]) ? HTML::sanitize(strip_tags($_POST['products_head_keywords_tag'][$language_id])) : '',
        'products_url' => isset($_POST['products_url'][$language_id]) ? HTML::sanitize($_POST['products_url'][$language_id]) : '',
        'products_head_tag' => isset($_POST['products_head_tag'][$language_id]) ? HTML::sanitize(strip_tags($_POST['products_head_tag'][$language_id])) : '',
        'products_shipping_delay' => isset($_POST['products_shipping_delay'][$language_id]) ? HTML::sanitize($_POST['products_shipping_delay'][$language_id]) : '',
        'products_shipping_delay_out_of_stock' => isset($_POST['products_shipping_delay_out_of_stock'][$language_id]) ? HTML::sanitize($_POST['products_shipping_delay_out_of_stock'][$language_id]) : '',
        'products_description_summary' => isset($_POST['products_description_summary'][$language_id]) ? HTML::sanitize(strip_tags($_POST['products_description_summary'][$language_id])) : '',
      ];

      if (is_numeric($id) && $action == 'Insert') {
        $insert_sql_data = [
          'products_id' => (int)$id,
          'language_id' => (int)$language_id
        ];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $this->db->save('products_description', $sql_data_array);
//update products
      } else {
        $update_sql_data = [
          'products_id' => (int)$id,
          'language_id' => (int)$language_id
        ];

        $this->db->save('products_description', $sql_data_array, $update_sql_data);
      } // end action
    } //end for
  }
}
