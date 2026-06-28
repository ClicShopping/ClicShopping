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
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

/**
 * ProductCloner Class
 *
 * Clones a product (and its images, descriptions, category link and customer-group
 * pricing) into other categories. Extracted verbatim from ProductsAdmin
 * (prepareCloneProducts + cloneProductsInOtherCategory — NPath 642) as part of the
 * Products god-class decomposition. Self-contained: db comes from the Registry.
 */
class ProductCloner
{
  use ProductsDebugTrait;

  private mixed $db;
  private mixed $hooks;

  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->hooks = Registry::get('Hooks');
  }

  /**
   * Prepares the cloning of products into other categories.
   *
   * @param int $id The ID of the product to be cloned.
   * @param int $categories_id The ID of the category or categories where the product will be cloned.
   *
   * @return void
   */
  public function prepareClone(int $id, int $categories_id): void
  {
    $new_category = $categories_id;

    if (is_array($new_category) && isset($new_category)) {
      foreach ($new_category as $value_id) {
        $this->cloneToCategory($id, $value_id);
      }
    }
  }

  /**
   * Clones a product into a specified category or multiple categories, including associated data such as attributes,
   * images, and descriptions. This ensures that the product is replicated in the desired category with all its
   * properties preserved.
   *
   * @param int $id The ID of the product to be cloned.
   * @param mixed $new_categories_id The ID of the category (or categories) where the product will be cloned. Can be an integer or string.
   *
   * @return void
   */
  public function cloneToCategory(int $id, mixed $new_categories_id): void
  {
    if (!is_numeric($new_categories_id)) {
      $new_categories_id = 0;
    }

    $multi_clone_categories_id_to = [];

    $multi_clone_categories_id_to[] = $new_categories_id;

    $Qproducts = $this->db->prepare('select *
                                      from :table_products
                                      where products_id = :products_id
                                     ');
    $Qproducts->bindInt(':products_id', $id);

    $Qproducts->execute();

    for ($i = 0, $iMax = count($multi_clone_categories_id_to); $i < $iMax; $i++) {
      $clone_categories_id_to = $multi_clone_categories_id_to[$i];

      $sql_array = [
        'parent_id' => (int)$Qproducts->valueInt('parent_id'),
        'has_children' => (int)$Qproducts->valueInt('has_children'),
        'products_quantity' => (int)$Qproducts->valueInt('products_quantity'),
        'products_model' => $Qproducts->value('products_model'),
        'products_ean' => $Qproducts->value('products_ean'),
        'products_sku' => $Qproducts->value('products_sku'),
        'products_jan' => $Qproducts->value('products_jan'),
        'products_isbn' => $Qproducts->value('products_isbn'),
        'products_mpn' => $Qproducts->value('products_mpn'),
        'products_upc' => $Qproducts->value('products_upc'),
        'products_image' => $Qproducts->value('products_image'),
        'products_image_zoom' => $Qproducts->value('products_image_zoom'),
        'products_price' => (float)$Qproducts->value('products_price'),
        'products_date_added' => 'now()',
        'products_date_available' => (empty($Qproducts->value('products_date_available')) ? "null" : "'" . $Qproducts->value('products_date_available') . "'"),
        'products_weight' => (float)$Qproducts->value('products_weight'),
        'products_price_kilo' => (float)$Qproducts->value('products_price_kilo'),
        'products_status' => $Qproducts->value('products_status'),
        'products_tax_class_id' => (int)$Qproducts->valueInt('products_tax_class_id'),
        'products_view' => (int)$Qproducts->valueInt('products_view'),
        'orders_view' => (int)$Qproducts->valueInt('orders_view'),
        'products_min_qty_order' => (int)$Qproducts->valueInt('products_min_qty_order'),
        'admin_user_name' => AdministratorAdmin::getUserAdmin(),
        'products_only_online' => (int)$Qproducts->valueInt('products_only_online'),
        'products_image_medium' => $Qproducts->value('products_image_medium'),
        'products_cost' => (float)$Qproducts->value('products_cost'),
        'products_handling' => (int)$Qproducts->value('products_handling'),
        'products_packaging' => (int)$Qproducts->valueInt('products_packaging'),
        'products_sort_order' => (int)$Qproducts->valueInt('products_sort_order'),
        'products_quantity_alert' => (int)$Qproducts->valueInt('products_quantity_alert'),
        'products_image_small' => $Qproducts->value('products_image_small'),
        'products_type' => $Qproducts->value('products_type')
      ];

// copy du produit
      $this->db->save('products', $sql_array);
      $dup_products_id = $this->db->lastInsertId();

      // ---------------------
      // gallery
      // ----------------------
      $QproductImage = $this->db->prepare('select *
                                            from :table_products_images
                                            where products_id = :products_id
                                          ');
      $QproductImage->bindInt(':products_id', $id);

      $QproductImage->execute();

      while ($QproductImage->fetch()) {
        $sql_array = [
          'products_id' => (int)$dup_products_id,
          'image' => $QproductImage->value('image'),
          'htmlcontent' => $QproductImage->value('htmlcontent'),
          'sort_order' => $QproductImage->valueInt('sort_order')
        ];

        $this->db->save('products_images', $sql_array);
      }

      // ---------------------
      // Description clonage
      // ----------------------
      $Qdescription = $this->db->prepare('select language_id,
                                                    products_name,
                                                    products_description,
                                                    products_description_summary,
                                                    products_seo_url,
                                                    products_head_title_tag,
                                                    products_head_desc_tag,
                                                    products_head_keywords_tag,
                                                    products_url,
                                                    products_head_tag,
                                                    products_shipping_delay,
                                                    products_shipping_delay_out_of_stock
                                             from :table_products_description
                                             where products_id = :products_id
                                            ');
      $Qdescription->bindInt(':products_id', $id);

      $Qdescription->execute();

      while ($Qdescription->fetch()) {
        $sql_array = [
          'products_id' => (int)$dup_products_id,
          'language_id' => (int)$Qdescription->valueInt('language_id'),
          'products_name' => $Qdescription->value('products_name'),
          'products_description' => $Qdescription->value('products_description'),
          'products_seo_url' => $Qdescription->value('products_seo_url'),
          'products_head_title_tag' => $Qdescription->value('products_head_title_tag'),
          'products_head_desc_tag' => $Qdescription->value('products_head_desc_tag'),
          'products_head_keywords_tag' => $Qdescription->value('products_head_keywords_tag'),
          'products_url' => $Qdescription->value('products_url'),
          'products_viewed' => 0,
          'products_head_tag' => $Qdescription->value('products_head_tag'),
          'products_shipping_delay' => $Qdescription->value('products_shipping_delay'),
          'products_shipping_delay_out_of_stock' => $Qdescription->value('products_shipping_delay_out_of_stock'),
          'products_description_summary' => $Qdescription->value('products_description_summary')
        ];

        $this->db->save('products_description', $sql_array);
      }

      // ---------------------
      // insertion table
      // ----------------------
      $sql_array = [
        'products_id' => (int)$dup_products_id,
        'categories_id' => (int)$clone_categories_id_to
      ];

      $this->db->save('products_to_categories', $sql_array);

      $clone_products_id = $dup_products_id;

      // ---------------------
      // groupe client clonage
      // ----------------------
      $QcustomersGroup = $this->db->prepare('select distinct customers_group_id,
                                                               customers_group_name,
                                                               customers_group_discount
                                               from :table_customers_groups
                                               where customers_group_id >  0
                                               order by customers_group_id
                                              ');
      $QcustomersGroup->execute();

      while ($QcustomersGroup->fetch()) {
        $Qattributes = $this->db->prepare('select g.customers_group_id,
                                                     g.customers_group_price,
                                                     p.products_price
                                              from :table_products_groups g,
                                                   :table_products p
                                              where p.products_id = :products_id
                                              and p.products_id =g.products_id
                                              and g.customers_group_id = :customers_group_id
                                              order by g.customers_group_id
                                            ');
        $Qattributes->bindInt(':products_id', (int)$clone_products_id);
        $Qattributes->bindInt(':customers_group_id', (int)$QcustomersGroup->valueInt('customers_group_id'));

        $Qattributes->execute();

        if ($Qattributes->rowCount() > 0) {
            // Definir la position 0 ou 1 pour --> Affichage Prix public + Affichage Produit + Autorisation Commande
            // L'Affichage des produits, autorisation de commander et affichage des prix mis par defaut en valeur 1 dans la cas de la B2B desactive.
          if (MODE_B2B_B2C == 'True') {
            if (HTML::sanitize($_POST['price_group_view' . $QcustomersGroup->valueInt('customers_group_id')]) == 1) {
              $price_group_view = 1;
            } else {
              $price_group_view = 0;
            }

            if (HTML::sanitize($_POST['products_group_view' . $QcustomersGroup->valueInt('customers_group_id')]) == 1) {
              $products_group_view = 1;
            } else {
              $products_group_view = 0;
            }

            if (HTML::sanitize($_POST['orders_group_view' . $QcustomersGroup->valueInt('customers_group_id')]) == 1) {
              $orders_group_view = 1;
            } else {
              $orders_group_view = 0;
            }

            $products_quantity_unit_id_group = HTML::sanitize($_POST['products_quantity_unit_id_group' . $QcustomersGroup->valueInt('customers_group_id')]);
            $products_model_group = HTML::sanitize($_POST['products_model_group' . $QcustomersGroup->valueInt('customers_group_id')]);
            $products_quantity_fixed_group = HTML::sanitize($_POST['products_quantity_fixed_group' . $QcustomersGroup->valueInt('customers_group_id')]);
          } else {
            $price_group_view = 1;
            $products_group_view = 1;
            $orders_group_view = 1;
            $products_quantity_unit_id_group = 0;
            $products_model_group = '';
            $products_quantity_fixed_group = 1;
          }

          $Qupdate = $this->db->prepare('update :table_products_groups
                                            set price_group_view = :price_group_view,
                                                products_group_view = :products_group_view,
                                                orders_group_view = :orders_group_view,
                                                products_quantity_unit_id_group = :products_quantity_unit_id_group,
                                                products_model_group = :products_model_group,
                                                products_quantity_fixed_group = :products_quantity_fixed_group
                                            where customers_group_id = :customers_group_id
                                            and products_id = :products_id
                                            ');
          $Qupdate->bindInt(':price_group_view', $price_group_view);
          $Qupdate->bindInt(':products_group_view', $products_group_view);
          $Qupdate->bindInt(':orders_group_view', $orders_group_view);
          $Qupdate->bindInt(':products_quantity_unit_id_group', $products_quantity_unit_id_group);
          $Qupdate->bindValue(':products_model_group', $products_model_group);
          $Qupdate->bindValue(':products_quantity_fixed_group', $products_quantity_fixed_group);
          $Qupdate->bindInt(':customers_group_id', (int)$Qattributes->valueInt('customers_group_id'));
          $Qupdate->bindInt(':products_id', (int)$clone_products_id);

          $Qupdate->execute();

          // Prix TTC B2B ----------
          if ($_POST['price' . $QcustomersGroup->valueInt('customers_group_id')] <> $Qattributes->valueDecimal('customers_group_price') && $Qattributes->valueInt('customers_group_id') == $QcustomersGroup->valueInt('customers_group_id')) {
            $Qupdate = $this->db->prepare('update :table_products_groups
                                             set customers_group_price = :customers_group_price,
                                                 products_price = :products_price
                                             where customers_group_id = :customers_group_id
                                             and products_id = :products_id
                                          ');
            $Qupdate->bindInt(':customers_group_price', $_POST['price' . $QcustomersGroup->valueInt('customers_group_id')]);
            $Qupdate->bindInt(':products_price', $_POST['products_price']);
            $Qupdate->bindInt(':customers_group_id', (int)$Qattributes->valueInt('customers_group_id'));
            $Qupdate->bindInt(':products_id', (int)$clone_products_id);

            $Qupdate->execute();
          } elseif ($_POST['price' . $QcustomersGroup->valueInt('customers_group_id')] == $Qattributes->valueInt('customers_group_id')) {
            //              $attributes = $Qattributes->fetch();
          }
        // Prix + Afficher Prix public + Afficher Produit + Autoriser Commande
        } elseif (is_array($_POST['price' . $QcustomersGroup->valueInt('customers_group_id')])) {
          if ($_POST['price' . $QcustomersGroup->valueInt('customers_group_id')] != '') {
            $sql_array = [
              'products_id' => (int)$clone_products_id,
              'products_price' => (float)$_POST['products_price'],
              'customers_group_id' => (int)$QcustomersGroup->valueInt('customers_group_id'),
              'customers_group_price' => (float)$_POST['price' . $QcustomersGroup->valueInt('customers_group_id')],
              'price_group_view' => (int)$_POST['price_group_view' . $QcustomersGroup->valueInt('customers_group_id')],
              'products_group_view' => (int)$_POST['products_group_view' . $QcustomersGroup->valueInt('customers_group_id')],
              'orders_group_view' => (int)$_POST['orders_group_view' . $QcustomersGroup->valueInt('customers_group_id')],
              'products_quantity_unit_id_group' => (int)$_POST['products_quantity_unit_id_group' . $QcustomersGroup->valueInt('customers_group_id')],
              'products_model_group' => $_POST['products_model_group' . $QcustomersGroup->valueInt('customers_group_id')],
              'products_quantity_fixed_group' => (int)$_POST['products_quantity_fixed_group' . $QcustomersGroup->valueInt('customers_group_id')],
            ];

            $this->db->save('products_groups', $sql_array);
          }
        }
      } // end while

      $this->hooks->call('Products', 'CloneProducts', ['clone_products_id' => $clone_products_id]);
    } //End for
  }
}
