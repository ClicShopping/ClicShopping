<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\Hooks\Shop\Orders;

use AllowDynamicProperties;
use ClicShopping\OM\Hash;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt as ChatGptApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\AI\DomainsAI\Shared\Embedding\NewVector;
use ClicShopping\Sites\Common\HTMLOverrideCommon;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;

#[AllowDynamicProperties]
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
    if (!Registry::exists('ChatGpt')) {
      Registry::set('ChatGpt', new ChatGptApp());
    }

    $this->app = Registry::get('ChatGpt');
    $this->lang = Registry::get('Language');

    if (!Registry::exists('Semantics')) {
      Registry::set('Semantics', new SemanticAgent());
    }
    $this->semantics = Registry::get('Semantics');
    $this->app->loadDefinitions('Module/Hooks/Shop/Orders/process');
  }

  /**
   * Checks if the embedding already exists for the given order ID.
   *
   * @param int $order_id The order ID.
   *
   * @return bool True if the embedding exists, false otherwise.
   */
 
 private function embeddingExists(int $order_id): bool
  {
    $Qcheck = $this->app->db->prepare('SELECT id 
                                       FROM :table_orders_embedding
                                      WHERE entity_id = :entity_id
                                      ');
    $Qcheck->bindInt(':entity_id', $order_id);
    $Qcheck->execute();

    return $Qcheck->fetch() !== false;
  }
  
  /**
   * Retrieves the order details from the database.
   *
   * @param int $order_id The order ID.
   *
   * @return array The order details.
   */
  private function getOrderDetails(int $order_id): array
  {
    $Q = $this->app->db->prepare(' SELECT * 
                                  FROM :table_orders 
                                  WHERE orders_id = :orders_id
                                  ');
    $Q->bindInt(':orders_id', $order_id);
    $Q->execute();

    return $Q->fetch();
  }

  /**
   * Retrieves the products associated with the order.
   *
   * @param int $order_id The order ID.
   *
   * @return array The products associated with the order.
   */
  private function getOrderProducts(int $order_id): array
  {
    $Q = $this->app->db->prepare('SELECT * 
                                  FROM :table_orders_products 
                                  WHERE orders_id = :orders_id
                                  ');
    $Q->bindInt(':orders_id', $order_id);
    $Q->execute();

    return $Q->fetchAll();
  }

  /**
   * Retrieves the product attributes associated with the order.
   *
   * @param int $order_id The order ID.
   *
   * @return array The product attributes associated with the order.
   */
  private function getOrderProductAttributes(int $order_id): array
  {
    $Q = $this->app->db->prepare('SELECT * 
                                  FROM :table_orders_products_attributes opa
                                  JOIN :table_orders_products op ON opa.orders_id = op.orders_id
                                  WHERE op.orders_id = :orders_id
                                ');
    $Q->bindInt(':orders_id', $order_id);
    $Q->execute();

    return $Q->fetchAll();
  }

  /**
   * Retrieves the order status history associated with the order.
   *
   * @param int $order_id The order ID.
   *
   * @return array The order status history associated with the order.
   */
  private function getOrderStatusHistory(int $order_id): array
  {
    $Q = $this->app->db->prepare('SELECT * FROM :table_orders_status_history WHERE orders_id = :orders_id');
    $Q->bindInt(':orders_id', $order_id);
    $Q->execute();

    return $Q->fetchAll();
  }

  /**
   * Retrieves the order totals associated with the order.
   *
   * @param int $order_id The order ID.
   *
   * @return array The order totals associated with the order.
   */
  private function getOrderTotals(int $order_id): array
  {
    $Q = $this->app->db->prepare('SELECT title, 
                                         value,
                                         class
                                  FROM :table_orders_total 
                                  WHERE orders_id = :orders_id');
    $Q->bindInt(':orders_id', $order_id);
    $Q->execute();

    return $Q->fetchAll();
  }

  /**
   * Builds the embedding data for the order using normalized atomic keys.
   *
   * This method constructs factual, deterministic embedding data with atomic keys
   * suitable for semantic search and vector embeddings.
   *
   * @param int $order_id The order ID.
   * @param array $order The order details.
   * @param array $products The products associated with the order.
   * @param array $attributes The product attributes associated with the order.
   * @param array $statusHistory The order status history associated with the order.
   * @param array $totals The order totals associated with the order.
   *
   * @return string The constructed embedding data string.
   */
  private function buildEmbeddingData(int $order_id, array $order, array $products, array $attributes, array $statusHistory, array $totals): string
  {
    $customers_city = Hash::displayDecryptedDataText($order['customers_city']);
    $customers_name = Hash::displayDecryptedDataText($order['customers_name']);
    $customers_company = Hash::displayDecryptedDataText($order['customers_company']);
    $delivery_city = Hash::displayDecryptedDataText($order['delivery_city']);

    // Use language file definitions for atomic keys
    $data = "[{$this->app->getDef('text_key_domain')}]: {$this->app->getDef('text_value_domain_ecommerce')}\n";
    $data .= "[{$this->app->getDef('text_key_entity')}]: {$this->app->getDef('text_value_entity_order')}\n\n";
    
    // Order information - atomic keys from language file
    $data .= "[{$this->app->getDef('text_key_order_id')}]: $order_id\n";
    $data .= "[{$this->app->getDef('text_key_order_date')}]: " . str_replace(' ', 'T', $order['date_purchased']) . "\n";
    $data .= "[{$this->app->getDef('text_key_order_status')}]: {$order['orders_status']}\n";
    $data .= "[{$this->app->getDef('text_key_order_currency')}]: {$order['currency']}\n";
    
    // Normalize payment method using HTMLOverrideCommon
    $paymentMethod = HTMLOverrideCommon::normalizeForAtomicKey($order['payment_method']);
    $data .= "[{$this->app->getDef('text_key_order_payment_method')}]: $paymentMethod\n\n";
    
    // Customer information - atomic keys from language file
    $data .= "[{$this->app->getDef('text_key_customer_name')}]: $customers_name\n";
    if (!empty($customers_company)) {
      $data .= "[{$this->app->getDef('text_key_customer_company')}]: $customers_company\n";
    }
    $data .= "[{$this->app->getDef('text_key_customer_city')}]: $customers_city\n";
    $data .= "[{$this->app->getDef('text_key_customer_country')}]: {$order['customers_country']}\n\n";
    
    // Delivery information - atomic keys from language file
    $data .= "[{$this->app->getDef('text_key_delivery_city')}]: $delivery_city\n";
    $data .= "[{$this->app->getDef('text_key_delivery_country')}]: {$order['delivery_country']}\n\n";
    
    // Products information - indexed atomic keys from language file
    $productIndex = 1;
    foreach ($products as $product) {
      $prefix = count($products) > 1 ? "$productIndex." : "";
      $baseKey = $this->app->getDef('text_key_product_name');
      $data .= "[" . str_replace('product.', "product.{$prefix}", $baseKey) . "]: {$product['products_name']}\n";
      
      $baseKey = $this->app->getDef('text_key_product_model');
      $data .= "[" . str_replace('product.', "product.{$prefix}", $baseKey) . "]: {$product['products_model']}\n";
      
      $baseKey = $this->app->getDef('text_key_product_price');
      $data .= "[" . str_replace('product.', "product.{$prefix}", $baseKey) . "]: {$product['products_price']}\n";
      
      $baseKey = $this->app->getDef('text_key_product_quantity');
      $data .= "[" . str_replace('product.', "product.{$prefix}", $baseKey) . "]: {$product['products_quantity']}\n";
      
      $baseKey = $this->app->getDef('text_key_product_tax_rate');
      $data .= "[" . str_replace('product.', "product.{$prefix}", $baseKey) . "]: " . ($product['products_tax'] / 100) . "\n";
      
      // Product attributes if any - indexed atomic keys from language file
      if (!empty($attributes) && is_array($attributes)) {
        $attrIndex = 1;
        foreach ($attributes as $attribute) {
          if (isset($attribute['orders_products_id']) && $attribute['orders_products_id'] == $product['orders_products_id']) {
            $baseKey = $this->app->getDef('text_key_product_attribute_option');
            $data .= "[" . str_replace(['product.', 'attribute.'], ["product.{$prefix}", "attribute.{$attrIndex}."], $baseKey) . "]: {$attribute['products_options']}\n";
            
            $baseKey = $this->app->getDef('text_key_product_attribute_value');
            $data .= "[" . str_replace(['product.', 'attribute.'], ["product.{$prefix}", "attribute.{$attrIndex}."], $baseKey) . "]: {$attribute['products_options_values']}\n";
            $attrIndex++;
          }
        }
      }
      
      $data .= "\n";
      $productIndex++;
    }
    
    // Totals information - atomic keys from language file with normalization
    $totalMapping = [
      'Sous Total' => $this->app->getDef('text_key_total_subtotal'),
      'Sub-Total' => $this->app->getDef('text_key_total_subtotal'),
      'Subtotal' => $this->app->getDef('text_key_total_subtotal'),
      'Shipping' => $this->app->getDef('text_key_total_shipping'),
      'Expédition' => $this->app->getDef('text_key_total_shipping'),
      'Tax' => $this->app->getDef('text_key_total_tax'),
      'TVA' => $this->app->getDef('text_key_total_tax'),
      'Taxe' => $this->app->getDef('text_key_total_tax'),
      'Total' => $this->app->getDef('text_key_total_total')
    ];
    
    foreach ($totals as $total) {
      $titleClean = trim(strip_tags($total['title']));
      $key = null;
      
      foreach ($totalMapping as $search => $mapped) {
        if (stripos($titleClean, $search) !== false) {
          $key = $mapped;
          break;
        }
      }
      
      if ($key) {
        $data .= "[$key]: {$total['value']}\n";
      }
    }
    
    // Status history - indexed atomic keys from language file, only with comments (factual insights)
    if (!empty($statusHistory) && is_array($statusHistory)) {
      $data .= "\n";
      $historyIndex = 1;
      $hasComments = false;
      
      foreach ($statusHistory as $status) {
        if (!empty($status['comments'])) {
          $hasComments = true;
          
          $baseKey = $this->app->getDef('text_key_history_date');
          $data .= "[" . str_replace('history.', "history.{$historyIndex}.", $baseKey) . "]: " . str_replace(' ', 'T', $status['date_added']) . "\n";
          
          $baseKey = $this->app->getDef('text_key_history_status');
          $data .= "[" . str_replace('history.', "history.{$historyIndex}.", $baseKey) . "]: {$status['orders_status_id']}\n";
          
          $baseKey = $this->app->getDef('text_key_history_comment');
          $data .= "[" . str_replace('history.', "history.{$historyIndex}.", $baseKey) . "]: " . HTMLOverrideCommon::cleanHtmlForEmbedding($status['comments']) . "\n";
          
          if (!empty($status['orders_tracking_number'])) {
            $baseKey = $this->app->getDef('text_key_history_tracking');
            $data .= "[" . str_replace('history.', "history.{$historyIndex}.", $baseKey) . "]: {$status['orders_tracking_number']}\n";
          }
          if (!empty($status['admin_user_name'])) {
            $baseKey = $this->app->getDef('text_key_history_admin');
            $data .= "[" . str_replace('history.', "history.{$historyIndex}.", $baseKey) . "]: {$status['admin_user_name']}\n";
          }
          
          $historyIndex++;
        }
      }
      
      // If no comments, just add the most recent status (factual state)
      if (!$hasComments && !empty($statusHistory)) {
        $lastStatus = end($statusHistory);
        
        $baseKey = $this->app->getDef('text_key_history_date');
        $data .= "[" . str_replace('history.', "history.1.", $baseKey) . "]: " . str_replace(' ', 'T', $lastStatus['date_added']) . "\n";
        
        $baseKey = $this->app->getDef('text_key_history_status');
        $data .= "[" . str_replace('history.', "history.1.", $baseKey) . "]: {$lastStatus['orders_status_id']}\n";
        
        if (!empty($lastStatus['admin_user_name'])) {
          $baseKey = $this->app->getDef('text_key_history_admin');
          $data .= "[" . str_replace('history.', "history.1.", $baseKey) . "]: {$lastStatus['admin_user_name']}\n";
        }
      }
    }

    return $data;
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
  public function execute()
  {
    if (Gpt::checkGptStatus() === false || !defined('CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING') || CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING == 'False' || !defined('CLICSHOPPING_APP_CHATGPT_RA_STATUS') || CLICSHOPPING_APP_CHATGPT_RA_STATUS == 'False') {
      return false;
    }

    $embedding_enabled = \defined('CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING') && CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING == 'True' && \defined( 'CLICSHOPPING_APP_CHATGPT_RA_STATUS') && CLICSHOPPING_APP_CHATGPT_RA_STATUS == 'True';
    if (!isset($_GET['Checkout'], $_GET['Process'])) {
      return false;
    }

    if ($embedding_enabled) {
      //take id of the latest order
      $Qorder = $this->app->db->prepare('select orders_id
                                        from :table_orders
                                        order by orders_id desc
                                        limit 1
                                        ');
      $Qorder->execute();

      $order_id = $Qorder->valueInt('orders_id');

      $insert_embedding = !$this->embeddingExists($order_id);

      $orderData = $this->getOrderDetails($order_id);
      $products = $this->getOrderProducts($order_id);
      $attributes = $this->getOrderProductAttributes($order_id);
      $statusHistory = $this->getOrderStatusHistory($order_id);
      $totals = $this->getOrderTotals($order_id);

      $embeddingData = $this->buildEmbeddingData($order_id, $orderData, $products, $attributes, $statusHistory, $totals);

      // Extract atomic keys from embedding data for metadata
      $tags = [];
      if (preg_match_all('/^\[([^\]]+)\]:\s*(.+)$/m', $embeddingData, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
          $tags[] = $match[1]; // Store only keys (atomic identifiers)
        }
      }

      // Generate embeddings
      $embeddedDocuments = NewVector::createEmbedding(null, $embeddingData);

      // Prepare base metadata for centralized chunk management
      $baseMetadata = [
        'order_name' => 'Order #' . $order_id,
        'content' => $embeddingData,
        'type' => 'orders',  // Entity type (goes in 'type' column)
        'tags' => $tags,
        'source' => ['type' => 'manual', 'name' => 'manual']  // Goes in 'sourcetype' and 'sourcename' columns
      ];

      // Save all chunks using centralized method
      $result = NewVector::saveEmbeddingsWithChunks(
        $embeddedDocuments,
        'orders_embedding',  // Table name
        (int)$order_id,
        null,  // language_id - orders table doesn't have this column
        $baseMetadata,
        $this->app->db,
        !$insert_embedding  // isUpdate = true if not inserting (i.e., updating existing entity)
      );

      if (!$result['success']) {
        error_log("Shop/Orders: Failed to save embeddings - " . $result['error']);
      } else {
        error_log("Shop/Orders: Successfully saved {$result['chunks_saved']} chunk(s) for order {$order_id}");
      }
    }
  }
}