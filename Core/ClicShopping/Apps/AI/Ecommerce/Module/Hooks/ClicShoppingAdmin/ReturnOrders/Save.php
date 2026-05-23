<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\ClicShoppingAdmin\ReturnOrders;

use ClicShopping\AI\DomainsAI\CoreAI\Embedding\NewVector;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Common\HTMLOverrideCommon;


class Save implements \ClicShopping\OM\Modules\HooksInterface
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
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/ReturnOrders/rag');
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
    $embedding_data .=   $this->app->getDef('text_orders_products_return_id') . ' : ' . $item['return_id'] . "\n";
    $embedding_data .= $this->app->getDef('text_orders_products_return_ref') . ' : ' . $item['return_ref'] . "\n";
    $embedding_data .= $this->app->getDef('text_orders_products_return_product_id') . ' : ' . $item['products_id'] . "\n";
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
   * Processes the execution related to product data management and delete in the database.
   * This includes generating products_embedding, based on product information.
   *
   * @return void
   * @throws \JsonException
   */
  public function execute()
  {
    $requiredConstants = [
      'CLICSHOPPING_APP_ECOMMERCE_EC_STATUS',
      'CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING',
      'CLICSHOPPING_APP_CHATGPT_RA_STATUS',
    ];

    CLICSHOPPING::checkAppsIsActivated($requiredConstants);

    if (!Gpt::checkGptStatus()) {
      return false;
    }

    $embedding_enabled = \defined('CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING') && CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING == 'True' && \defined( 'CLICSHOPPING_APP_CHATGPT_RA_STATUS') && CLICSHOPPING_APP_CHATGPT_RA_STATUS == 'True';

    if (isset($_POST['rId'], $_GET['ReturnOrders'], $_GET['Save'])) {
      $return_id = HTML::sanitize($_POST['rId']);

      $Qcheck = $this->app->db->prepare('select id
                                         from :table_return_orders_embedding
                                         where entity_id = :entity_id
                                        ');
      $Qcheck->bindInt(':entity_id', $return_id);
      $Qcheck->execute();

        //********************
        // add embedding
        //********************
       if ($embedding_enabled) {
          $insert_embedding = false;

          if ($Qcheck->fetch() === false) {
            $insert_embedding = true;
          }

          $embedding_data = $this->returnOrders($return_id);
          $embedding_data .= $this->returnOrdersHistory($return_id);

          if (!empty($this->returnOrdersHistory($return_id))) {
            $taxonomy = $this->semantics->createTaxonomy(HTMLOverrideCommon::cleanHtmlForEmbedding($embedding_data), null);

            if (!empty($taxonomy)) {
              $lines = array_filter(array_map('trim', explode("\n", $taxonomy)));
              $tags = [];

              foreach ($lines as $line) {
                if (preg_match('/^\[([^\]]+)\]:\s*(.+)$/', $line, $matches)) {
                  $tags[$matches[1]] = trim($matches[2]);
                }
              }
            } else {
              $tags = [];
            }

            $embedding_data .= "\n" . $this->app->getDef('text_return_order_taxonomy') . " :\n";

            foreach ($tags as $key => $value) {
              $embedding_data .= "[$key]: $value\n";
            }
          }

          $embeddedDocuments = NewVector::createEmbedding(null, $embedding_data);

          // Prepare base metadata
          $baseMetadata = [
            'return_order_name' => $this->app->getDef('text_orders_products_return'),
            'content' => HtmlOverrideCommon::cleanHtmlForEmbedding($this->returnOrdersHistory($return_id)),
            'return_id' => (int)$return_id,
            'type' => 'return_orders',
            'source' => [
              'type' => 'manual',
              'name' => 'manual'
            ],
            'tags' => $taxonomy ? array_filter(array_map(fn($t) => trim(strip_tags($t)), explode("\n", $taxonomy))) : []
          ];

          // Save all chunks using centralized method
          $result = NewVector::saveEmbeddingsWithChunks(
            $embeddedDocuments,
            'return_orders_embedding',
            (int)$return_id,
            null,  // return_orders table doesn't have language_id
            $baseMetadata,
            $this->app->db,
            !$insert_embedding  // isUpdate = true if not inserting
          );

          if (!$result['success']) {
            error_log("ReturnOrders: Failed to save embeddings for return order {$return_id} - " . $result['error']);
          } else {
            error_log("ReturnOrders: Successfully saved {$result['chunks_saved']} chunks for return order {$return_id}");
          }
       }
    }
  }
}