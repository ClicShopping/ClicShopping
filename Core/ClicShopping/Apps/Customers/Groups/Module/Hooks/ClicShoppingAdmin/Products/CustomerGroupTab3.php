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

use ClicShopping\Apps\Configuration\ProductsQuantityUnit\Classes\ClicShoppingAdmin\ProductsQuantityUnitAdmin;
use ClicShopping\Apps\Customers\Groups\Groups as GroupsApp;

class CustomerGroupTab3 implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  protected $qteUnit;

  /**
   * Constructor method that initializes required registry objects and sets up class properties.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Groups')) {
      Registry::set('Groups', new GroupsApp());
    }

    if (!Registry::exists('ProductsQuantityUnitAdmin')) {
      Registry::set('ProductsQuantityUnitAdmin', new ProductsQuantityUnitAdmin());
    }

    $this->qteUnit = Registry::get('ProductsQuantityUnitAdmin');
    $this->app = Registry::get('Groups');
  }

  /**
   * Retrieves product details from the database based on the provided product ID (pID) in the GET request.
   *
   * @return array|false Returns an array of product details if a valid product ID is provided, or false otherwise.
   */
  protected function getProducts()
  {
    if (isset($_GET['pID'])) {
      $Qproducts = $this->app->db->prepare('select products_id,
                                                      products_percentage
                                              from :table_products
                                              where products_id =  :products_id
                                              ');
      $Qproducts->bindInt(':products_id', (int)$_GET['pID']);
      $Qproducts->execute();

      return $Qproducts->fetchAll();
    } else {
      return false;
    }
  }

  /**
   * Displays customer group pricing information and options within an admin interface.
   *
   * This method generates HTML content for managing group-specific pricing and other
   * related product attributes based on the configured parameters and existing database values.
   *
   * @return string The generated HTML content as a string representing the customer group display.
   */
  public function display(): string
  {
    $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

    if (!\defined('CLICSHOPPING_APP_CUSTOMERS_GROUPS_GR_STATUS') || CLICSHOPPING_APP_CUSTOMERS_GROUPS_GR_STATUS == 'False') {
      return false;
    }

    if (CLICSHOPPING_APP_CUSTOMERS_GROUPS_GR_STATUS == 'True' && !empty(CLICSHOPPING_APP_CUSTOMERS_GROUPS_GR_STATUS)) {
      $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/CustomerGroup/customer_group');

      $products_array = $this->getProducts();

      if (\is_array($products_array) && $products_array !== false) {
        $products_id = $products_array[0]['products_id'];
        $products_percentage = $products_array[0]['products_percentage'];
      }

      if (MODE_B2B_B2C == 'True') {
        if (!isset($products_percentage)) {
          $products_percentage = 1;
        }

        $products_quantity_unit_drop_down = $this->qteUnit->productsQuantityUnitDropDown();

        switch ($products_percentage) {
          case '0':
            $in_percent = false;
            $out_percent = true;
            break;
          case '1':
          default:
            $in_percent = true;
            $out_percent = false;
            break;
        }

        $QcustomersGroup = $this->app->db->prepare('select distinct customers_group_id,
                                                                        customers_group_name,
                                                                        customers_group_discount
                                                       from :table_customers_groups
                                                       where customers_group_id != 0
                                                       order by customers_group_id
                                                      ');
        $QcustomersGroup->execute();

        $header = false;

        if (B2B == 'true') {
          $override_on = $this->app->getDef('text_override_on');
        } else {
          $override_on = $this->app->getDef('text_override_on1');
        }

        if (!$header) {
          $header = true;
          $title = '<div class="mt-1"></div>';
          $title .= '<div class="col-md-12 mainTitle" style="height:30px;">';
          $title .= $this->app->getDef('text_cust_groups') . '&nbsp;&nbsp;&nbsp;&nbsp;' . HTML::radioField('products_percentage', '1', $in_percent, 'id="in_percent"') . '&nbsp;' . $override_on . '&nbsp;&nbsp;&nbsp;' . HTML::radioField('products_percentage', '0', $out_percent, 'id="out_percent"') . '&nbsp;' . $this->app->getDef('text_override_off');
          $title .= '</div>';
          $title .= '<div class="mt-1"></div>';
        }

        $content = '<div class="row">';

        while ($QcustomersGroup->fetch()) {
          $content .= '<div class="col-md-4 m-1">';
          $content .= '<div class="card cardPrice">';

          if ($QcustomersGroup->rowCount() > 0 && $products_array !== false) {
            $Qattributes = $this->app->db->prepare('select g.customers_group_id,
                                                                 g.customers_group_price,
                                                                 g.price_group_view,
                                                                 g.products_group_view,
                                                                 g.orders_group_view,
                                                                 p.products_price,
                                                                 p.products_id,
                                                                 g.products_quantity_unit_id_group,
                                                                 g.products_model_group,
                                                                 g.products_quantity_fixed_group
                                                          from :table_products_groups g,
                                                               :table_products p
                                                          where p.products_id = :products_id
                                                          and p.products_id = g.products_id
                                                          and g.customers_group_id = :customers_group_id
                                                          order by g.customers_group_id
                                                         ');
            $Qattributes->bindInt(':products_id', $products_id);
            $Qattributes->bindInt(':customers_group_id', $QcustomersGroup->valueInt('customers_group_id'));
            $Qattributes->execute();

            if ($Qattributes->fetch()) {
              $content_attibutes = HTML::inputField('price' . $QcustomersGroup->valueInt('customers_group_id'), $Qattributes->valueDecimal('customers_group_price'), 'onchange="updateGross()" placeholder="' . $this->app->getDef('tax_excluded') . '"') . '<strong>' . $this->app->getDef('tax_included') . '</strong>';
              $customers_group_price = $Qattributes->valueDecimal('customers_group_price');
              $price_group_view = $Qattributes->valueInt('price_group_view');
              $products_group_view = $Qattributes->valueInt('products_group_view');
              $orders_group_view = $Qattributes->valueInt('orders_group_view');
              $products_model_group = $Qattributes->value('products_model_group');
              $products_quantity_fixed_group = $Qattributes->valueInt('products_quantity_fixed_group');
              $products_quantity_unit_id_group = $Qattributes->valueInt('products_quantity_unit_id_group');
            } else {
              $content_attibutes = HTML::inputField('price' . $QcustomersGroup->valueInt('customers_group_id'), '0', 'onchange="updateGross()" size="7" placeholder="' . $this->app->getDef('tax_included') . '"') . '<strong>' . $this->app->getDef('tax_included') . '</strong>';
// Allow to display options
              $price_group_view = 1;
              $products_group_view = 1;
              $orders_group_view = 1;
              $products_quantity_fixed_group = 1;
              $products_model_group = '';
              $customers_group_price = 0;
              $products_quantity_unit_id_group = 0;
            }
          } else {
            $content_attibutes = HTML::inputField('price' . $QcustomersGroup->valueInt('customers_group_id'), '0', 'onchange="updateGross()" size="7" placeholder="' . $this->app->getDef('tax_included') . '"') . '<strong>' . $this->app->getDef('tax_included') . '</strong>';
// Allow to display options
            $price_group_view = 1;
            $products_group_view = 1;
            $orders_group_view = 1;
            $products_quantity_fixed_group = 1;
            $products_model_group = '';
            $customers_group_price = 0;
            $products_quantity_unit_id_group = 0;
          }

          $content .= '<div class="card-header">' . $QcustomersGroup->value('customers_group_name') . '</div>';
          $content .= '<div class="card-body">';

          $content .= '<div>';
          $content .= $QcustomersGroup->value('customers_group_name') . ' ';
          $content .= $content_attibutes;
          $content .= '<div class="mt-1"></div>';

          if (DISPLAY_DOUBLE_TAXE == 'false') {
            $content .= HTML::inputField('price_gross' . $QcustomersGroup->valueInt('customers_group_id'), $customers_group_price, 'onkeyUp="updateNet()" size="7"  placeholder="' . $this->app->getDef('tax_excluded') . '"') . '<strong>' . $this->app->getDef('tax_excluded') . '</strong>';
          }

          $content .= '<div class="mt-1"></div>';
          $content .= HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'icons/euro.png', $this->app->getDef('tab_price_group_view')) . ' ' . HTML::checkboxField('price_group_view' . $QcustomersGroup->valueInt('customers_group_id'), 1, $price_group_view) . '&nbsp;&nbsp;&nbsp;';
          $content .= HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'icons/last.png', $this->app->getDef('tab_products_group_view')) . ' ' . HTML::checkboxField('products_group_view' . $QcustomersGroup->valueInt('customers_group_id'), '1', $products_group_view) . '&nbsp;&nbsp;&nbsp;';
          $content .= HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'icons/orders-up.gif', $this->app->getDef('tab_orders_group_view')) . ' ' . HTML::checkboxField('orders_group_view' . $QcustomersGroup->valueInt('customers_group_id'), '1', $orders_group_view) . '<br /><br />';

          $content .= '<div class="mt-1"></div>';
          $content .= $this->app->getDef('text_products_model_group') . ' ' . HTML::inputField('products_model_group' . $QcustomersGroup->valueInt('customers_group_id'), $products_model_group);
          $content .= '<div class="mt-1"></div>';
          $content .= $this->app->getDef('text_products_quantity_fixed_group') . ' ' . HTML::inputField('products_quantity_fixed_group' . $QcustomersGroup->valueInt('customers_group_id'), $products_quantity_fixed_group);
          $content .= '<div class="mt-1"></div>';

          if (\defined('CLICSHOPPING_APP_PRODUCTS_QUANTITY_UNIT_PQ_STATUS') && CLICSHOPPING_APP_PRODUCTS_QUANTITY_UNIT_PQ_STATUS == 'True' && !empty(CLICSHOPPING_APP_PRODUCTS_QUANTITY_UNIT_PQ_STATUS)) {
            $content .= $this->app->getDef('text_products_min_order_quantity_group') . ' ' . HTML::selectMenu('products_quantity_unit_id_group' . $QcustomersGroup->valueInt('customers_group_id'), $products_quantity_unit_drop_down, $products_quantity_unit_id_group);
          }

          $content .= '</div>';
          $content .= '</div>';
          $content .= '</div>';
          $content .= '</div>';
        }

        $content .= '</div>';
        // help
        $content .= '<div class="mt-1"></div>';
        $content .= '<div class="alert alert-info">';
        $content .= '<div class="row">';
        $content .= '<span class="col-sm-12">';
        $content .= '<h4><i class="bi bi-question-circle" title="' . $this->app->getDef('title_help_price') . '"></i></h4>';
        $content .= '<strong>' . '&nbsp;' . $this->app->getDef('title_help_price') . '</strong>';
        $content .= '</span>';
        $content .= '</div>';
        $content .= '<div class="mt-1"></div>';

        $content .= '<div class="row">';
        $content .= '<span class="col-sm-12">';
        $content .= HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'icons/euro.png', $this->app->getDef('title_help_price'));
        $content .= '&nbsp;&nbsp;' . $this->app->getDef('help_price_group_view') . '<strong>*</strong>';
        $content .= '</span>';
        $content .= '<span class="col-sm-12">';
        $content .= HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'icons/last.png', $this->app->getDef('tab_products_group_view'));
        $content .= '&nbsp;&nbsp;' . $this->app->getDef('help_products_view');
        $content .= '</span>';
        $content .= '<span class="col-sm-12">';
        $content .= HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'icons/orders-up.gif', $this->app->getDef('tab_orders_group_view'));
        $content .= '&nbsp;&nbsp;' . $this->app->getDef('help_orders_view');
        $content .= '</span>';
        $content .= '</div>';
        $content .= '<div class="mt-1"></div>';
        $content .= '<div class="row">';
        $content .= '<span class="col-sm-12">&nbsp;<strong>' . '&nbsp;' . $this->app->getDef('help_others_group') . '</strong></span>';
        $content .= '</div>';
        $content .= '</div>';

        $output = <<<EOD
<!-- ######################## -->
<!--  Start CustomersGroup      -->
<!-- ######################## -->
         {$title}
         {$content}
<!-- ######################## -->
<!--  Start CustomersGroup      -->
<!-- ######################## -->
EOD;
        return $output;
      }
    }
  }
}
