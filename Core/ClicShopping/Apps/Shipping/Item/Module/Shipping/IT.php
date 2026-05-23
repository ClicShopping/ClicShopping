<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Shipping\Item\Module\Shipping;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Shipping\Item\Item as ItemApp;
use ClicShopping\Sites\Common\B2BCommon;

class IT implements \ClicShopping\OM\Modules\ShippingInterface
{
  public string $code;
  public $title;
  public $description;
  public $enabled = false;
  public $icon;
  public mixed $app;
  public $quotes;
  public $group;
  public $signature;
  public $public_title;
  protected $api_version;
  public $tax_class;
  public int|null $sort_order = 0;

  /**
   * Constructor method for the class.
   *
   * Initializes the required dependencies, sets up the module's properties, configurations,
   * and determines if the module is active and configured properly for specific customer groups
   * or geographic zones.
   *
   * @return void
   */
  public function __construct()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (Registry::exists('Order')) {
      $CLICSHOPPING_Order = Registry::get('Order');
    }

    if (!Registry::exists('Item')) {
      Registry::set('Item', new ItemApp());
    }

    $this->app = Registry::get('Item');
    $this->app->loadDefinitions('Module/Shop/IT/IT');

    $this->signature = 'Item|' . $this->app->getVersion() . '|1.0';
    $this->api_version = $this->app->getApiVersion();

    $this->code = 'IT';
    $this->title = $this->app->getDef('module_item_title');
    $this->public_title = $this->app->getDef('module_item_public_title');
    $this->sort_order = \defined('CLICSHOPPING_APP_ITEM_IT_SORT_ORDER') ? CLICSHOPPING_APP_ITEM_IT_SORT_ORDER : 0;

// Activation module du paiement selon les groupes B2B
    if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
      if (B2BCommon::getShippingUnallowed($this->code)) {
        if (CLICSHOPPING_APP_ITEM_IT_STATUS == 'True') {
          $this->enabled = true;
        } else {
          $this->enabled = false;
        }
      }
    } else {
      if (\defined('CLICSHOPPING_APP_ITEM_IT_NO_AUTHORIZE') && CLICSHOPPING_APP_ITEM_IT_NO_AUTHORIZE == 'True' && $CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
        if ($CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
          if (CLICSHOPPING_APP_ITEM_IT_STATUS == 'True') {
            $this->enabled = true;
          } else {
            $this->enabled = false;
          }
        }
      }
    }

    if (\defined('CLICSHOPPING_APP_ITEM_IT_TAX_CLASS')) {
      if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
        if (B2BCommon::getTaxUnallowed($this->code) || !$CLICSHOPPING_Customer->isLoggedOn()) {
          $this->tax_class = \defined('CLICSHOPPING_APP_ITEM_IT_TAX_CLASS') ? CLICSHOPPING_APP_ITEM_IT_TAX_CLASS : 0;

        }
      } else {
        if (B2BCommon::getTaxUnallowed($this->code)) {
          $this->tax_class = \defined('CLICSHOPPING_APP_ITEM_IT_TAX_CLASS') ? CLICSHOPPING_APP_ITEM_IT_TAX_CLASS : 0;
        }
      }
    }

    if (($this->enabled === true) && ((int)CLICSHOPPING_APP_ITEM_IT_ZONE > 0)) {
      $check_flag = false;

      $array = [
        'geo_zone_id' => (int)CLICSHOPPING_APP_ITEM_IT_ZONE,
        'zone_country_id' => $CLICSHOPPING_Order->delivery['country']['id']
      ];

      $Qcheck = $this->app->db->get('zones_to_geo_zones', 'zone_id', $array, 'zone_id');

      while ($Qcheck->fetch()) {
        if (($Qcheck->valueInt('zone_id') < 1) || ($Qcheck->valueInt('zone_id') === $CLICSHOPPING_Order->delivery['zone_id'])) {
          $check_flag = true;
          break;
        }
      }

      if ($check_flag === false) {
        $this->enabled = false;
      }
    }
  }

  /**
   * Retrieves and constructs the shipping quote information, including module details, tax,
   * handling cost, and optional icon representation, based on the current order and configuration.
   *
   * @param string $method Optional parameter specifying the shipping method. Defaults to an empty string.
   * @return array Returns the assembled quotes array containing shipping details, such as module id, title, methods, costs, tax, and optional icon.
   */
  public function quote($method = '')
  {
    $CLICSHOPPING_Order = Registry::get('Order');
    $CLICSHOPPING_Tax = Registry::get('Tax');
    $CLICSHOPPING_Template = Registry::get('Template');

    $number_of_items = $this->getNumberOfItems();

    $this->quotes = ['id' => $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code,
      'module' => $this->app->getDef('module_item_text_title'),
      'methods' => [
        [
          'id' => $this->code,
          'title' => $this->app->getDef('module_item_text_way'),
          'cost' => ((float)CLICSHOPPING_APP_ITEM_IT_COST * (int)$number_of_items) + (float)CLICSHOPPING_APP_ITEM_IT_HANDLING
        ]
      ]
    ];

    if ($this->tax_class > 0) {
      $this->quotes['tax'] = $CLICSHOPPING_Tax->getTaxRate($this->tax_class, $CLICSHOPPING_Order->delivery['country']['id'], $CLICSHOPPING_Order->delivery['zone_id']);
    }

    if (!empty(CLICSHOPPING_APP_ITEM_IT_LOGO)) {
      $this->icon = $CLICSHOPPING_Template->getDirectoryTemplateImages() . 'logos/shipping/' . CLICSHOPPING_APP_ITEM_IT_LOGO;
      $this->icon = HTML::image($this->icon, $this->title);
    } else {
      $this->icon = '';
    }

    if (!\is_null($this->icon)) $this->quotes['icon'] = '&nbsp;&nbsp;&nbsp;' . $this->icon;

    return $this->quotes;
  }

  /**
   * Checks if the constant 'CLICSHOPPING_APP_ITEM_IT_STATUS' is defined and not empty.
   *
   * @return bool Returns true if the constant is defined and its value is not an empty string, false otherwise.
   */
  public function check()
  {
    return \defined('CLICSHOPPING_APP_ITEM_IT_STATUS') && (trim(CLICSHOPPING_APP_ITEM_IT_STATUS) != '');
  }

  /**
   *
   * @return void
   */
  public function install()
  {
    $this->app->redirect('Configure&Install&module=Item');
  }

  /**
   * Redirects the application to the uninstall configuration page for a specified module.
   *
   * @return void
   */
  public function remove()
  {
    $this->app->redirect('Configure&Uninstall&module=Item');
  }

  /**
   * Retrieves the configuration keys for the application.
   *
   * @return array Returns an array of configuration keys.
   */
  public function keys()
  {
    return array('CLICSHOPPING_APP_ITEM_IT_SORT_ORDER');
  }

  /**
   * Calculates and returns the total number of items in the shopping cart.
   * If the order content type is 'mixed', it adjusts the count based on specific conditions,
   * including checking for product attributes and their associations with downloads.
   *
   * @return int The total number of items in the shopping cart.
   */
  public function getNumberOfItems()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Order = Registry::get('Order');
    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');

    $number_of_items = $CLICSHOPPING_ShoppingCart->getCountContents();

    if ($CLICSHOPPING_Order->content_type == 'mixed') {
      $number_of_items = 0;

      for ($i = 0, $n = \count($CLICSHOPPING_Order->products); $i < $n; $i++) {
        $number_of_items += $CLICSHOPPING_Order->products[$i]['qty'];

        if (isset($CLICSHOPPING_Order->products[$i]['attributes'])) {
          foreach ($CLICSHOPPING_Order->products[$i]['attributes'] as $option => $value) {
            $Qcheck = $CLICSHOPPING_Db->prepare('select pa.products_id
                                                  from :table_products_attributes pa,
                                                       :table_products_attributes_download pad
                                                  where pa.products_id = :products_id
                                                  and pa.options_values_id = :options_values_id
                                                  and pa.products_attributes_id = pad.products_attributes_id
                                                  ');
            $Qcheck->bindInt(':products_id', $CLICSHOPPING_Order->products[$i]['id']);
            $Qcheck->bindInt(':options_values_id', $value['value_id']);
            $Qcheck->execute();

            if ($Qcheck->fetch() !== false) {
              $number_of_items -= $CLICSHOPPING_Order->products[$i]['qty'];
            }
          }
        }
      }
    }

    return $number_of_items;
  }
}