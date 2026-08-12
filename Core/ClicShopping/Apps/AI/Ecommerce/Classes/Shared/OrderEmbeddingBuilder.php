<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\Shared;

use ClicShopping\AI\DomainsAI\Shared\Embedding\NewVector;
use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\OM\Hash;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Common\HTMLOverrideCommon;

/**
 * OrderEmbeddingBuilder — the single producer of the `orders_embedding` document.
 *
 * Extracted verbatim from the three hooks that each carried their own copy of the
 * same 231 lines (Shop/Orders/Process, ClicShoppingAdmin/Orders/Update and
 * .../UpdateOrderProduct). One store written by three copies of one builder is how
 * a defect gets fixed in one place and stays alive in the two others.
 *
 * The atomic keys come from the language definitions the CALLER loaded, so every
 * caller keeps the group it already used. Those keys are English by design in every
 * language: the document is read by the LLM after the query has been normalised to
 * English, and this builder keys on the literal English prefixes 'product.' and
 * 'history.' to number repeated blocks — a translated key silently loses its index
 * and makes every product in an order collide.
 */
class OrderEmbeddingBuilder
{
  private mixed $app;

  public function __construct()
  {
    if (!Registry::exists('Ecommerce')) {
      Registry::set('Ecommerce', new EcommerceApp());
    }

    $this->app = Registry::get('Ecommerce');
  }

  /**
   * Checks if the embedding already exists for the given order ID.
   *
   * @param int $order_id The order ID.
   * @return bool True if the embedding exists, false otherwise.
   */
  public function embeddingExists(int $order_id): bool
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

    $order = $Q->fetch();

    return is_array($order) ? $order : [];
  }

  /**
   * Retrieves the products associated with the order.
   *
   * @param int $order_id The order ID.
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
   * @return array The order status history associated with the order.
   */
  private function getOrderStatusHistory(int $order_id): array
  {
    $Q = $this->app->db->prepare('SELECT *
                                  FROM :table_orders_status_history
                                  WHERE orders_id = :orders_id
                                  ');
    $Q->bindInt(':orders_id', $order_id);
    $Q->execute();

    return $Q->fetchAll();
  }

  /**
   * Retrieves the order totals associated with the order.
   *
   * @param int $order_id The order ID.
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
   * Builds the embedding document of an order from its current database state.
   *
   * @param int $order_id The order ID.
   * @return string|null Null when the order no longer exists.
   */
  public function buildDocument(int $order_id): ?string
  {
    $order = $this->getOrderDetails($order_id);

    if ($order === []) {
      return null;
    }

    return $this->buildEmbeddingData(
      $order_id,
      $order,
      $this->getOrderProducts($order_id),
      $this->getOrderProductAttributes($order_id),
      $this->getOrderStatusHistory($order_id),
      $this->getOrderTotals($order_id)
    );
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
   * Embeds a document and writes its chunks, replacing the previous ones.
   *
   * @param int $order_id The order ID.
   * @param string $document The document returned by buildDocument().
   * @param bool|null $isUpdate Null resolves it from the store.
   * @return array The NewVector::saveEmbeddingsWithChunks() result.
   */
  public function store(int $order_id, string $document, ?bool $isUpdate = null): array
  {
    $isUpdate ??= $this->embeddingExists($order_id);

    // Extract atomic keys from embedding data for metadata
    $tags = [];
    if (preg_match_all('/^\[([^\]]+)\]:\s*(.+)$/m', $document, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $match) {
        $tags[] = $match[1]; // Store only keys (atomic identifiers)
      }
    }

    $embeddedDocuments = NewVector::createEmbedding(null, $document);

    $baseMetadata = [
      'order_name' => 'Order #' . $order_id,
      'content' => $document,
      'type' => 'orders',  // Entity type (goes in 'type' column)
      'tags' => $tags,
      'source' => ['type' => 'manual', 'name' => 'manual']  // Goes in 'sourcetype' and 'sourcename' columns
    ];

    return NewVector::saveEmbeddingsWithChunks(
      $embeddedDocuments,
      'orders_embedding',
      $order_id,
      null,  // language_id - orders table doesn't have this column
      $baseMetadata,
      $this->app->db,
      $isUpdate
    );
  }

  /**
   * Rebuilds an order document from its current state and replaces its chunks.
   *
   * @param int $order_id The order ID.
   * @return array success / chunks_saved / error, like saveEmbeddingsWithChunks().
   */
  public function regenerate(int $order_id): array
  {
    $document = $this->buildDocument($order_id);

    if ($document === null) {
      return ['success' => false, 'chunks_saved' => 0, 'error' => 'Order ' . $order_id . ' no longer exists'];
    }

    return $this->store($order_id, $document);
  }
}
