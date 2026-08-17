<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Classes\Shop;

use ClicShopping\OM\Registry;

use function count;
use function defined;
use function is_null;

/**
 * Persists an order and its child rows: the orders row, the orders_total rows, and the
 * orders_products rows (with their attributes and download entries). Extracted verbatim
 * from {@see Order::Insert()} so the persistence concern is a single, explicit-input
 * collaborator that a future agentic adapter can drive with a prepared payload.
 *
 * Data preparation (payment resolution, name encryption, sql_data_array building) stays
 * in Order::Insert(); this class only writes what it is handed.
 */
class OrderWriter
{
  private mixed $db;
  private mixed $hooks;

  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->hooks = Registry::get('Hooks');
  }

  /**
   * Inserts the main orders row and returns its new id.
   *
   * @param array<string, mixed> $sqlDataArray Fully prepared orders row (incl. encrypted names).
   * @return int The new orders_id.
   */
  public function insertOrder(array $sqlDataArray): int
  {
    // Tax convention: dropped when the column is not there yet, so a deploy landing before the
    // migration never breaks checkout — same defensive rollout as total_sign below.
    if (isset($sqlDataArray['orders_prices_include_tax']) && !$this->ordersHasTaxConventionColumn()) {
      unset($sqlDataArray['orders_prices_include_tax']);
    }

    $this->db->save('orders', $sqlDataArray);

    $order_id = (int)$this->db->lastInsertId();

    // Extension seam: observers can react as soon as the order header row exists.
    $this->hooks->call('OrderWriter', 'OrderInserted', ['order_id' => $order_id]);

    return $order_id;
  }

  /**
   * Writes the orders_total rows from the OrderTotal pipeline output.
   *
   * @param int $order_id
   * @param array<int, array{title:mixed, text:mixed, value:mixed, code:mixed, sort_order:mixed}> $orderTotals
   * @return void
   */
  public function insertOrderTotals(int $order_id, array $orderTotals): void
  {
    $has_sign = $this->ordersTotalHasSignColumn();
    $has_rank = $this->ordersTotalHasRankColumn();

    for ($i = 0, $n = count($orderTotals); $i < $n; $i++) {
      $sql_data_array = [
        'orders_id' => (int)$order_id,
        'title' => $orderTotals[$i]['title'],
        'text' => $orderTotals[$i]['text'],
        'value' => (float)$orderTotals[$i]['value'],
        'class' => $orderTotals[$i]['code'],
        'sort_order' => (int)$orderTotals[$i]['sort_order']
      ];

      // total_sign: +1 charge, -1 credit/discount — written only when the column exists so a
      // deploy that lands before the DB migration never breaks checkout (defensive rollout).
      if ($has_sign) {
        $sql_data_array['total_sign'] = (int)($orderTotals[$i]['total_sign'] ?? 1);
      }

      // total_rank: the fiscal rank the module computed at, so re-editing this order reproduces ITS
      // sequence and not the configuration of the day. Same defensive column probe as total_sign.
      if ($has_rank && isset($orderTotals[$i]['total_rank'])) {
        $sql_data_array['total_rank'] = (int)$orderTotals[$i]['total_rank'];
      }

      $this->db->save('orders_total', $sql_data_array);
    }
  }

  /**
   * Whether orders_total carries the total_sign column yet (added by the 4.33 migration).
   * Cached for the request so we probe the schema at most once.
   *
   * @return bool
   */
  private function ordersTotalHasSignColumn(): bool
  {
    static $exists = null;

    if ($exists === null) {
      $Q = $this->db->prepare("SHOW COLUMNS FROM :table_orders_total LIKE 'total_sign'");
      $Q->execute();
      $exists = (bool)$Q->fetch();
    }

    return $exists;
  }

  /**
   * Whether orders_total carries the total_rank column yet (SQL-21 migration).
   * Cached for the request so we probe the schema at most once.
   *
   * @return bool
   */
  private function ordersTotalHasRankColumn(): bool
  {
    static $exists = null;

    if ($exists === null) {
      $Q = $this->db->prepare("SHOW COLUMNS FROM :table_orders_total LIKE 'total_rank'");
      $Q->execute();
      $exists = (bool)$Q->fetch();
    }

    return $exists;
  }

  /**
   * Whether orders carries the orders_prices_include_tax column yet (SQL-14 migration).
   * Cached for the request so we probe the schema at most once.
   *
   * @return bool
   */
  private function ordersHasTaxConventionColumn(): bool
  {
    static $exists = null;

    if ($exists === null) {
      $Q = $this->db->prepare("SHOW COLUMNS FROM :table_orders LIKE 'orders_prices_include_tax'");
      $Q->execute();
      $exists = (bool)$Q->fetch();
    }

    return $exists;
  }

  /**
   * Writes the orders_products rows (with attributes and download entries) for an order.
   *
   * @param int $order_id
   * @param array<int, array<string, mixed>> $products The Order::$products array.
   * @param int $customersGroupId B2B group (0 for B2C) — drives the group model lookup.
   * @param int $languageId Language used to resolve attribute labels.
   * @return void
   */
  public function insertOrderProducts(int $order_id, array $products, int $customersGroupId, int $languageId): void
  {
    $CLICSHOPPING_Prod = Registry::get('Prod');
    $CLICSHOPPING_ProductsAttributes = Registry::get('ProductsAttributes');

// initialized for the email confirmation
    for ($i = 0, $n = count($products); $i < $n; $i++) {
// search the good model
      if ($customersGroupId != 0) {
        $QproductsModuleCustomersGroup = $this->db->prepare('select products_model_group
                                                               from :table_products_groups
                                                               where products_id = :products_id
                                                               and customers_group_id = :customers_group_id
                                                              ');
        $QproductsModuleCustomersGroup->bindInt(':products_id', $CLICSHOPPING_Prod::getProductID($products[$i]['id']));
        $QproductsModuleCustomersGroup->bindInt(':customers_group_id', $customersGroupId);
        $QproductsModuleCustomersGroup->execute();

        $products_model = $QproductsModuleCustomersGroup->value('products_model_group');

        if (empty($products_model)) {
          $products_model = $products[$i]['model'];
        } else {
          $products_model = 'no model';
        }
      } else {
        $products_model = $products[$i]['model'];
      }

// save data
      $sql_data_array = [
        'orders_id' => (int)$order_id,
        'products_id' => (int)$CLICSHOPPING_Prod::getProductID($products[$i]['id']),
        'products_model' => $products_model,
        'products_name' => $products[$i]['name'],
        'products_price' => (float)$products[$i]['price'],
        'final_price' => (float)$products[$i]['final_price'],
        'products_tax' => (float)$products[$i]['tax'],
        'products_quantity' => (int)$products[$i]['qty']
      ];

      $this->db->save('orders_products', $sql_data_array);

      $order_products_id = $this->db->lastInsertId();

      if (isset($products[$i]['attributes'])) {
        for ($j = 0, $n2 = count($products[$i]['attributes']); $j < $n2; $j++) {
          $Qattributes = $CLICSHOPPING_ProductsAttributes->getAttributesDownloaded($products[$i]['id'], $products[$i]['attributes'][$j]['option_id'], $products[$i]['attributes'][$j]['value_id'], $languageId);

          $sql_data_array = [
            'orders_id' => (int)$order_id,
            'orders_products_id' => (int)$order_products_id,
            'products_options' => $Qattributes->value('products_options_name'),
            'products_options_values' => $Qattributes->value('products_options_values_name'),
            'options_values_price' => (float)$Qattributes->value('options_values_price'),
            'price_prefix' => $Qattributes->value('price_prefix'),
            'products_attributes_reference' => $Qattributes->value('products_attributes_reference')
          ];

          $this->db->save('orders_products_attributes', $sql_data_array);

          if ((\defined('DOWNLOAD_ENABLED') && DOWNLOAD_ENABLED == 'true') && $Qattributes->hasValue('products_attributes_filename') && !is_null($Qattributes->value('products_attributes_filename'))) {
            $sql_data_array = [
              'orders_id' => (int)$order_id,
              'orders_products_id' => (int)$order_products_id,
              'orders_products_filename' => $Qattributes->value('products_attributes_filename'),
              'download_maxdays' => (int)$Qattributes->value('products_attributes_maxdays'),
              'download_count' => (int)$Qattributes->value('products_attributes_maxcount')
            ];

            $this->db->save('orders_products_download', $sql_data_array);
          }
        }
      }
    } // end for
  }
}
