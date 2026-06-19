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
      //We run SQL to get total number of taxes configured for our shipping zone
      //If we have many taxes rates configured, we will compare tax priorities.
      //If taxes have different priorities, ot_tax will apply 2nd priority tax_rate over 1rst (ex: for Quebec we have PST over GST), we assume GST has the lowest priority.
      //If taxes have the same priorities, ot_tax still show taxes on two line but dosen't apply compounded taxes (ie: Ontario)
      //If we get only one tax result, we assume we are handling only GST or HST (same scenario)
      // take also the shipping taxe

      $compound_tax = false;
      $gst_total = 0;
      $pst_total = 0;
      $hst_total = 0;

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

      $QtaxPriority = $CLICSHOPPING_Db->prepare('select tax_priority
                                                   from :table_tax_rates tr left join :table_zones_to_geo_zones za on (tr.tax_zone_id = za.geo_zone_id)
                                                                            left join :table_geo_zones tz on (tz.geo_zone_id = tr.tax_zone_id)
                                                   where za.zone_country_id = :zone_country_id
                                                   and za.zone_id = :zone_id
                                                   order by tr.tax_priority
                                                  ');
      $QtaxPriority->bindInt(':zone_country_id', $CLICSHOPPING_Order->delivery['country']['id']);
      $QtaxPriority->bindInt(':zone_id', $zone_id);
      $QtaxPriority->execute();

      $Qtax = $CLICSHOPPING_Db->prepare('select tax_rates_id,
                                                  tax_priority,
                                                  tax_rate,
                                                  tax_description
                                           from :table_tax_rates tr left join :table_zones_to_geo_zones za on (tr.tax_zone_id = za.geo_zone_id)
                                                                     left join :table_geo_zones tz on (tz.geo_zone_id = tr.tax_zone_id)
                                           where za.zone_country_id = :zone_country_id
                                           and za.zone_id = :zone_id
                                           order by tr.tax_priority
                                        ');
      $Qtax->bindInt(':zone_country_id', $CLICSHOPPING_Order->delivery['country']['id']);
      $Qtax->bindInt(':zone_id', $zone_id);
      $Qtax->execute();

      if ($QtaxPriority->fetch()) {
        $hst_total = 0;

        if ($QtaxPriority->rowCount() == 2) { //Show taxes on two lines
          $i = 0;
          $tax_priority = '';

          while ($QtaxPriority->fetch()) { //compare tax_priotiries
            if ($i == 0) {
              $tax_priority = $QtaxPriority->valueInt('tax_priority');
            } else {
              if ($tax_priority != $QtaxPriority->valueInt('tax_priority')) {
                $compound_tax = true;
              } else {
                $compound_tax = false;
              }
            }
            $i++;
          }
//END Compare tax priorities

          if ($compound_tax) { //ie Quebec different de false et true
            $j = 0;

            while ($Qtax->fetch()) {
              if ($j == 0) {
                $gst_description = $Qtax->value('tax_description');
                $gst_rate = $Qtax->valueDecimal('tax_rate') / 100;
              } elseif ($j >= 1) {
                $pst_description = $Qtax->value('tax_description');
                $pst_rate = $Qtax->valueDecimal('tax_rate') / 100;
              }
              $j++;
            }

            $subtotal = $CLICSHOPPING_Order->info['subtotal'] + $CLICSHOPPING_Order->info['shipping_cost'];

// Si l'ordre d'affichage du shipping < sort order on additionne les frais d'envoi au sous total
            if (defined(CLICSHOPPING_APP_ORDER_TOTAL_SHIPPING_SH_SORT_ORDER) && (CLICSHOPPING_APP_ORDER_TOTAL_SHIPPING_SH_SORT_ORDER < CLICSHOPPING_APP_ORDER_TOTAL_TAX_TX_SORT_ORDER)) $subtotal += $CLICSHOPPING_Order->info['shipping_cost'];

            $gst_total = round($subtotal * $gst_rate, $CLICSHOPPING_Currencies->currencies[DEFAULT_CURRENCY]['decimal_places']);
            $pst_total = ($subtotal + $gst_total) * $pst_rate;

            foreach ($CLICSHOPPING_Order->info['tax_groups'] as $key => $value) {
              if ($value > 0) {
                $this->output[] = [
                  'title' => $gst_description . ':',
                  'text' => $CLICSHOPPING_Currencies->format($gst_total, true, $CLICSHOPPING_Order->info['currency'], $CLICSHOPPING_Order->info['currency_value']), 'value' => $gst_total
                ];
                $this->output[] = [
                  'title' => $pst_description . ':',
                  'text' => $CLICSHOPPING_Currencies->format($pst_total, true, $CLICSHOPPING_Order->info['currency'], $CLICSHOPPING_Order->info['currency_value']),
                  'value' => $pst_total
                ];
              }
            } // end while
          } else { //ie: Ontario
            $j = 0;

            while ($Qtax->fetch()) {
              if ($j == 0) {
                $gst_description = $Qtax->value('tax_description');
                $gst_rate = $Qtax->valueDecimal('tax_rate') / 100;
              } elseif ($j >= 1) {
                $pst_description = $Qtax->value('tax_description');
                $pst_rate = $Qtax->valueDecimal('tax_rate') / 100;
              }

              $j++;
            }
            $subtotal = $CLICSHOPPING_Order->info['subtotal'];

// Si l'ordre d'affichage du shipping < sort order on additionne les frais d'envoi au sous total
            if (defined('CLICSHOPPING_APP_ORDER_TOTAL_SHIPPING_SH_SORT_ORDER') && (CLICSHOPPING_APP_ORDER_TOTAL_SHIPPING_SH_SORT_ORDER < CLICSHOPPING_APP_ORDER_TOTAL_TAX_TX_SORT_ORDER)) $subtotal += $CLICSHOPPING_Order->info['shipping_cost'];

            $gst_total = round($subtotal * $gst_rate, $CLICSHOPPING_Currencies->currencies[DEFAULT_CURRENCY]['decimal_places']);
            $pst_total = $subtotal * $pst_rate;

            foreach ($CLICSHOPPING_Order->info['tax_groups'] as $key => $value) {
              if ($value > 0) {
                $this->output[] = [
                  'title' => $gst_description . ':',
                  'text' => $CLICSHOPPING_Currencies->format($gst_total, true, $CLICSHOPPING_Order->info['currency'], $CLICSHOPPING_Order->info['currency_value']),
                  'value' => $gst_total
                ];
                $this->output[] = [
                  'title' => $pst_description . ':',
                  'text' => $CLICSHOPPING_Currencies->format($pst_total, true, $CLICSHOPPING_Order->info['currency'], $CLICSHOPPING_Order->info['currency_value']),
                  'value' => $pst_total
                ];
              }
            }
          }
// -----------------------------
// Only one taxe
// -----------------------------
//
        } elseif ($QtaxPriority->rowCount() == 1) { //Only GST or HST applies
          while ($Qtax->fetch()) {
            $subtotal = $CLICSHOPPING_Order->info['subtotal'];

// Si l'ordre d'affichage du shipping < sort order on additionne les frais d'envoi au sous total
            if (defined('CLICSHOPPING_APP_ORDER_TOTAL_SHIPPING_SH_SORT_ORDER') && CLICSHOPPING_APP_ORDER_TOTAL_SHIPPING_SH_SORT_ORDER < CLICSHOPPING_APP_ORDER_TOTAL_TAX_TX_SORT_ORDER) $subtotal += $CLICSHOPPING_Order->info['shipping_cost'];

            $hst_total = $subtotal * ($Qtax->valueDecimal('tax_rate') / 100);

            foreach ($CLICSHOPPING_Order->info['tax_groups'] as $key => $value) {
              if ($value > 0) {
                $this->output[] = [
                  'title' => $Qtax->value('tax_description') . ' : ',
                  'text' => $CLICSHOPPING_Currencies->format($hst_total, true, $CLICSHOPPING_Order->info['currency'], $CLICSHOPPING_Order->info['currency_value']), 'value' => $hst_total
                ];
              }
            }
          }
        } // end elseif
      }

//We calculate $CLICSHOPPING_Order->info with updated tax values. For this to work ot_tax has to be last ot module called, just before ot_total
      $CLICSHOPPING_Order->info['tax'] = round($gst_total, $CLICSHOPPING_Currencies->currencies[DEFAULT_CURRENCY]['decimal_places']) + round($pst_total, $CLICSHOPPING_Currencies->currencies[DEFAULT_CURRENCY]['decimal_places']) + round($hst_total, $CLICSHOPPING_Currencies->currencies[DEFAULT_CURRENCY]['decimal_places']);
      $CLICSHOPPING_Order->info['total'] = $CLICSHOPPING_Order->info['subtotal'] + $CLICSHOPPING_Order->info['tax'] + $CLICSHOPPING_Order->info['shipping_cost'];

    } else {
// **********************************
// normal tax
// ************************************

      foreach ($CLICSHOPPING_Order->info['tax_groups'] as $key => $value) {
        if ($value > 0) {
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
