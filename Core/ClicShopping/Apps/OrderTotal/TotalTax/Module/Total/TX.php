<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\OrderTotal\TotalTax\Module\Total;

use ClicShopping\OM\Registry;
use ClicShopping\OM\OrderTotalSequence;
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

  // The pivot of the sequence, and it does not move: charges entering the taxable base run before
  // it, the grand total after it.
  public string $moduletype = OrderTotalSequence::ROLE_TAX;

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

// Txe Canada - Quebec
    if (DISPLAY_DOUBLE_TAXE == 'true') {
      //WARNING: This module does not consider tax_class!!! We assume everything is taxable.
      //The GST/PST/HST split lives in Tax::computeDoubleTaxRows so the checkout and the admin
      //order recalc render exactly the same lines. Here we resolve the zone and the base.
      $double_tax = Tax::computeDoubleTaxRows(
        (int)$CLICSHOPPING_Order->delivery['country']['id'],
        $this->deliveryZoneId($CLICSHOPPING_Order),
        $this->taxableBase($CLICSHOPPING_Order),
        $CLICSHOPPING_Order->info['tax_groups'],
        $CLICSHOPPING_Order->info['currency'] ?? ($_SESSION['currency'] ?? DEFAULT_CURRENCY),
        $CLICSHOPPING_Order->info['currency_value'] ?? null
      );

      //A delivery zone that declares no rate is NOT a split-tax jurisdiction. The setting is
      //shop-wide, the jurisdiction is not: without this the whole VAT of a French order would be
      //replaced by an empty split, silently, line and amount alike.
      if ($double_tax['jurisdiction']) {
        foreach ($double_tax['rows'] as $row) {
          $this->output[] = $row;
        }

//The tax is recomputed here, so ACCUMULATE the change instead of rebuilding the total from the
//subtotal: the old assignment discarded whatever an upstream reduction or charge had added.
//On a tax-inclusive order nothing is added: the tax is already inside the price the customer sees.
        $previous_tax = (float)($CLICSHOPPING_Order->info['tax'] ?? 0);

        $CLICSHOPPING_Order->info['tax'] = $double_tax['tax_total'];

        if ($CLICSHOPPING_Order->info['tax_charged'] ?? empty($CLICSHOPPING_Order->info['prices_include_tax'])) {
          $CLICSHOPPING_Order->info['total'] += $double_tax['tax_total'] - $previous_tax;
        }

        return;
      }
    }

// **********************************
// normal tax
// ************************************
    $this->renderTaxGroups($CLICSHOPPING_Order);
  }

  /**
   * Delivery zone of the live order, resolved by name when the address carries no zone id.
   */
  private function deliveryZoneId(mixed $order): int
  {
    if ((int)($order->delivery['zone_id'] ?? 0) !== 0) {
      return (int)$order->delivery['zone_id'];
    }

    $QzoneCheck = Registry::get('Db')->prepare('select zone_id
                                                  from :table_zones
                                                  where zone_name = :zone_name
                                                  and zone_country_id = :zone_country_id
                                                ');
    $QzoneCheck->bindInt(':zone_country_id', (int)$order->delivery['country']['id']);
    $QzoneCheck->bindValue(':zone_name', $order->delivery['state'] ?? '');
    $QzoneCheck->execute();

    return $QzoneCheck->valueInt('zone_id');
  }

  /**
   * Base the rates apply to: the HT goods base recorded by Order::cart(), moved by every module
   * ranked before the tax (shipping, fees, discounts) — see OM\OrderTotalSequence.
   */
  private function taxableBase(mixed $order): float
  {
    $base = (float)($order->info['taxable_base'] ?? $order->info['subtotal']);

    return $base + (float)($order->info['taxable_base_delta'] ?? 0);
  }

  /**
   * One line per tax group, zero-rated ones included: an exempt line must show, not vanish.
   */
  private function renderTaxGroups(mixed $order): void
  {
    $CLICSHOPPING_Currencies = Registry::get('Currencies');

    foreach ($order->info['tax_groups'] as $key => $value) {
      if ($value >= 0) {
        $this->output[] = [
          'title' => $key,
          'text' => $CLICSHOPPING_Currencies->format($value, true, $order->info['currency'] ?? ($_SESSION['currency'] ?? DEFAULT_CURRENCY), $order->info['currency_value'] ?? null),
          'value' => $value
        ];
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
