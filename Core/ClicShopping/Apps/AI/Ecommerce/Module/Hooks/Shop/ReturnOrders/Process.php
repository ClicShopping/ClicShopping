<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\Shop\ReturnOrders;


use ClicShopping\AI\DomainsAI\CoreAI\Embedding\NewVector;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt as ChatGptApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Common\HTMLOverrideCommon;


class Process implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;
  public mixed $lang;
  public mixed $semantics;
  
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
    $this->app->loadDefinitions('Module/Hooks/Shop/ReturnOrders/process');
  }

  /**
   * Generates an embedding string for a given return order ID.
   *
   * @param int $return_id The ID of the return order.
   * @return string The generated embedding string.
   */
  private function returnOrders(int $return_id): string
  {
    $QreturnOrders = $this->app->db->prepare('select r.return_id,
                                                     r.return_ref,
                                                     r.order_id,
                                                     r.product_id,
                                                     r.product_model,
                                                     r.product_name,
                                                     r.quantity,
                                                     r.return_reason_id,
                                                     r.return_status_id,
                                                     r.comment,
                                                     r.date_added,
                                                     o.date_purchased as date_ordered
                                              from :table_return_orders r,
                                                   :table_orders o
                                              where r.return_id = :return_id
                                              and o.orders_id = r.order_id
                                             ');

    $QreturnOrders->bindInt(':return_id', $return_id);
    $QreturnOrders->execute();

    $item = $QreturnOrders->fetch();
    $embedding_data = $this->app->getDef('text_orders_products_return') . "\n";
    $embedding_data .= $this->app->getDef('text_orders_products_return_ref') . ' : ' . $item['return_ref'] . "\n";
    $embedding_data .= $this->app->getDef('text_orders_products_return_order_id') . ' : ' . $item['order_id'] . "\n";
    $embedding_data .= $this->app->getDef('text_orders_products_return_model') . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($item['product_model']) . "\n";
    $embedding_data .= $this->app->getDef('text_orders_products_return_name') . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($item['product_name']) . "\n";
    $embedding_data .= $this->app->getDef('text_orders_products_return_qty') . ' : ' . $item['quantity'] . "\n";
    $embedding_data .= $this->app->getDef('text_orders_products_reason_id') . ' : ' . $item['return_reason_id'] . "\n";
    $embedding_data .= $this->app->getDef('text_orders_products_status_id') . ' : ' . $item['return_status_id'] . "\n";
    $embedding_data .= $this->app->getDef('text_orders_products_comment') . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($item['comment']) . "\n";
    $embedding_data .= $this->app->getDef('text_orders_products_date_added') . ' : ' . $item['date_added'] . "\n";
    $embedding_data .= $this->app->getDef('text_orders_products_date_ordered') . ' : ' . $item['date_ordered'] . "\n";

    return $embedding_data;
  }

  /**
   * Generates an embedding string for the return order history.
   *
   * @param int $return_id The ID of the return order.
   * @return string The generated embedding string.
   */
  private function returnOrdersHistory(int $return_id): string
  {
    $QreturnOrders = $this->app->db->prepare('select rh.comment,
                                                    rh.date_added,
                                                    rh.return_status_id,
                                                    rh.admin_user_name,
                                                    r.return_id      
                                             from :table_return_orders r,
                                                  :table_return_orders_history rh
                                             where r.return_id = :return_id
                                             and r.return_id = rh.return_id
                                             ');

    $QreturnOrders->bindInt(':return_id', $return_id);
    $QreturnOrders->execute();

    $return_orders_array = $QreturnOrders->fetchAll();
    $embedding_data =  "\n" . $this->app->getDef('text_orders_products_return_history') . "\n";

    foreach ($return_orders_array as $item) {
      $embedding_data .= $this->app->getDef('text_orders_products_return_history_comment') . ' : ' . HTMLOverrideCommon::cleanHtmlForEmbedding($item['comment']) . "\n";
      $embedding_data .= $this->app->getDef('text_orders_products_return_history_date_added') . ' : ' . $item['date_added'] . "\n";
    }

    return $embedding_data;
  }


  /**
   * Checks if the embedding already exists for the given order ID.
   *
   * @param int $order_id The order ID.
   *
   * @return bool True if the embedding exists, false otherwise.
   */

  private function embeddingExists(int $return_id): bool
  {
    $Qcheck = $this->app->db->prepare('SELECT id FROM :table_return_orders_embedding WHERE entity_id = :entity_id');
    $Qcheck->bindInt(':entity_id', $return_id);
    $Qcheck->execute();

    return $Qcheck->fetch() !== false;
  }


  /**
   * Processes the execution related to return order data management in the database.
   * This includes generating return_orders_embedding, based on return order information.
   *
   * @return void
   * @throws \JsonException
   */
  public function execute()
  {
    if (Gpt::checkGptStatus() === false || !defined('CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING') || CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING == 'False' || !defined('CLICSHOPPING_APP_CHATGPT_RA_STATUS') || CLICSHOPPING_APP_CHATGPT_RA_STATUS == 'False') {
      return false;
    }

    $embedding_enabled = \defined('CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING') && CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING == 'True' && \defined( 'CLICSHOPPING_APP_CHATGPT_RA_STATUS') && CLICSHOPPING_APP_CHATGPT_RA_STATUS == 'True';

    if (!isset($_GET['Checkout'], $_GET['Process'])) {
      return false;
    }
    
    $return_id = HTML::sanitize($_POST['rId']);

    if ($embedding_enabled) {
      $insert_embedding = !$this->embeddingExists($return_id);

      // Build embedding data
      $embedding_data = $this->returnOrders($return_id);
      $embedding_data .= $this->returnOrdersHistory($return_id);

      // Generate taxonomy tags if history exists
      $tags = [];
      if (!empty($this->returnOrdersHistory($return_id))) {
        $taxonomy = $this->semantics->createTaxonomy(HTMLOverrideCommon::cleanHtmlForEmbedding($embedding_data), null);

        if (!empty($taxonomy)) {
          $lines = array_filter(array_map('trim', explode("\n", $taxonomy)));

          foreach ($lines as $line) {
            if (preg_match('/^\[([^\]]+)\]:\s*(.+)$/', $line, $matches)) {
              $tags[$matches[1]] = trim($matches[2]);
            }
          }

          $embedding_data .= "\n" . $this->app->getDef('text_return_order_taxonomy') . " :\n";

          foreach ($tags as $key => $value) {
            $embedding_data .= "[$key]: $value\n";
          }
        }
      }

      // Generate embeddings
      $embeddedDocuments = NewVector::createEmbedding(null, $embedding_data);

      // Prepare base metadata for centralized chunk management
      $baseMetadata = [
        'return_order_name' => $this->app->getDef('text_orders_products_return'),
        'content' => HTMLOverrideCommon::cleanHtmlForEmbedding($this->returnOrdersHistory($return_id)),
        'return_id' => (int)$return_id,
        'type' => 'return_orders',  // Entity type (goes in 'type' column)
        'tags' => $tags,
        'source' => ['type' => 'manual', 'name' => 'manual']  // Goes in 'sourcetype' and 'sourcename' columns
      ];

      // Save all chunks using centralized method
      $result = NewVector::saveEmbeddingsWithChunks(
        $embeddedDocuments,
        'return_orders_embedding',  // Table name
        (int)$return_id,
        null,  // language_id - return_orders table doesn't have this column
        $baseMetadata,
        $this->app->db,
        !$insert_embedding  // isUpdate = true if not inserting (i.e., updating existing entity)
      );

      if (!$result['success']) {
        error_log("Shop/ReturnOrders: Failed to save embeddings - " . $result['error']);
      } else {
        error_log("Shop/ReturnOrders: Successfully saved {$result['chunks_saved']} chunk(s) for return order {$return_id}");
      }
    }
  }
}