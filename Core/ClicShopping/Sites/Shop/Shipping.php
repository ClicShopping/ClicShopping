<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Sites\Shop;

use ClicShopping\OM\Apps;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use function count;
use function defined;
use function in_array;
use function is_array;
use function is_null;

/**
 * Class responsible for handling shipping modules and operations related to shipping in the application.
 */
class Shipping
{
  public array $modules = [];

  /**
   * Constructor for initializing the shipping modules.
   *
   * @param array|null $module Optional module information, expected to include an 'id' key with module identifier.
   * @return void
   */
  public function __construct($module = null)
  {
    if (defined('MODULE_SHIPPING_INSTALLED') && !is_null(MODULE_SHIPPING_INSTALLED)) {
      $this->modules = explode(';', MODULE_SHIPPING_INSTALLED);

      $include_modules = [];

      $code = null;

      if (isset($module) && is_array($module) && isset($module['id'])) {
        if (str_contains($module['id'], '\\')) {
          list($vendor, $app, $module) = explode('\\', $module['id']);

          $code = $vendor . '\\' . $app . '\\' . $module;
        }
      }

      if (isset($code) && (in_array($code . '.' . substr(CLICSHOPPING::getIndex(), (strrpos(CLICSHOPPING::getIndex(), '.') + 1)), $this->modules) || in_array($code, $this->modules))) {
        if (str_contains($code, '\\')) {
          $class = Apps::getModuleClass($code, 'Shipping');

          $include_modules[] = [
            'class' => $code,
            'file' => $class
          ];
        }
      } else {
        foreach ($this->modules as $value) {
          if (str_contains($value, '\\')) {
            $class = Apps::getModuleClass($value, 'Shipping');

            $include_modules[] = [
              'class' => $value,
              'file' => $class
            ];
          }
        }
      }

      for ($i = 0, $n = count($include_modules); $i < $n; $i++) {
        if (str_contains($include_modules[$i]['class'], '\\')) {
          Registry::set('Shipping_' . str_replace('\\', '_', $include_modules[$i]['class']), new $include_modules[$i]['file']);
        }
      }
    }
  }

  /**
   * Calculates the shipping weight based on the items in the shopping cart,
   * considering additional box weight, padding, and maximum shipping weight constraints.
   *
   * @return float The calculated shipping weight.
   */
  public function getShippingWeight(): float
  {
    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
    $shipping_weight = 1;

    if (is_array($this->modules)) {
      $shipping_weight = $CLICSHOPPING_ShoppingCart->getWeight();

      if (SHIPPING_BOX_WEIGHT >= ($shipping_weight * (SHIPPING_BOX_PADDING / 100))) {
        $shipping_weight = $shipping_weight + SHIPPING_BOX_WEIGHT;
      } else {
        $shipping_weight = $shipping_weight + ($shipping_weight * (SHIPPING_BOX_PADDING / 100));
      }

      if ($shipping_weight > SHIPPING_MAX_WEIGHT) { // Split into many boxes
        $shipping_num_boxes = ceil($shipping_weight / SHIPPING_MAX_WEIGHT);
        $shipping_weight = $shipping_weight / $shipping_num_boxes;
      }
    }

    return $shipping_weight;
  }

  /**
   * Retrieves shipping quotes based on the specified method and module.
   *
   * @param string|null $method The shipping method for which quotes are required. Defaults to null if no specific method is provided.
   * @param string|null $module The shipping module to be used for fetching the quotes. Defaults to null if no specific module is provided.
   *
   * @return array An array of shipping quotes retrieved based on the provided method and module.
   */
  public function getQuote($method = null, $module = null): array
  {
    $quotes_array = [];

    if (is_array($this->modules)) {
      $include_quotes = [];

      foreach ($this->modules as $value) {
        if (str_contains($value, '\\')) {
          $obj = Registry::get('Shipping_' . str_replace('\\', '_', $value));

          if (!is_null($module)) {
            if (($module == $value) && ($obj->enabled)) {
              $include_quotes[] = $value;
            }
          } elseif ($obj->enabled) {
            $include_quotes[] = $value;
          }
        }
      }

      $size = count($include_quotes);

      for ($i = 0; $i < $size; $i++) {
        if (str_contains($include_quotes[$i], '\\')) {
          $quotes = Registry::get('Shipping_' . str_replace('\\', '_', $include_quotes[$i]))->quote($method);
        }

        if (is_array($quotes)) {
          $quotes_array[] = $quotes;
        }
      }
    }

    return $quotes_array;
  }

// function not include in shipping.php
// can put pb with module like post canada online 94
  /**
   * Retrieves the first available shipping method with its corresponding details
   * if enabled and meeting the required conditions.
   *
   * @return array|null An associative array containing the first available shipping method's
   *                    details such as 'id', 'title', 'info', and 'cost'. Returns null
   *                    if no valid shipping method is found.
   */
  public function getFirst():array|null
  {
    foreach ($this->modules as $value) {
      if (str_contains($value, '\\')) {
        $obj = Registry::get('Shipping_' . str_replace('\\', '_', $value));

        if ($obj->enabled) {
          foreach ($obj->quotes['methods'] as $method) {
            if (isset($method['cost']) && !is_null($method['cost'])) {
              return [
                'id' => $obj->quotes['id'] . '_' . $method['id'],
                'title' => $obj->quotes['module'] . (isset($method['title']) && !empty($method['title']) ? ' (' . $method['title'] . ')' : ''),
                'info' => $obj->quotes['info'] . (isset($method['info']) && !empty($method['info']) ? ' (' . $method['info'] . ')' : ''),
                'cost' => $method['cost']
              ];
            }
          }
        }
      }
    }

    return null;
  }

  /**
   * Determines and returns the cheapest shipping rate from available modules.
   *
   * This method iterates through registered shipping modules, collecting their
   * rates and comparing them to find the lowest-cost shipping option.
   *
   * @return array|false An associative array with details of the cheapest shipping rate
   *                     (including 'id', 'title', 'info', and 'cost')
   *                     or false if no rates are available.
   */
  public function getCheapest()
  {
    if (is_array($this->modules)) {
      $rates = [];
      $obj = [];

      foreach ($this->modules as $value) {
        if (str_contains($value, '\\')) {
          $obj = Registry::get('Shipping_' . str_replace('\\', '_', $value));
        }

        if (is_object($obj)) {
          if ($obj->enabled) {
            $quotes = $obj->quotes;

            for ($i = 0, $n = count($quotes['methods'] ?: []); $i < $n; $i++) {
              if (isset($quotes['methods'][$i]['cost']) && !is_null($quotes['methods'][$i]['cost'])) {
                $rates[] = [
                  'id' => $quotes['id'] . '_' . $quotes['methods'][$i]['id'],
                  'title' => $quotes['module'] . (isset($quotes['methods'][$i]['title']) && !empty($quotes['methods'][$i]['title']) ? ' (' . $quotes['methods'][$i]['title'] . ')' : ''),
                  'info' => $quotes['info'] . (isset($quotes['methods'][$i]['info']) && !empty($quotes['methods'][$i]['info']) ? ' (' . $quotes['methods'][$i]['info'] . ')' : ''),
                  'cost' => $quotes['methods'][$i]['cost']
                ];
              }
            }
          }
        }
      }

      $cheapest = false;

      for ($i = 0, $n = count($rates); $i < $n; $i++) {
        if (is_array($cheapest)) {
          if ($rates[$i]['cost'] < $cheapest['cost']) {
            $cheapest = $rates[$i];
          }
        } else {
          $cheapest = $rates[$i];
        }
      }

      return $cheapest;
    }

    return false;
  }

  /**
   * Counts the number of enabled shipping modules installed in the system.
   *
   * @return int The count of enabled shipping modules.
   */
  public function geCountShippingModules(): int
  {
    $count = 0;

    $modules_array = explode(';', MODULE_SHIPPING_INSTALLED);

    for ($i = 0, $n = count($modules_array); $i < $n; $i++) {
      $m = $modules_array[$i];

      $CLICSHOPPING_SM = null;

      if (str_contains($m, '\\')) {
        list($vendor, $app, $module) = explode('\\', $m);

        $module = $vendor . '\\' . $app . '\\' . $module;

        $code = 'Shipping_' . str_replace('\\', '_', $module);

        if (Registry::exists($code)) {
          $CLICSHOPPING_SM = Registry::get($code);
        }
      }

      if (isset($CLICSHOPPING_SM) && $CLICSHOPPING_SM->enabled) {
        $count++;
      }
    }

    return $count;
  }
}