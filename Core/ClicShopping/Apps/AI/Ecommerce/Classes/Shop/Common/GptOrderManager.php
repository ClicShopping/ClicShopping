<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\Shop\Common;


use ClicShopping\Apps\Orders\Orders\Classes\Shop\Order;
use ClicShopping\OM\Registry;
use ClicShopping\OM\SimpleLogger;

/**
 * Class GptOrderManager
 *
 * This class is responsible for managing the creation and persistence of orders
 * initiated via an external agent (ACP/OpenAI or UCP/Google), into the ClicShopping
 * database. It handles the direct insertion of order data, products, totals,
 * and status history.
 */

class GptOrderManager
{
  /**
   * @var object The database connection object (from Registry).
   */
  protected object $db;

  /**
   * @var SimpleLogger The simple logger instance for debugging and error logging.
   */
  protected SimpleLogger $logger;

  /**
   * @var object The language object (from Registry).
   */
  protected object $lang;

  /**
   * GptOrderManager constructor.
   *
   * Initializes database and language objects, and sets up the logger.
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->lang = Registry::get('Language');
    if (!Registry::exists('SimpleLogger')) {
      $this->logger = new SimpleLogger('ACP_UCP_ClicShopping');
    } else {
      $this->logger = Registry::get('SimpleLogger');
    }
  }

  /**
   * Creates an order from a GPT checkout session.
   *
   * This is the main public entry point for order creation. It validates the session,
   * performs the database insertions, and logs the process.
   *
   * @param array $sessionData The checkout session data from the agent (ACP or UCP).
   * Expected keys: 'id', 'items', 'total', 'subtotal', 'tax', 'shipping_cost', 'currency'.
   * @param array $customerData Customer information (ID, name, address, contact) to associate with the order.
   * @param array $paymentData Payment information (method, CC details) for the order header.
   * @return array Order result data (order_id, status, message, order_data) on completion.
   * Returns an array with 'status' => 'error' on failure.
   */
  public function createOrderFromSession(array $sessionData, array $customerData = [], array $paymentData = [])
  {
    try {
      // Log the order creation attempt
      $this->logger->info('Attempting to create order from GPT session', [
        'session_id' => $sessionData['id'] ?? 'unknown',
        'items_count' => count($sessionData['items'] ?? [])
      ]);

      // Validate session data
      if (!$this->validateSessionData($sessionData)) {
        throw new \Exception('Invalid session data provided');
      }

      // Create order directly in database
      $this->logger->info('Calling createOrderDirectly', [
        'session_id' => $sessionData['id'] ?? 'unknown',
        'customer_id' => $customerData['id'] ?? 'unknown'
      ]);

      $orderId = $this->createOrderDirectly($sessionData, $customerData, $paymentData);

      $this->logger->info('createOrderDirectly result', [
        'order_id' => $orderId,
        'success' => $orderId !== false
      ]);

      if (!$orderId) {
        throw new \Exception('Failed to create order in database');
      }

      // Log successful order creation
      $this->logger->info('Order successfully created from GPT session', [
        'event' => 'gpt_order_created',
        'order_id' => $orderId,
        'session_id' => $sessionData['id'] ?? 'unknown',
        'total' => $sessionData['total'] ?? 0
      ]);

      return [
        'order_id' => $orderId,
        'status' => 'success',
        'message' => 'Order created successfully',
        'order_data' => $this->getOrderData($orderId)
      ];

    } catch (\Exception $e) {
      // Log the error
      $this->logger->error('Failed to create order from GPT session', [
        'event' => 'gpt_order_error',
        'error' => $e->getMessage(),
        'session_id' => $sessionData['id'] ?? 'unknown'
      ]);

      return [
        'status' => 'error',
        'message' => $e->getMessage(),
        'order_id' => null
      ];
    }
  }

  /**
   * Validates the session data before creating an order.
   *
   * Checks for the presence of items and a positive total value.
   *
   * @param array $sessionData The GPT checkout session data.
   * @return bool True if data is valid, false otherwise.
   */
  private function validateSessionData(array $sessionData): bool
  {
    $hasItems = !empty($sessionData['items']) && is_array($sessionData['items']);
    $hasLineItems = !empty($sessionData['line_items']) && is_array($sessionData['line_items']);

    if (!$hasItems && !$hasLineItems) {
      return false;
    }

    $totals = $this->extractTotalsFromSession($sessionData);
    if ($totals['total'] <= 0) {
      return false;
    }

    return true;
  }

  /**
   * Extracts numeric totals from session data.
   *
   * @param array $sessionData
   * @return array{subtotal:float,tax:float,shipping:float,total:float,currency:string}
   */
  private function extractTotalsFromSession(array $sessionData): array
  {
    $currency = strtoupper($sessionData['currency'] ?? (\defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'EUR'));
    $subtotal = $sessionData['subtotal'] ?? null;
    $tax = $sessionData['tax'] ?? null;
    $shipping = $sessionData['shipping_cost'] ?? null;
    $total = $sessionData['total'] ?? null;

    if (!empty($sessionData['totals']) && is_array($sessionData['totals'])) {
      $map = [];
      foreach ($sessionData['totals'] as $totalRow) {
        if (!empty($totalRow['type']) && isset($totalRow['amount']) && is_numeric($totalRow['amount'])) {
          $map[$totalRow['type']] = (int)$totalRow['amount'];
        }
      }

      if (isset($map['subtotal'])) {
        $subtotal = $map['subtotal'] / 100;
      }
      if (isset($map['tax'])) {
        $tax = $map['tax'] / 100;
      }
      if (isset($map['fulfillment'])) {
        $shipping = $map['fulfillment'] / 100;
      }
      if (isset($map['total'])) {
        $total = $map['total'] / 100;
      }

      if ($total === null && $subtotal !== null) {
        $total = (float)$subtotal + (float)($tax ?? 0) + (float)($shipping ?? 0);
      }
    }

    return [
      'subtotal' => (float)($subtotal ?? 0),
      'tax' => (float)($tax ?? 0),
      'shipping' => (float)($shipping ?? 0),
      'total' => (float)($total ?? 0),
      'currency' => $currency
    ];
  }

  /**
   * Builds minimal items array from line items.
   *
   * @param array $lineItems
   * @return array
   */
  private function buildItemsFromLineItems(array $lineItems): array
  {
    $items = [];

    foreach ($lineItems as $lineItem) {
      $item = $lineItem['item'] ?? [];
      $quantity = (int)($item['quantity'] ?? 1);
      $baseAmount = isset($lineItem['base_amount']) ? (int)$lineItem['base_amount'] : 0;
      $unitPrice = $quantity > 0 ? ($baseAmount / $quantity) / 100 : 0.0;
      $items[] = [
        'id' => (string)($item['id'] ?? ''),
        'title' => (string)($item['id'] ?? ''),
        'price' => $unitPrice,
        'unit_price' => $unitPrice,
        'quantity' => $quantity,
        'metadata' => []
      ];
    }

    return $items;
  }

  /**
   * Sets up basic order information (deprecated/unused in favor of createOrderDirectly).
   *
   * This method is part of a previous approach using the ClicShopping\Apps\Orders\Orders\Classes\Shop\Order class.
   * It's retained for potential future use or compatibility.
   *
   * @param Order $order The order object to populate.
   * @param array $sessionData The session data.
   * @param array $customerData Customer data.
   * @param array $paymentData Payment data.
   */
  private function setupOrderInfo(Order $order, array $sessionData, array $customerData, array $paymentData): void
  {
    $order->info = [
      'order_status' => 1, // Pending
      'order_status_invoice' => 1,
      'currency' => $sessionData['currency'] ?? 'EUR',
      'currency_value' => 1.0,
      'payment_method' => $paymentData['method'] ?? 'gpt_payment',
      'cc_type' => '',
      'cc_owner' => '',
      'cc_number' => '',
      'cc_expires' => '',//integrer orders_status_history

      'date_purchased' => date('Y-m-d H:i:s'),
      'last_modified' => date('Y-m-d H:i:s')
    ];
  }

  /**
   * Sets up order products (deprecated/unused in favor of insertOrderProducts).
   *
   * @param Order $order The order object to populate.
   * @param array $items The array of product items.
   */
  private function setupOrderProducts(Order $order, array $items): void
  {
    $order->products = [];

    foreach ($items as $item) {
      $order->products[] = [
        'id' => $item['id'],
        'name' => $item['title'],
        'model' => $item['metadata']['model'] ?? '',
        'price' => $item['price'],
        'final_price' => $item['unit_price'],
        'qty' => $item['quantity'],
        'tax' => $this->calculateTax($item),
        'attributes' => []
      ];
    }
  }

  /**
   * Sets up customer information (deprecated/unused).
   *
   * @param Order $order The order object to populate.
   * @param array $customerData Customer data.
   */
  private function setupCustomerInfo(Order $order, array $customerData): void
  {
    $order->customer = [
      'id' => $customerData['id'] ?? 0,
      'firstname' => $customerData['firstname'] ?? 'GPT',
      'lastname' => $customerData['lastname'] ?? 'Customer',
      'company' => $customerData['company'] ?? '',
      'street_address' => $customerData['street_address'] ?? '',
      'suburb' => $customerData['suburb'] ?? '',
      'city' => $customerData['city'] ?? '',
      'postcode' => $customerData['postcode'] ?? '',
      'state' => $customerData['state'] ?? '',
      'country' => $customerData['country'] ?? 'FR',
      'format_id' => 1,
      'telephone' => $customerData['telephone'] ?? '',
      'email_address' => $customerData['email_address'] ?? 'gpt@example.com',
      'cellular_phone' => $customerData['cellular_phone'] ?? '',
      'siret' => $customerData['siret'] ?? '',
      'ape' => $customerData['ape'] ?? '',
      'tva_intracom' => $customerData['tva_intracom'] ?? ''
    ];
  }

  /**
   * Sets up billing and shipping addresses (deprecated/unused).
   *
   * @param Order $order The order object to populate.
   * @param array $sessionData The session data.
   * @param array $customerData Customer data.
   */
  private function setupAddresses(Order $order, array $sessionData, array $customerData): void
  {
    $shippingAddress = $sessionData['shipping_address'] ?? [];
    $billingAddress = $sessionData['billing_address'] ?? [];

    $order->billing = [
      'firstname' => $billingAddress['name'] ?? $customerData['firstname'] ?? 'GPT',
      'lastname' => $billingAddress['lastname'] ?? $customerData['lastname'] ?? 'Customer',
      'company' => $billingAddress['company'] ?? $customerData['company'] ?? '',
      'street_address' => $billingAddress['address'] ?? $customerData['street_address'] ?? '',
      'suburb' => $billingAddress['suburb'] ?? $customerData['suburb'] ?? '',
      'city' => $billingAddress['city'] ?? $customerData['city'] ?? '',
      'postcode' => $billingAddress['postal_code'] ?? $customerData['postcode'] ?? '',
      'state' => $billingAddress['state'] ?? $customerData['state'] ?? '',
      'country' => $billingAddress['country'] ?? $customerData['country'] ?? 'FR',
      'format_id' => 1
    ];

    $order->delivery = [
      'firstname' => $shippingAddress['name'] ?? $customerData['firstname'] ?? 'GPT',
      'lastname' => $shippingAddress['lastname'] ?? $customerData['lastname'] ?? 'Customer',
      'company' => $shippingAddress['company'] ?? $customerData['company'] ?? '',
      'street_address' => $shippingAddress['address'] ?? $customerData['street_address'] ?? '',
      'suburb' => $shippingAddress['suburb'] ?? $customerData['suburb'] ?? '',
      'city' => $shippingAddress['city'] ?? $customerData['city'] ?? '',
      'postcode' => $shippingAddress['postal_code'] ?? $customerData['postcode'] ?? '',
      'state' => $shippingAddress['state'] ?? $customerData['state'] ?? '',
      'country' => $shippingAddress['country'] ?? $customerData['country'] ?? 'FR',
      'format_id' => 1
    ];
  }

  /**
   * Sets up payment information (deprecated/unused).
   *
   * @param Order $order The order object to populate.
   * @param array $paymentData Payment data.
   */
  private function setupPaymentInfo(Order $order, array $paymentData): void
  {
    $order->info['payment_method'] = $paymentData['method'] ?? 'gpt_payment';
    $order->info['cc_type'] = $paymentData['cc_type'] ?? '';
    $order->info['cc_owner'] = $paymentData['cc_owner'] ?? '';
    $order->info['cc_number'] = $paymentData['cc_number'] ?? '';
    $order->info['cc_expires'] = $paymentData['cc_expires'] ?? '';
  }

  /**
   * Sets up order totals (deprecated/unused in favor of insertOrderTotals).
   *
   * @param Order $order The order object to populate.
   * @param array $sessionData The session data.
   */
  private function setupOrderTotals(Order $order, array $sessionData): void
  {
    $order->totals = [
      [
        'title' => 'Sub-Total:',
        'text' => number_format($sessionData['subtotal'] ?? 0, 2) . ' EUR',
        'value' => $sessionData['subtotal'] ?? 0,
        'class' => 'ST',
        'sort_order' => 1
      ],
      [
        'title' => 'Tax:',
        'text' => number_format($sessionData['tax'] ?? 0, 2) . ' EUR',
        'value' => $sessionData['tax'] ?? 0,
        'class' => 'TX',
        'sort_order' => 2
      ],
      [
        'title' => 'Shipping:',
        'text' => number_format($sessionData['shipping_cost'] ?? 0, 2) . ' EUR',
        'value' => $sessionData['shipping_cost'] ?? 0,
        'class' => 'SH',
        'sort_order' => 3
      ],
      [
        'title' => 'Total:',
        'text' => number_format($sessionData['total'] ?? 0, 2) . ' EUR',
        'value' => $sessionData['total'] ?? 0,
        'class' => 'TO',
        'sort_order' => 4
      ]
    ];
  }

  /**
   * Calculates tax for an item based on its unit price.
   *
   * NOTE: Uses a hardcoded tax rate of 20%. This should be enhanced for real-world tax logic.
   *
   * @param array $item The product item data, containing 'unit_price'.
   * @return float The calculated tax amount.
   */
  private function calculateTax(array $item): float
  {
    // Simple tax calculation - can be enhanced based on business rules
    $taxRate = 0.20; // 20% tax rate
    return $item['unit_price'] * $taxRate;
  }

  /**
   * Retrieves summary data for an order by its ID.
   *
   * Joins orders table with customers table to get basic customer info.
   *
   * @param int $orderId The ID of the order to retrieve.
   * @return array|null The order data array, or null if the order is not found.
   */
  private function getOrderData(int $orderId): ?array
  {
    $Qorder = $this->db->prepare('
      SELECT o.*,
             c.customers_firstname,
             c.customers_lastname,
             c.customers_email_address
      FROM :table_orders o
      LEFT JOIN :table_customers c ON o.customers_id = c.customers_id
      WHERE o.orders_id = :orders_id
    ');
    $Qorder->bindInt(':orders_id', $orderId);
    $Qorder->execute();

    if ($Qorder->fetch()) {
      return [
        'orders_id' => $Qorder->valueInt('orders_id'),
        'customers_id' => $Qorder->valueInt('customers_id'),
        'customers_name' => $Qorder->value('customers_firstname') . ' ' . $Qorder->value('customers_lastname'),
        'customers_email' => $Qorder->value('customers_email_address'),
        'orders_status' => $Qorder->valueInt('orders_status'),
        'currency' => $Qorder->value('currency'),
        'currency_value' => $Qorder->value('currency_value'),
        'date_purchased' => $Qorder->value('date_purchased'),
        'total' => $Qorder->value('order_total')
      ];
    }

    return null;
  }

  /**
   * Updates the status of an existing order and logs the change.
   *
   * @param int $orderId The ID of the order to update.
   * @param int $status The new status ID.
   * @return bool True on success, false on failure.
   */
  public function updateOrderStatus(int $orderId, int $status): bool
  {
    try {
      $sql_data_array = [
        'orders_status' => $status,
        'last_modified' => date('Y-m-d H:i:s')
      ];

      $this->db->save('orders', $sql_data_array, ['orders_id' => $orderId]);

      // Insert order status history entry
      $this->insertOrderStatusHistory($orderId, $status, 'Agent-driven retail order (ACP/UCP)', 0);

      $this->logger->info('Order status updated', [
        'event' => 'gpt_order_status_updated',
        'order_id' => $orderId,
        'new_status' => $status
      ]);

      return true;
    } catch (\Exception $e) {
      $this->logger->error('Failed to update order status', [
        'event' => 'gpt_order_status_error',
        'order_id' => $orderId,
        'error' => $e->getMessage()
      ]);
      return false;
    }
  }

  /**
   * Retrieves order by ID (public access).
   *
   * @param int $orderId The ID of the order.
   * @return array|null The order data array, or null.
   */
  public function getOrderById(int $orderId): ?array
  {
    return $this->getOrderData($orderId);
  }

  /**
   * Creates the order record directly into the database tables.
   *
   * Inserts records into 'orders', 'orders_total', 'orders_products', and 'orders_status_history'.
   *
   * @param array $sessionData The checkout session data.
   * @param array $customerData Customer information.
   * @param array $paymentData Payment information.
   * @return int|false The new order ID on success, or false on failure.
   */
  public function createOrderDirectly(array $sessionData, array $customerData, array $paymentData)
  {
    try {
      $this->logger->info('Starting createOrderDirectly', [
        'session_id' => $sessionData['id'] ?? 'unknown',
        'customer_id' => $customerData['id'] ?? 'unknown',
        'items_count' => count($sessionData['items'] ?? $sessionData['line_items'] ?? [])
      ]);

      // --- 1. Insert into :table_orders ---
      $totals = $this->extractTotalsFromSession($sessionData);

      $sql_data_array = [
        'customers_id' => $customerData['id'] ?? 0,
        'customers_name' => ($customerData['firstname'] ?? 'GPT') . ' ' . ($customerData['lastname'] ?? 'Customer'),
        'customers_company' => $customerData['company'] ?? '',
        'customers_street_address' => $customerData['street_address'] ?? '',
        'customers_suburb' => $customerData['suburb'] ?? '',
        'customers_city' => $customerData['city'] ?? '',
        'customers_postcode' => $customerData['postcode'] ?? '',
        'customers_state' => $customerData['state'] ?? '',
        'customers_country' => $customerData['country'] ?? 'FR',
        'customers_telephone' => $customerData['telephone'] ?? '',
        'customers_email_address' => $customerData['email_address'] ?? 'gpt@example.com',
        'customers_address_format_id' => 1,
        'delivery_name' => ($customerData['firstname'] ?? 'GPT') . ' ' . ($customerData['lastname'] ?? 'Customer'),
        'delivery_company' => $customerData['company'] ?? '',
        'delivery_street_address' => $customerData['street_address'] ?? '',
        'delivery_suburb' => $customerData['suburb'] ?? '',
        'delivery_city' => $customerData['city'] ?? '',
        'delivery_postcode' => $customerData['postcode'] ?? '',
        'delivery_state' => $customerData['state'] ?? '',
        'delivery_country' => $customerData['country'] ?? 'FR',
        'delivery_address_format_id' => 1,
        'billing_name' => ($customerData['firstname'] ?? 'GPT') . ' ' . ($customerData['lastname'] ?? 'Customer'),
        'billing_company' => $customerData['company'] ?? '',
        'billing_street_address' => $customerData['street_address'] ?? '',
        'billing_suburb' => $customerData['suburb'] ?? '',
        'billing_city' => $customerData['city'] ?? '',
        'billing_postcode' => $customerData['postcode'] ?? '',
        'billing_state' => $customerData['state'] ?? '',
        'billing_country' => $customerData['country'] ?? 'FR',
        'billing_address_format_id' => 1,
        'payment_method' => $paymentData['method'] ?? 'gpt_payment',
        'cc_type' => $paymentData['cc_type'] ?? '',
        'cc_owner' => $paymentData['cc_owner'] ?? '',
        'cc_number' => $paymentData['cc_number'] ?? '',
        'cc_expires' => substr($paymentData['cc_expires'] ?? '', 0, 4),
        'date_purchased' => date('Y-m-d H:i:s'),
        'orders_status' => 1, // Pending
        'orders_status_invoice' => 1,
        'currency' => $totals['currency'] ?? 'EUR',
        'currency_value' => 1.0,
        'customers_cellular_phone' => $customerData['cellular_phone'] ?? ''
      ];

      try {
        $this->db->save('orders', $sql_data_array);
        $orderId = $this->db->lastInsertId();
      } catch (\Exception $e) {
        $this->logger->error('Database save failed for orders table', [
          'error' => $e->getMessage(),
        ]);
        throw $e;
      }

      if (!$orderId) {
        $this->logger->error('Failed to get order ID after insert');
        return false;
      }

      // --- 2. Insert order totals ---
      $this->insertOrderTotals($orderId, $sessionData);

      // --- 3. Insert order products ---
      $items = $sessionData['items'] ?? [];
      if (empty($items)) {
        throw new \Exception('Order items are required to create order products.');
      }
      $this->insertOrderProducts($orderId, $items);

      // --- 4. Insert initial order status history entry ---
      $this->insertOrderStatusHistory($orderId, 1, 'Agent-driven retail order (ACP/UCP)', 0);

      return $orderId;

    } catch (\Exception $e) {
      $this->logger->error('Failed to create order directly', [
        'event' => 'gpt_order_direct_error',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'session_id' => $sessionData['id'] ?? 'unknown'
      ]);
      return false;
    }
  }

  /**
   * Inserts the order's financial totals (sub-total, tax, shipping, total) into the :table_orders_total table.
   *
   * @param int $orderId The ID of the newly created order.
   * @param array $sessionData The session data containing 'subtotal', 'tax', 'shipping_cost', and 'total'.
   */
  private function insertOrderTotals(int $orderId, array $sessionData): void
  {
    $totalsData = $this->extractTotalsFromSession($sessionData);
    $currency = $totalsData['currency'] ?? 'EUR';

    $totals = [
      [
        'title' => 'Sub-Total:',
        'text' => number_format($totalsData['subtotal'], 2) . ' ' . $currency,
        'value' => $totalsData['subtotal'],
        'class' => 'ot_subtotal',
        'sort_order' => 1
      ],
      [
        'title' => 'Tax:',
        'text' => number_format($totalsData['tax'], 2) . ' ' . $currency,
        'value' => $totalsData['tax'],
        'class' => 'ot_tax',
        'sort_order' => 2
      ],
      [
        'title' => 'Shipping:',
        'text' => number_format($totalsData['shipping'], 2) . ' ' . $currency,
        'value' => $totalsData['shipping'],
        'class' => 'ot_shipping',
        'sort_order' => 3
      ],
      [
        'title' => 'Total:',
        'text' => number_format($totalsData['total'], 2) . ' ' . $currency,
        'value' => $totalsData['total'],
        'class' => 'ot_total',
        'sort_order' => 4
      ]
    ];

    foreach ($totals as $total) {
      $sql_data_array = [
        'orders_id' => $orderId,
        'title' => $total['title'],
        'text' => $total['text'],
        'value' => $total['value'],
        'class' => $total['class'],
        'sort_order' => $total['sort_order']
      ];

      $this->db->save('orders_total', $sql_data_array);
    }
  }

  /**
   * Inserts individual product items for the order into the :table_orders_products table.
   *
   * @param int $orderId The ID of the newly created order.
   * @param array $items The array of product items from the checkout session.
   */
  private function insertOrderProducts(int $orderId, array $items): void
  {
    foreach ($items as $item) {
      $sql_data_array = [
        'orders_id' => $orderId,
        'products_id' => (int)$item['id'],
        'products_model' => $item['metadata']['model'] ?? '',
        'products_name' => $item['title'],
        'products_price' => (float)$item['price'],
        'final_price' => (float)$item['unit_price'],
        'products_tax' => $this->calculateTax($item),
        'products_quantity' => (int)$item['quantity']
      ];

      $this->db->save('orders_products', $sql_data_array);
    }
  }

  /**
   * Inserts an entry into the orders status history table (:table_orders_status_history).
   *
   * @param int $orderId The ID of the order.
   * @param int $statusId The status ID (e.g., 1 for Pending).
   * @param string $comments The comment to associate with the status change.
   * @param int $customerNotified Flag to indicate if the customer should be notified (0 or 1). Default: 0.
   */
  private function insertOrderStatusHistory(int $orderId, int $statusId, string $comments = 'Agent-driven retail order (ACP/UCP)', int $customerNotified = 0): void
  {
    $sql_data_array = [
      'orders_id' => $orderId,
      'orders_status_id' => $statusId,
      'date_added' => date('Y-m-d H:i:s'),
      'customer_notified' => $customerNotified,
      'comments' => $comments
    ];

    $this->db->save('orders_status_history', $sql_data_array);
  }
}
