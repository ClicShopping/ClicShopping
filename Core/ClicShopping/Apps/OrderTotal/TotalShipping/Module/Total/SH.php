<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\OrderTotal\TotalShipping\Module\Total;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\OrderTotal\TotalShipping\TotalShipping as TotalShippingApp;

class SH implements \ClicShopping\OM\Modules\OrderTotalInterface
{
  public string $code;
  public $title;
  public $description;
  public $enabled;
  public $group;
  public $output;
  public int|null $sort_order = 0;
  public mixed $app;
  public $surcharge;
  public $maximum;
  public $signature;
  public $public_title;
  protected $api_version;

  /**
   * Constructor method for initializing the TotalShipping application.
   *
   * The method checks if the TotalShipping application is registered in the Registry.
   * If it is not, it initializes and registers a new instance of TotalShippingApp.
   * It loads the necessary definitions, sets the application signature, API version, code, titles,
   * and determines whether the module is enabled based on configuration constants.
   * Additionally, it initializes the sort order and output properties.
   *
   * @return void
   */
  public function __construct()
  {

    if (!Registry::exists('TotalShipping')) {
      Registry::set('TotalShipping', new TotalShippingApp());
    }

    $this->app = Registry::get('TotalShipping');
    $this->app->loadDefinitions('Module/Shop/SH/SH');

    $this->signature = 'Total Shipping |' . $this->app->getVersion() . '|1.0';
    $this->api_version = $this->app->getApiVersion();

    $this->code = 'SH';
    $this->title = $this->app->getDef('module_sh_title');
    $this->public_title = $this->app->getDef('module_sh_public_title');

    $this->enabled = \defined('CLICSHOPPING_APP_ORDER_TOTAL_SHIPPING_SH_STATUS') && (CLICSHOPPING_APP_ORDER_TOTAL_SHIPPING_SH_STATUS == 'True') ? true : false;

    $this->sort_order = \defined('CLICSHOPPING_APP_ORDER_TOTAL_SHIPPING_SH_SORT_ORDER') && ((int)CLICSHOPPING_APP_ORDER_TOTAL_SHIPPING_SH_SORT_ORDER > 0) ? (int)CLICSHOPPING_APP_ORDER_TOTAL_SHIPPING_SH_SORT_ORDER : 0;

    $this->output = [];
  }

  /**
   * Processes the shipping logic for an order.
   *
   * This method checks for various conditions related to the shipping configuration and applies
   * rules such as free shipping based on destination and order total. It calculates shipping taxes,
   * updates order totals, and outputs the shipping method and cost.
   *
   * @return void
   */
  public function process()
  {

    $CLICSHOPPING_Currencies = Registry::get('Currencies');
    $CLICSHOPPING_Order = Registry::get('Order');
    $CLICSHOPPING_Tax = Registry::get('Tax');

    if (\defined('CLICSHOPPING_APP_ORDER_TOTAL_SHIPPING_SH_OVER') && \defined('CLICSHOPPING_APP_ORDER_TOTAL_SHIPPING_SH_DESTINATION') && \defined('STORE_COUNTRY')) {
      if (CLICSHOPPING_APP_ORDER_TOTAL_SHIPPING_SH_OVER == 'True') {
        $pass = false;
        switch (CLICSHOPPING_APP_ORDER_TOTAL_SHIPPING_SH_DESTINATION) {
          case 'national':
            if ($CLICSHOPPING_Order->delivery['country_id'] == STORE_COUNTRY) $pass = true;
            break;
          case 'international':
            if ($CLICSHOPPING_Order->delivery['country_id'] != STORE_COUNTRY) $pass = true;
            break;
          case 'both':
            $pass = true;
            break;
          default:
            $pass = false;
            break;
        }

        if (($pass === true) && (($CLICSHOPPING_Order->info['total'] - $CLICSHOPPING_Order->info['shipping_cost']) >= CLICSHOPPING_APP_ORDER_TOTAL_SHIPPING_SH_OVER)) {
          $CLICSHOPPING_Order->info['shipping_method'] = CLICSHOPPING::getDef('free_shipping_title');
          $CLICSHOPPING_Order->info['total'] -= $CLICSHOPPING_Order->info['shipping_cost'];
          $CLICSHOPPING_Order->info['shipping_cost'] = 0;
        }
      }
    }

    if (isset($_SESSION['shipping']) && is_string($_SESSION['shipping']['id']) && str_contains($_SESSION['shipping']['id'], '\\')) {
      $parts = explode('\\', $_SESSION['shipping']['id']);
      if (count($parts) === 3) {
        [$vendor, $app, $module] = $parts;
        $module_parts = explode('_', $module);
        if (count($module_parts) === 2) {
          [$module, $method] = $module_parts;
          $module = $vendor . '\\' . $app . '\\' . $module;
          $code = 'Shipping_' . str_replace('\\', '_', $module);
          if (Registry::exists($code)) {
            $CLICSHOPPING_SM = Registry::get($code);
          }
        }
      }
    }

    if (!\is_null($CLICSHOPPING_Order->info['shipping_method'])) {
      if (isset($CLICSHOPPING_SM->tax_class) && $CLICSHOPPING_SM->tax_class > 0) {
        $shipping_tax = $CLICSHOPPING_Tax->getTaxRate($CLICSHOPPING_SM->tax_class, $CLICSHOPPING_Order->delivery['country']['id'], $CLICSHOPPING_Order->delivery['zone_id']);
        $shipping_tax_description = $CLICSHOPPING_Tax->getTaxRateDescription($CLICSHOPPING_SM->tax_class, $CLICSHOPPING_Order->delivery['country']['id'], $CLICSHOPPING_Order->delivery['zone_id']);
        $CLICSHOPPING_Order->info['tax'] += $CLICSHOPPING_Tax->calculate($CLICSHOPPING_Order->info['shipping_cost'], $shipping_tax);

        if (isset($CLICSHOPPING_Order->info['tax_groups']["$shipping_tax_description"])) {
          $CLICSHOPPING_Order->info['tax_groups']["$shipping_tax_description"] += $CLICSHOPPING_Tax->calculate($CLICSHOPPING_Order->info['shipping_cost'], $shipping_tax);
        } else {
          $CLICSHOPPING_Order->info['tax_groups']["$shipping_tax_description"] = $CLICSHOPPING_Tax->calculate($CLICSHOPPING_Order->info['shipping_cost'], $shipping_tax);
        }

        $CLICSHOPPING_Order->info['total'] += $CLICSHOPPING_Tax->calculate($CLICSHOPPING_Order->info['shipping_cost'], $shipping_tax);

        if (\defined('DISPLAY_PRICE_WITH_TAX') && DISPLAY_PRICE_WITH_TAX == 'True') {
          $CLICSHOPPING_Order->info['shipping_cost'] += $CLICSHOPPING_Tax->calculate($CLICSHOPPING_Order->info['shipping_cost'], $shipping_tax);
        }
      }

      $this->output[] = ['title' => $CLICSHOPPING_Order->info['shipping_method'],
        'text' => $CLICSHOPPING_Currencies->format($CLICSHOPPING_Order->info['shipping_cost'], true, $CLICSHOPPING_Order->info['currency'], $CLICSHOPPING_Order->info['currency_value']),
        'value' => $CLICSHOPPING_Order->info['shipping_cost']
      ];
    }
  }


  /**
   *
   * @return bool Returns true if the constant 'CLICSHOPPING_APP_ORDER_TOTAL_SHIPPING_SH_STATUS' is defined and its value is not an empty string after trimming, otherwise returns false.
   */
  public function check()
  {
    return \defined('CLICSHOPPING_APP_ORDER_TOTAL_SHIPPING_SH_STATUS') && (trim(CLICSHOPPING_APP_ORDER_TOTAL_SHIPPING_SH_STATUS) != '');
  }

  /**
   * Initiates the installation process by redirecting to the specified configuration and installation page.
   *
   * @return void
   */
  public function install()
  {
    $this->app->redirect('Configure&Install&module=SH');
  }

  /**
   * Redirects the application to the uninstall configuration page for the specified module.
   *
   * @return void
   */
  public function remove()
  {
    $this->app->redirect('Configure&Uninstall&module=SH');
  }

  /**
   * Retrieves an array of configuration keys related to the shipping order total module.
   *
   * @return array Returns an array containing the configuration key identifiers.
   */
  public function keys()
  {
    return array('CLICSHOPPING_APP_ORDER_TOTAL_SHIPPING_SH_SORT_ORDER');
  }
}
