<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\OrderTotal\TotalTax\Module\Total;

use ClicShopping\OM\Registry;
use ClicShopping\Sites\Common\B2BCommon;
use ClicShopping\Sites\Shop\Tax;
use ClicShopping\Apps\OrderTotal\TotalTax\TotalTax as TotalTaxApp;
use ClicShopping\OM\Interfaces\OrderTotalInterface;

use function defined;

class TX implements OrderTotalInterface
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
   * Constructor method for initializing the TotalTax module.
   *
   * This method registers the TotalTax module in the registry, loads its
   * definitions, sets up its configuration properties, including the module's
   * signature, code, title, public title, sort order, and enabled status.
   * Additionally, it initializes the output array for the module.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('TotalTax')) {
      Registry::set('TotalTax', new TotalTaxApp());
    }

    $this->app = Registry::get('TotalTax');
    $this->app->loadDefinitions('Module/Shop/TX/TX');

    $this->signature = 'Tax|' . $this->app->getVersion() . '|1.0';
    $this->api_version = $this->app->getApiVersion();

    $this->code = 'TX';
    $this->title = $this->app->getDef('module_tx_title');
    $this->public_title = $this->app->getDef('module_tx_public_title');

// Controle en B2B l'assujetti a la TVA (valeur true par defaut en mode B2C)
    if (B2BCommon::getTaxUnallowed($this->code)) {
      $this->enabled = defined('CLICSHOPPING_APP_ORDER_TOTAL_TAX_TX_STATUS') && (CLICSHOPPING_APP_ORDER_TOTAL_TAX_TX_STATUS == 'True') ? true : false;
    }

    $this->sort_order = defined('CLICSHOPPING_APP_ORDER_TOTAL_TAX_TX_SORT_ORDER') && ((int)CLICSHOPPING_APP_ORDER_TOTAL_TAX_TX_SORT_ORDER > 0) ? (int)CLICSHOPPING_APP_ORDER_TOTAL_TAX_TX_SORT_ORDER : 0;

    $this->output = [];
  }

  /**
   * Processes tax calculations for orders based on geographical zones and tax priorities.
   * This includes evaluating compound taxes for regions like Quebec, handling single or multi-level taxes,
   * and formatting tax outputs for the order.
   *
   * @return void
   */
  public function process()
  {
    $CLICSHOPPING_Order = Registry::get('Order');
    $CLICSHOPPING_Currencies = Registry::get('Currencies');
    $CLICSHOPPING_Db = Registry::get('Db');

// Txe Canada - Quebec
    if (DISPLAY_DOUBLE_TAXE == 'true') {
      //WARNING: This module does not consider tax_class!!! We assume everything is taxable.
      //The GST/PST/HST split (compound or not) lives in Tax::computeDoubleTaxRows so
      //the checkout and the admin order recalc render exactly the same lines.
      //Here we only resolve the delivery zone from the live Order and delegate.

      if ($CLICSHOPPING_Order->delivery['zone_id'] == 0) {
        $QzoneCheck = $CLICSHOPPING_Db->prepare('select zone_id
                                                    from :table_zones
                                                    where zone_name = :zone_name
                                                    and zone_country_id = :zone_country_id
                                                    ');
        $QzoneCheck->bindInt(':zone_country_id', $CLICSHOPPING_Order->delivery['country']['id']);
        $QzoneCheck->bindvalue(':zone_name', $CLICSHOPPING_Order->delivery['state']);
        $QzoneCheck->execute();

        $zone_id = $QzoneCheck->valueInt('zone_id');
      } else {
        $zone_id = $CLICSHOPPING_Order->delivery['zone_id'];
      }

      $double_tax = Tax::computeDoubleTaxRows(
        (int)$CLICSHOPPING_Order->delivery['country']['id'],
        (int)$zone_id,
        (float)$CLICSHOPPING_Order->info['subtotal'],
        (float)$CLICSHOPPING_Order->info['shipping_cost'],
        $CLICSHOPPING_Order->info['tax_groups'],
        $CLICSHOPPING_Order->info['currency'],
        (float)$CLICSHOPPING_Order->info['currency_value']
      );

      foreach ($double_tax['rows'] as $row) {
        $this->output[] = $row;
      }

//We calculate $CLICSHOPPING_Order->info with updated tax values. For this to work ot_tax has to be last ot module called, just before ot_total
      $CLICSHOPPING_Order->info['tax'] = $double_tax['tax_total'];
      $CLICSHOPPING_Order->info['total'] = $CLICSHOPPING_Order->info['subtotal'] + $CLICSHOPPING_Order->info['tax'] + $CLICSHOPPING_Order->info['shipping_cost'];

    } else {
// **********************************
// normal tax
// ************************************

//Taxes must appear same if the value is 0
      foreach ($CLICSHOPPING_Order->info['tax_groups'] as $key => $value) {
        if ($value >= 0) {
          $this->output[] = [
            'title' => $key,
            'text' => $CLICSHOPPING_Currencies->format($value, true, $CLICSHOPPING_Order->info['currency'], $CLICSHOPPING_Order->info['currency_value']),
            'value' => $value
          ];
        }
      }
    }
  }

  /**
   *
   * @return bool Returns true if the constant 'CLICSHOPPING_APP_ORDER_TOTAL_TAX_TX_STATUS' is defined and its value is not an empty string after trimming; otherwise, false.
   */
  public function check()
  {
    return defined('CLICSHOPPING_APP_ORDER_TOTAL_TAX_TX_STATUS') && (trim(CLICSHOPPING_APP_ORDER_TOTAL_TAX_TX_STATUS) != '');
  }

  /**
   * Redirects the application to the installation configuration page for the specified module.
   *
   * @return void
   */
  public function install()
  {
    $this->app->redirect('Configure&Install&module=TX');
  }

  /**
   * Removes a module by redirecting to the uninstall configuration page.
   *
   * @return void
   */
  public function remove()
  {
    $this->app->redirect('Configure&Uninstall&module=TX');
  }

  /**
   *
   * @return array Returns an array of configuration keys related to the order total tax module.
   */
  public function keys()
  {
    return array('CLICSHOPPING_APP_ORDER_TOTAL_TAX_TX_SORT_ORDER');
  }
}
