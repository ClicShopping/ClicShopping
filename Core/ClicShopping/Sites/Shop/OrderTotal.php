<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop;

use ClicShopping\OM\Apps;
use ClicShopping\OM\Registry;
use function count;
use function defined;
use function is_array;
use function is_null;
/**
 * The OrderTotal class is responsible for managing, processing, and rendering
 * order total modules in a shopping cart system. It dynamically loads and executes
 * installed order total modules to determine the calculation and output of order totals.
 */
class OrderTotal
{
  public array $modules = [];

// class constructor

  /**
   * Constructor method for initializing the class.
   *
   * Checks if the module order total is installed and processes the installed modules.
   * Splits the modules into an array and registers each module in the application's registry.
   *
   * @return void
   */
  public function __construct()
  {
    if (defined('MODULE_ORDER_TOTAL_INSTALLED') && !is_null(MODULE_ORDER_TOTAL_INSTALLED)) {
      $this->modules = explode(';', MODULE_ORDER_TOTAL_INSTALLED);

      foreach ($this->modules as $value) {
        if (str_contains($value, '\\')) {
          $class = Apps::getModuleClass($value, 'OrderTotal');

          Registry::set('OrderTotal_' . str_replace('\\', '_', $value), new $class);
        }
      }
    }
  }

  /**
   * Processes the modules to generate an array of order totals.
   *
   * This method iterates through available modules, checks their enabled status,
   * and processes their output to construct an array containing details such as
   * the code, title, displayed text, value, and sort order of each order total component.
   *
   * @return array Returns an array of order totals, each containing the keys:
   *               - 'code': The unique code of the module.
   *               - 'title': The title of the order total component.
   *               - 'text': The textual representation of the order total value.
   *               - 'value': The numeric value of the order total component.
   *               - 'sort_order': The sorting order assigned to the component.
   */
  public function process()
  {
    $order_total_array = [];

    if (is_array($this->modules)) {
      foreach ($this->modules as $value) {

        if (str_contains($value, '\\')) {
          $CLICSHOPPING_OTM = Registry::get('OrderTotal_' . str_replace('\\', '_', $value));
        }
        if ($CLICSHOPPING_OTM->enabled) {
          $CLICSHOPPING_OTM->output = [];
          $CLICSHOPPING_OTM->process();

          for ($i = 0, $n = count($CLICSHOPPING_OTM->output); $i < $n; $i++) {
            if (!is_null($CLICSHOPPING_OTM->output[$i]['title']) && !is_null($CLICSHOPPING_OTM->output[$i]['text'])) {
              $order_total_array[] = [
                'code' => $CLICSHOPPING_OTM->code,
                'title' => $CLICSHOPPING_OTM->output[$i]['title'],
                'text' => $CLICSHOPPING_OTM->output[$i]['text'],
                'value' => $CLICSHOPPING_OTM->output[$i]['value'],
                'sort_order' => $CLICSHOPPING_OTM->sort_order
              ];
            }
          }
        }
      }
    }

    return $order_total_array;
  }

  /**
   * Generates and returns an HTML string containing the output of enabled modules.
   * The output includes title and text values of the modules formatted as table rows.
   *
   * @return string The formatted HTML string representing the output of the enabled modules.
   */
  public function output()
  {
    $output_string = '';
    if (is_array($this->modules)) {
      foreach ($this->modules as $value) {
        if (str_contains($value, '\\')) {
          $CLICSHOPPING_OTM = Registry::get('OrderTotal_' . str_replace('\\', '_', $value));
        }

        if ($CLICSHOPPING_OTM->enabled) {
          $size = count($CLICSHOPPING_OTM->output);
          for ($i = 0; $i < $size; $i++) {
            $output_string .= '              <tr>' . "\n" .
              '                <td class="OrderTotalTitle">' . $CLICSHOPPING_OTM->output[$i]['title'] . '</td>' . "\n" .
              '                <td class="OrderTotalText">' . $CLICSHOPPING_OTM->output[$i]['text'] . '</td>' . "\n" .
              '              </tr>';
          }
        }
      }
    }

    return $output_string;
  }
}