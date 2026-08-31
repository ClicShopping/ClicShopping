<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\Shop\Orders;

use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\Apps\AI\Ecommerce\Classes\Shared\OrderEmbeddingBuilder;
use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

class Process implements HooksInterface
{
  public mixed $app;
  public mixed $lang;
  public mixed $semantics;
  private OrderEmbeddingBuilder $builder;
  
  /**
   * Class constructor.
   *
   * Initializes the ChatGptApp instance in the Registry if it doesn't already exist,
   * and loads the necessary definitions for the application.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Ecommerce')) {
      Registry::set('Ecommerce', new EcommerceApp());
    }

    $this->app = Registry::get('Ecommerce');
    $this->lang = Registry::get('Language');

    if (!Registry::exists('Semantics')) {
      Registry::set('Semantics', new SemanticAgent());
    }

    $this->semantics = Registry::get('Semantics');
    $this->builder = new OrderEmbeddingBuilder();
    $this->app->loadDefinitions('Module/Hooks/Shop/Orders/process');
  }


  /**
   * Incrementally updates the product co-occurrence table after an order is placed.
   *
   * This method computes pairwise product associations within a single order
   * and updates the `table_products_cooccurrence` table using an upsert strategy.
   *
   * Mechanism:
   * - Performs a self-join on `orders_products` for the given order ID.
   * - Generates all directed product pairs (A → B) where A ≠ B.
   * - Inserts each pair with an initial score of 1.
   * - If the pair already exists (PRIMARY KEY: product_id, related_id),
   *   increments the existing score by 1.
   *
   * Complexity:
   * - O(k²) where k = number of distinct products in the order.
   * - No dependency on global dataset size (constant-time relative to total orders).
   *
   * Data semantics:
   * - `product_id`   = source product
   * - `related_id`   = associated product
   * - `score`        = frequency of co-occurrence across all processed orders
   *
   * Requirements:
   * - UNIQUE or PRIMARY KEY on (product_id, related_id)
   * - Indexed columns for performance
   *
   * Usage context:
   * - Intended to be called immediately after order validation (checkout hook).
   * - Supports real-time enrichment of recommendation signals.
   *
   * Limitations:
   * - Does not normalize scores (raw counts only).
   * - Does not account for time decay or session weighting.
   *
   * @param int $orderId Identifier of the processed order
   * @return void
   */
  public static function updateCooccurrence(int $orderId): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Q = $CLICSHOPPING_Db->prepare("INSERT INTO :table_products_cooccurrence (product_id, related_id, score)
                                    SELECT 
                                      a.products_id,
                                      b.products_id,
                                      1
                                    FROM :table_orders_products a
                                    JOIN :table_orders_products b
                                      ON a.orders_id = b.orders_id
                                     AND a.products_id != b.products_id
                                    WHERE a.orders_id = :orders_id
                                    ON DUPLICATE KEY UPDATE score = score + 1
                                  ");

    $Q->bindInt(':orders_id', $orderId);
    $Q->execute();
  }

  /**
   * Executes the embedding process for order updates.
   *
   * This method checks the GPT status and whether the embedding feature is enabled.
   * If the conditions are met, it retrieves order details, products, attributes,
   * status history, totals, and returns. It then builds the embedding data and
   * saves it to the database.
   *
   * @return bool Returns false if conditions are not met, otherwise true.
   */
  public function execute(?array $parameters = null)
  {
    $requiredConstants = [
      'CLICSHOPPING_APP_ECOMMERCE_EC_STATUS',
      'CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING',
      'CLICSHOPPING_APP_CHATGPT_RA_STATUS',
    ];

    if (!CLICSHOPPING::checkAppsIsActivated($requiredConstants)) {
      return false;
    }

    if (!Gpt::checkGptStatus()) {
      return false;
    }

    $embedding_enabled = \defined('CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING') && CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING == 'True' && \defined( 'CLICSHOPPING_APP_CHATGPT_RA_STATUS') && CLICSHOPPING_APP_CHATGPT_RA_STATUS == 'True';

    if (!isset($_GET['Checkout'], $_GET['Process'])) {
      return false;
    }

    if ($embedding_enabled) {
      //take id of the latest order
      $order_id = $parameters['order_id'] ?? null;

      if (is_null($order_id)) {
        return false;
      }

      self::updateCooccurrence($order_id);
      $result = $this->builder->regenerate((int)$order_id);

      if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
        if (!$result['success']) {
          error_log("Shop/Orders: Failed to save embeddings - " . $result['error']);
        } else {
          error_log("Shop/Orders: Successfully saved {$result['chunks_saved']} chunk(s) for order {$order_id}");
        }
      }
    }
  }
}