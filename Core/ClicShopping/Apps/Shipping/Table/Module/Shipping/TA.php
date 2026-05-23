<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Shipping\Table\Module\Shipping;

use ClicShopping\Apps\Shipping\Table\Table as TableApp;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Common\B2BCommon;

class TA implements \ClicShopping\OM\Modules\ShippingInterface
{
  public string $code;
  public $title;
  public $description;
  public $enabled = false;
  public $icon;
  public mixed $app;
  public $quotes;
  public $signature;
  public $public_title;
  public $api_version;
  public int|null $sort_order = 0;
  public $tax_class;
  public $group;

  public function __construct()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (Registry::exists('Order')) {
      $CLICSHOPPING_Order = Registry::get('Order');
    }

    if (!Registry::exists('Table')) {
      Registry::set('Table', new TableApp());
    }

    $this->app = Registry::get('Table');
    $this->app->loadDefinitions('Module/Shop/TA/TA');

    $this->signature = 'Table|' . $this->app->getVersion() . '|1.0';
    $this->api_version = $this->app->getApiVersion();

    $this->code = 'TA';
    $this->title = $this->app->getDef('module_table_title');
    $this->public_title = $this->app->getDef('module_table_public_title');
    $this->sort_order = \defined('CLICSHOPPING_APP_TABLE_TA_SORT_ORDER') ? CLICSHOPPING_APP_TABLE_TA_SORT_ORDER : 0;

// Activation module du paiement selon les groupes B2B
    if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
      if (B2BCommon::getShippingUnallowed($this->code)) {
        if (CLICSHOPPING_APP_TABLE_TA_STATUS == 'True') {
          $this->enabled = true;
        } else {
          $this->enabled = false;
        }
      }
    } else {
      if (\defined('CLICSHOPPING_APP_TABLE_TA_NO_AUTHORIZE') && CLICSHOPPING_APP_TABLE_TA_NO_AUTHORIZE == 'True' && $CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
        if ($CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
          if (CLICSHOPPING_APP_TABLE_TA_STATUS == 'True') {
            $this->enabled = true;
          } else {
            $this->enabled = false;
          }
        }
      }
    }

    if (\defined('CLICSHOPPING_APP_TABLE_TA_TAX_CLASS')) {
      if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
        if (B2BCommon::getTaxUnallowed($this->code) || !$CLICSHOPPING_Customer->isLoggedOn()) {
          $this->tax_class = \defined('CLICSHOPPING_APP_TABLE_TA_TAX_CLASS') ? CLICSHOPPING_APP_TABLE_TA_TAX_CLASS : 0;

        }
      } else {
        if (B2BCommon::getTaxUnallowed($this->code)) {
          $this->tax_class = \defined('CLICSHOPPING_APP_TABLE_TA_TAX_CLASS') ? CLICSHOPPING_APP_TABLE_TA_TAX_CLASS : 0;
        }
      }
    }

    if (($this->enabled === true) && ((int)CLICSHOPPING_APP_TABLE_TA_ZONE > 0)) {
      $check_flag = false;

      $Qcheck = $this->app->db->get('zones_to_geo_zones', 'zone_id', ['geo_zone_id' => (int)CLICSHOPPING_APP_TABLE_TA_ZONE,
        'zone_country_id' => $CLICSHOPPING_Order->delivery['country']['id']
      ],
        'zone_id'
      );

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

  public function quote($method = '')
  {
    $CLICSHOPPING_Order = Registry::get('Order');
    $CLICSHOPPING_Tax = Registry::get('Tax');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Shipping = Registry::get('Shipping');

    $shipping_weight = $CLICSHOPPING_Shipping->getShippingWeight();

    $shipping_num_boxes = 1;

    if (CLICSHOPPING_APP_TABLE_TA_MODE == 'price') {
      $order_total = $this->getShippableTotal();
    } else {
      $order_total = $shipping_weight;
    }

    $table_cost = preg_split("/[:,]/", CLICSHOPPING_APP_TABLE_TA_COST);
    $size = \count($table_cost);

    for ($i = 0, $n = $size; $i < $n; $i += 2) {
      if ($order_total <= $table_cost[$i]) {
        $shipping = $table_cost[$i + 1];
        break;
      }
}

    if (CLICSHOPPING_APP_TABLE_TA_MODE == 'weight') {
      $shipping = $shipping * $shipping_num_boxes;
    }

    $this->quotes = [
      'id' => $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code,
      'module' => $this->app->getDef('module_table_text_title'),
      'methods' => [
        ['id' => $this->code,
        'title' => $this->app->getDef('module_shipping_table_text_way') ?? 'Best way',
        'cost' => $shipping + (float)CLICSHOPPING_APP_TABLE_TA_HANDLING
        ]
      ]
    ];

    if ($this->tax_class > 0) {
      $this->quotes['tax'] = $CLICSHOPPING_Tax->getTaxRate($this->tax_class, $CLICSHOPPING_Order->delivery['country']['id'], $CLICSHOPPING_Order->delivery['zone_id']);
    }

    if (!empty(CLICSHOPPING_APP_TABLE_TA_LOGO)) {
      $this->icon = $CLICSHOPPING_Template->getDirectoryTemplateImages() . 'logos/shipping/' . CLICSHOPPING_APP_TABLE_TA_LOGO;
      $this->icon = HTML::image($this->icon, $this->title);
    } else {
      $this->icon = '';
    }

    if (!\is_null($this->icon)) $this->quotes['icon'] = '&nbsp;&nbsp;&nbsp;' . $this->icon;

    return $this->quotes;
  }

  public function check()
  {
    return \defined('CLICSHOPPING_APP_TABLE_TA_STATUS') && (trim(CLICSHOPPING_APP_TABLE_TA_STATUS) != '');
  }

  public function install()
  {
    $this->app->redirect('Configure&Install&module=Table');
  }

  public function remove()
  {
    $this->app->redirect('Configure&Uninstall&module=Table');
  }

  public function keys()
  {
    return array('CLICSHOPPING_APP_TABLE_TA_SORT_ORDER');
  }

  public function getShippableTotal()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Currencies = Registry::get('Currencies');
    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
    $CLICSHOPPING_Order = Registry::get('Order');

    $order_total = $CLICSHOPPING_ShoppingCart->show_total();

    if ($CLICSHOPPING_Order->content_type == 'mixed') {
      $order_total = 0;

      for ($i = 0, $n = \count($CLICSHOPPING_Order->products); $i < $n; $i++) {
        $order_total += $CLICSHOPPING_Currencies->calculatePrice($CLICSHOPPING_Order->products[$i]['final_price'], $CLICSHOPPING_Order->products[$i]['tax'], $CLICSHOPPING_Order->products[$i]['qty']);

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
              $order_total -= $CLICSHOPPING_Currencies->calculatePrice($CLICSHOPPING_Order->products[$i]['final_price'], $CLICSHOPPING_Order->products[$i]['tax'], $CLICSHOPPING_Order->products[$i]['qty']);
            }
          }
        }
      }
    }

    return $order_total;
  }
}