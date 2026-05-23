<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Groups\Groups as GroupsApp;

class Save implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  protected $id;
  protected $currentCategoryId;

  /**
   * Constructor method for initializing the Groups application environment.
   *
   * It ensures the 'Groups' application instance is registered in the registry, retrieves
   * required product information, and sets the current category ID based on provided input.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Groups')) {
      Registry::set('Groups', new GroupsApp());
    }

    $this->app = Registry::get('Groups');

    if (isset($_GET['pID'])) {
      $this->id = HTML::sanitize($_GET['pID']);
    } else {
      $Qproducts = $this->app->db->prepare('select products_id
                                              from :table_products
                                              order by products_id desc 
                                              limit 1
                                            ');

      $Qproducts->execute();

      $this->id = $Qproducts->valueInt('products_id');
    }

    if (isset($_POST['current_category_id'])) {
      $this->currentCategoryId = HTML::sanitize($_POST['current_category_id']);
    } else {
      $this->currentCategoryId = 0;
    }
  }

  /**
   * Executes the processing of customer group pricing and discounts based on the specified product.
   * This method handles both the insertion and update of customer group data related to a product,
   * applying relevant business logic for discounts, pricing adjustments, and other attributes.
   *
   * @return void
   */
  public function execute()
  {
    $QcustomersGroup = $this->app->db->prepare('select customers_group_id,
                                                         customers_group_name,
                                                         customers_group_discount
                                                  from :table_customers_groups                                               
                                                ');

    $QcustomersGroup->execute();

    if (isset($_GET['Insert'], $_GET['Products'])) {
      $products_price = HTML::sanitize($_POST['products_price']);

      while ($QcustomersGroup->fetch()) {
        $Qdiscount = $this->app->db->prepare('select discount
                                                from :table_groups_to_categories
                                                where customers_group_id = :customers_group_id
                                                and categories_id = :categories_id
                                                ');
        $Qdiscount->bindInt(':categories_id', (int)$this->currentCategoryId);
        $Qdiscount->bindInt(':customers_group_id', $QcustomersGroup->valueInt('customers_group_id'));
        $Qdiscount->execute();

        if ($Qdiscount->rowCount() < 1) {
          $group_discount = $QcustomersGroup->valueDecimal('customers_group_discount');
        } else {
          $group_discount = $Qdiscount->value('discount');
        }

        $group_customer_price = @$_POST['price' . $QcustomersGroup->valueInt('customers_group_id')];
        
        // Sanitize and validate the price input - convert empty strings to null
        if ($group_customer_price === '' || $group_customer_price === null) {
          $group_customer_price = null;
        }

        if (MODE_B2B_B2C == 'False' && !isset($_POST['products_percentage'])) {
          $group_customer_price = $products_price;
        } elseif (isset($_POST['products_percentage'])) {
          if ($_POST['products_percentage'] == 1) {
            if ($products_price > 0) {
              if (B2B == 'true') {
                if ($group_discount > 0) {
                  $newprice = $products_price + ($products_price / 100) * $group_discount;
                } elseif ($group_discount == 0) {
                  $newprice = $products_price;
                }
              }

              if (B2B == 'false') {
                if ($group_discount > 0) {
                  $newprice = $products_price - ($products_price / 100) * $group_discount;
                }

                if ($group_discount == 0) {
                  $newprice = $products_price;
                }

                $group_customer_price = $newprice;
              }
            } else {
              $group_customer_price = $products_price;
            }
          } else {
            $group_customer_price = $products_price;
          }
        }

        if (isset($_POST['products_quantity_unit_id_group' . $QcustomersGroup->valueInt('customers_group_id')])) {
          $products_quantity_unit_id_group = HTML::sanitize($_POST['products_quantity_unit_id_group' . $QcustomersGroup->valueInt('customers_group_id')]);
        } else {
          $products_quantity_unit_id_group = 0;
        }

        // Final validation: ensure we never have empty string for decimal field
        if (empty($group_customer_price) || $group_customer_price === '') {
          $group_customer_price = $products_price;
        }

        if (isset($_POST['products_model_group' . $QcustomersGroup->valueInt('customers_group_id')])) {
          $products_model_group = HTML::sanitize($_POST['products_model_group' . $QcustomersGroup->valueInt('customers_group_id')]);
        } else {
          $products_model_group = '';
        }

        if (isset($_POST['price_group_view' . $QcustomersGroup->valueInt('customers_group_id')])) {
          $price_group_view = HTML::sanitize($_POST['price_group_view' . $QcustomersGroup->valueInt('customers_group_id')]);
        } else {
          $price_group_view = 1;
        }

        if (isset($_POST['orders_group_view' . $QcustomersGroup->valueInt('customers_group_id')])) {
          $orders_group_view = HTML::sanitize($_POST['orders_group_view' . $QcustomersGroup->valueInt('customers_group_id')]);
        } else {
          $orders_group_view = 1;
        }

        if (isset($_POST['products_group_view' . $QcustomersGroup->valueInt('customers_group_id')])) {
          $products_group_view = HTML::sanitize($_POST['products_group_view' . $QcustomersGroup->valueInt('customers_group_id')]);
        } else {
          $products_group_view = 1;
        }

        if (isset($_POST['products_quantity_fixed_group' . $QcustomersGroup->valueInt('customers_group_id')])) {
          $products_quantity_fixed_group = HTML::sanitize($_POST['products_quantity_fixed_group' . $QcustomersGroup->valueInt('customers_group_id')]);
        } else {
          $products_quantity_fixed_group = 0;
        }

        $sql_array = [
          'products_id' => (int)$this->id,
          'products_price' => (float)$products_price,
          'customers_group_id' => (int)$QcustomersGroup->valueInt('customers_group_id'),
          'customers_group_price' => (float)$group_customer_price,
          'price_group_view' => (int)$price_group_view,
          'products_group_view' => (int)$products_group_view,
          'orders_group_view' => (int)$orders_group_view,
          'products_quantity_unit_id_group' => (int)$products_quantity_unit_id_group,
          'products_model_group' => $products_model_group,
          'products_quantity_fixed_group' => (int)$products_quantity_fixed_group
        ];

        $this->app->db->save('products_groups', $sql_array);
      }
    } elseif (isset($_GET['Update'], $_GET['Products'])) {
      $products_price = HTML::sanitize($_POST['products_price']);

      while ($QcustomersGroup->fetch()) {
        $Qattributes = $this->app->db->prepare('select customers_group_id,
                                                         customers_group_price,
                                                         products_price
                                                 from :table_products_groups
                                                 where products_id = :products_id
                                                 and customers_group_id = :customers_group_id
                                                 order by customers_group_id
                                                ');

        $Qattributes->bindInt(':products_id', $this->id);

        $Qattributes->bindInt(':customers_group_id', $QcustomersGroup->valueInt('customers_group_id'));
        $Qattributes->execute();

        $Qdiscount = $this->app->db->prepare('select discount
                                              from :table_groups_to_categories
                                              where customers_group_id = :customers_group_id
                                              and categories_id = :categories_id
                                              ');
        $Qdiscount->bindInt(':categories_id', (int)$this->currentCategoryId);
        $Qdiscount->bindInt(':customers_group_id', $QcustomersGroup->valueInt('customers_group_id'));
        $Qdiscount->execute();

        if ($Qdiscount->rowCount() < 1) {
          $group_discount = $QcustomersGroup->valueDecimal('customers_group_discount');
        } else {
          $group_discount = $Qdiscount->value('discount');
        }

        $group_customer_price = @$_POST['price' . $QcustomersGroup->valueInt('customers_group_id')];
        
        // Sanitize and validate the price input - convert empty strings to null
        if ($group_customer_price === '' || $group_customer_price === null) {
          $group_customer_price = null;
        }

        if (MODE_B2B_B2C == 'False' && !isset($_POST['products_percentage'])) {
          $group_customer_price = $products_price;
        } elseif (isset($_POST['products_percentage'])) {
          if ($_POST['products_percentage'] == 1) {
            if ($products_price > 0) {
              if (B2B == 'true') {
                if ($group_discount > 0) {
                  $newprice = $products_price + ($products_price / 100) * $group_discount;
                }

                if ($group_discount == 0) {
                  $newprice = $products_price;
                }
              }

              if (B2B == 'false') {
                if ($group_discount > 0) {
                  $newprice = $products_price - ($products_price / 100) * $group_discount;
                }

                if ($group_discount == 0) {
                  $newprice = $products_price;
                }
              }
              // Prix TTC
              $group_customer_price = $newprice;
            } else {
              $group_customer_price = $products_price;
            }
          }
        } else {
          $group_customer_price = $Qattributes->valueDecimal('customers_group_price');
        }
        
        // Final validation: ensure we never have empty string for decimal field
        if (empty($group_customer_price) || $group_customer_price === '') {
          $group_customer_price = $products_price;
        }

// Gets all of the customers groups
        $Qattributes = $this->app->db->prepare('select g.customers_group_id,
                                                         g.customers_group_price,
                                                         p.products_price
                                                  from :table_products_groups g,
                                                       :table_products p
                                                  where p.products_id = :products_id
                                                  and p.products_id = g.products_id
                                                  and g.customers_group_id = :customers_group_id
                                                  order by g.customers_group_id
                                                  ');
        $Qattributes->bindInt(':products_id', $this->id);
        $Qattributes->bindInt(':customers_group_id', $QcustomersGroup->valueInt('customers_group_id'));
        $Qattributes->execute();

        if ($Qattributes->rowCount() > 0) {
// Definir la position 0 ou 1 pour --> Affichage Prix Public + Affichage Produit + Autorisation Commande
// L'Affichage des produits, autorisation de commander et affichage des prix mis par defaut en valeur 1 dans la cas de la B2B desactive.

          if (MODE_B2B_B2C == 'True') {
            $price_group_view = 0;

            if (isset($_POST['price_group_view' . $QcustomersGroup->valueInt('customers_group_id')])) {
              if (HTML::sanitize($_POST['price_group_view' . $QcustomersGroup->valueInt('customers_group_id')]) == 1) {
                $price_group_view = 1;
              } else {
                $price_group_view = 0;
              }
            }

            $products_group_view = 0;

            if (isset($_POST['products_group_view' . $QcustomersGroup->valueInt('customers_group_id')])) {
              if (HTML::sanitize($_POST['products_group_view' . $QcustomersGroup->valueInt('customers_group_id')]) == 1) {
                $products_group_view = 1;
              } else {
                $products_group_view = 0;
              }
            }

            $orders_group_view = 0;

            if (isset($_POST['orders_group_view' . $QcustomersGroup->valueInt('customers_group_id')])) {
              if (HTML::sanitize($_POST['orders_group_view' . $QcustomersGroup->valueInt('customers_group_id')]) == 1) {
                $orders_group_view = 1;
              } else {
                $orders_group_view = 0;
              }
            }

            if (isset($_POST['products_quantity_unit_id_group' . $QcustomersGroup->valueInt('customers_group_id')])) {
              $products_quantity_unit_id_group = $_POST['products_quantity_unit_id_group' . $QcustomersGroup->valueInt('customers_group_id')];
            } else {
              $products_quantity_unit_id_group = 0;
            }

            if (isset($_POST['products_model_group' . $QcustomersGroup->valueInt('customers_group_id')])) {
              $products_model_group = $_POST['products_model_group' . $QcustomersGroup->valueInt('customers_group_id')];
            } else {
              $products_model_group = '';
            }

            $products_quantity_fixed_group = 1;

            if (isset($_POST['products_quantity_fixed_group' . $QcustomersGroup->valueInt('customers_group_id')])) $products_quantity_fixed_group = $_POST['products_quantity_fixed_group' . $QcustomersGroup->valueInt('customers_group_id')];

          } else {
            $price_group_view = 1;
            $products_group_view = 1;
            $orders_group_view = 1;
            $products_quantity_unit_id_group = 0;
            $products_model_group = '';
            $products_quantity_fixed_group = 1;
          }

          $Qupdate = $this->app->db->prepare('update :table_products_groups
                                                set price_group_view = :price_group_view,
                                                    products_group_view = :products_group_view,
                                                    orders_group_view = :orders_group_view,
                                                    products_quantity_unit_id_group = :products_quantity_unit_id_group,
                                                    products_model_group= :products_model_group,
                                                    products_quantity_fixed_group= :products_quantity_fixed_group
                                                where customers_group_id = :customers_group_id
                                                and products_id = :products_id
                                              ');
          $Qupdate->bindInt(':price_group_view', $price_group_view);
          $Qupdate->bindInt(':products_group_view', $products_group_view);
          $Qupdate->bindInt(':orders_group_view', $orders_group_view);
          $Qupdate->bindInt(':products_quantity_unit_id_group', $products_quantity_unit_id_group);
          $Qupdate->bindValue(':products_model_group', $products_model_group);
          $Qupdate->bindInt(':products_quantity_fixed_group', $products_quantity_fixed_group);
          $Qupdate->bindInt(':customers_group_id', $Qattributes->valueInt('customers_group_id'));
          $Qupdate->bindInt(':products_id', $this->id);
          $Qupdate->execute();


          // Prix TTC B2B ----------
          if (($group_customer_price != $Qattributes->valueDecimal('customers_group_price')) && ($Qattributes->valueInt('customers_group_id') == $QcustomersGroup->valueInt('customers_group_id'))) {
            // Ensure group_customer_price is a valid decimal value
            if (empty($group_customer_price) || $group_customer_price === '') {
              $group_customer_price = $products_price;
            }
            
            // Convert to float to ensure valid decimal format
            $group_customer_price = (float)$group_customer_price;

            $this->app->db->save('products_groups', [
              'customers_group_price' => $group_customer_price,
              'products_price' => (float)HTML::sanitize($_POST['products_price']),
            ],
              ['products_id' => (int)$this->id,
                'customers_group_id' => $Qattributes->valueInt('customers_group_id')
              ]
            );
          }
        }
      }
    }
  }
}
