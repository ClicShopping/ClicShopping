<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */
namespace ClicShopping\Apps\Tools\MCP\Classes\Shop\EndPoint;


use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\Message;
use ClicShopping\Apps\Tools\MCP\MCP;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;


/**
 * This class handles the business logic for managing customer orders through an API.
 * It provides methods for listing, retrieving, and updating orders, ensuring data integrity and customer ownership.
 */
class OrdersShop
{
  /**
   * @var mixed The database connection instance.
   */
  public mixed $db;

  /**
   * @var mixed The ClicShopping application instance.
   */
  public mixed $app;

  /**
   * @var mixed The customer session instance.
   */
  public mixed $customer;

  /**
   * @var mixed The language instance.
   */
  public mixed $lang;

  /**
   * @var mixed The message handler instance for sending API responses.
   */
  public mixed $message;

  /**
   * Initializes the class by setting up dependencies.
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
    if (Registry::exists('Customer')) {
      $this->customer = Registry::get('Customer');
    }
    if (!Registry::exists('MCP')) {
      Registry::set('MCP', new MCP());
    }
    if (!Registry::exists('Message')) {
      Registry::set('Message', new Message());
    }

    $this->app = Registry::get('MCP');
    $this->lang = Registry::get('Language');
    $this->message = Registry::get('Message');
  }

  /**
   * Retrieves a list of orders for the current customer.
   *
   * @return void
   */
  public function listOrders(): void
  {
    $customerId = $this->getCustomerId();
    if (empty($customerId)) {
      $this->message->sendError('customer_id is required.');
    }

    //
    // Correct Code to be implemented later
    //
    /*
    $limit = filter_var($_GET['limit'] ?? 10, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

    if ($limit === false) {
        $this->message->sendError('limit must be a positive integer.', 400);
    }
    if ($offset === false) {
        $this->message->sendError('offset must be a non-negative integer.', 400);
    }
    */
    //
    // Test Code
    //
    $limit = (int)HTML::sanitize($_GET['limit'] ?? 10);
    $offset = (int)HTML::sanitize($_GET['offset'] ?? 0);

    $Qorders = $this->db->prepare('select o.orders_id,
                                          o.date_purchased,
                                          o.orders_status,
                                          o.customers_name,
                                          o.customers_email_address,
                                          o.delivery_name,
                                          o.delivery_company,
                                          o.delivery_street_address,
                                          o.delivery_suburb,
                                          o.delivery_city,
                                          o.delivery_postcode,
                                          o.delivery_state,
                                          o.delivery_country,
                                          s.orders_status_name
                                    from :table_orders o,
                                          :table_orders_status s
                                    where customers_id = :customers_id
                                    and o.orders_status = s.orders_status_id
                                    and s.language_id = :language_id
                                    order by orders_id desc
                                    limit :limit
                                    offset :offset
                                    ');
    $Qorders->bindInt(':customers_id', $customerId);
    $Qorders->bindInt(':limit', $limit);
    $Qorders->bindInt(':offset', $offset);
    $Qorders->bindInt(':language_id', $this->lang->getId());
    $Qorders->execute();

    $orders = [];
    while ($Qorders->fetch()) {
      $orders[] = [
        'orders_id'       => $Qorders->valueInt('orders_id'),
        'date_purchased'  => $Qorders->value('date_purchased'),
        'orders_status'   => $Qorders->value('orders_status_name'),
        'customers_name'  => Hash::displayDecryptedDataText($Qorders->value('customers_name')),
        'customers_email' => $Qorders->value('customers_email_address'),
        'delivery'        => [
          'name'    => Hash::displayDecryptedDataText($Qorders->value('delivery_name')),
          'company' => Hash::displayDecryptedDataText($Qorders->value('delivery_company')),
          'street'  => Hash::displayDecryptedDataText($Qorders->value('delivery_street_address')),
          'suburb'  => Hash::displayDecryptedDataText($Qorders->value('delivery_suburb')),
          'city'    => Hash::displayDecryptedDataText($Qorders->value('delivery_city')),
          'postcode'=> Hash::displayDecryptedDataText($Qorders->value('delivery_postcode')),
          'state'   => $Qorders->value('delivery_state'),
          'country' => $Qorders->value('delivery_country'),
        ],
      ];
    }
    $this->message->sendSuccess(['orders' => $orders]);
  }

  /**
   * Reads the details of a specific order.
   *
   * @param array $data The request data containing 'order_id'.
   * @return void
   */
  public function readOrder(array $data): void
  {
    $customerId = $this->getCustomerId();

    //
    // Correct Code to be implemented later
    //
    /*
    $orderId = filter_var($data['order_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (empty($customerId)) {
      $this->message->sendError('customer_id is required.');
    }
    if ($orderId === false) {
      $this->message->sendError('order_id is required and must be a positive integer.', 400);
    }
    */
    //
    // Test Code
    //
    $orderId = (int)($data['order_id'] ?? 0);
    if (empty($customerId) || empty($orderId)) {
      $this->message->sendError('customer_id and order_id are required.');
    }

    $Qorder = $this->db->prepare('select o.orders_id,
                                         o.orders_status,
                                         o.orders_status_invoice,
                                         o.date_purchased,
                                         o.last_modified,
                                         o.currency,
                                         o.currency_value,
                                         o.payment_method,
                                         s.orders_status_name
                                  from :table_orders o,
                                       :table_orders_status s
                                  where o.orders_id = :order_id
                                  and o.customers_id = :customers_id
                                  and o.orders_status = s.orders_status_id
                                  and s.language_id = :language_id
                                 ');
    $Qorder->bindInt(':order_id', $orderId);
    $Qorder->bindInt(':customers_id', $customerId);
    $Qorder->bindInt(':language_id', $this->lang->getId());
    $Qorder->execute();

    if ($Qorder->rowCount() === 0) {
      $this->message->sendError('Order not found or access denied.');
    }

    $order = [
      'orders_id' => (int)$Qorder->valueInt('orders_id'),
      'orders_status' => $Qorder->value('orders_status_name'),
      'orders_status_invoice' => (int)$Qorder->valueInt('orders_status_invoice'),
      'date_purchased' => (string)$Qorder->value('date_purchased'),
      'last_modified' => (string)$Qorder->value('last_modified'),
      'currency' => (string)$Qorder->value('currency'),
      'currency_value' => (float)$Qorder->value('currency_value'),
      'payment_method' => (string)$Qorder->value('payment_method')
    ];

    $Qproducts = $this->db->prepare('select products_id,
                                             products_name,
                                             products_price,
                                             final_price,
                                             products_tax,
                                             products_quantity
                                      from :table_orders_products
                                      where orders_id = :order_id
                                      ');
    $Qproducts->bindInt(':order_id', $orderId);
    $Qproducts->execute();

    $products = [];
    while ($Qproducts->fetch()) {
      $products[] = [
        'products_id' => (int)$Qproducts->valueInt('products_id'),
        'products_name' => (string)$Qproducts->value('products_name'),
        'products_price' => (float)$Qproducts->valueDecimal('products_price'),
        'final_price' => (float)$Qproducts->valueDecimal('final_price'),
        'products_tax' => (float)$Qproducts->valueDecimal('products_tax'),
        'products_quantity' => (int)$Qproducts->valueInt('products_quantity')
      ];
    }
    $this->message->sendSuccess(['order' => $order, 'products' => $products]);
  }

  /**
   * Creates a new order. Placeholder method.
   *
   * @param array $data The order data.
   * @return void
   */
  protected function createOrder(array $data): void
  {
     $Qorder = $this->db->prepare('SELECT order_id
                                    FROM :table_orders
                                    ORDER BY order_id DESC
                                    LIMIT 1
                                  ');

    $last_order =   $Qorder->valueInt('order_id');
    $new_order = $last_order + 1;

    $this->message->sendSuccess(['action' => 'create_order', 'order_id' => $new_order]);
  }

  /**
   * Updates an existing order. Placeholder method.
   *
   * @param int $orderId The order ID.
   * @param array $data The data to update.
   * @return void
   */
  protected function updateOrder(int $orderId, array $data): void
  {
    $this->message->sendSuccess(['action' => 'update_order', 'order_id' => $orderId]);
  }

  /**
   * Cancels a customer's order.
   *
   * @param int $orderId The order ID to cancel.
   * @return void
   */
  public function cancelOrder(int $orderId): void
  {
    $customerId = $this->getCustomerId();

    //
    // Correct Code to be implemented later
    //
    /*
    $orderId = filter_var($orderId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (empty($customerId)) {
      $this->message->sendError('customer_id is required.');
    }
    if ($orderId === false) {
      $this->message->sendError('order_id is required and must be a positive integer.', 400);
    }
    */
    //
    // Test Code
    //
    if (empty($customerId) || empty($orderId)) {
      $this->message->sendError('customer_id and order_id are required.');
    }

    $Qorder = $this->db->prepare('select orders_status,
                                           orders_status_invoice
                                  from :table_orders
                                  where orders_id = :order_id
                                  and customers_id = :customers_id
                                  ');
    $Qorder->bindInt(':order_id', $orderId);
    $Qorder->bindInt(':customers_id', $customerId);
    $Qorder->execute();

    if ($Qorder->rowCount() === 0) {
      $this->message->sendError('Order not found or access denied.');
    }

    $currentStatus = (int)$Qorder->valueInt('orders_status');
    $currentInvoiceStatus = (int)$Qorder->valueInt('orders_status_invoice');
    $newStatus = \defined('ORDERS_STATUS_CANCELLED_ID') ? (int)\constant('ORDERS_STATUS_CANCELLED_ID') : $currentStatus;

    if ($newStatus !== $currentStatus) {
      $this->db->save('orders', ['orders_status' => $newStatus, 'last_modified' => 'now()'], ['orders_id' => $orderId]);
    }

    $this->db->save('orders_status_history', [
      'orders_id' => $orderId,
      'orders_status_id' => $newStatus,
      'orders_status_invoice_id' => $currentInvoiceStatus,
      'admin_user_name' => '',
      'date_added' => 'now()',
      'customer_notified' => 0,
      'comments' => '[customer_request] cancel order'
    ]);
    $this->message->sendSuccess(['action' => 'cancel_order', 'order_id' => $orderId, 'orders_status' => $newStatus]);
  }

  /**
   * Sends a message from the customer to the admin.
   *
   * @param int $orderId The order ID.
   * @param string $message The message content.
   * @return void
   */
  public function sendMessageToAdmin(int $orderId, string $message): void
  {
    $customerId = $this->getCustomerId();

    //
    // Correct Code to be implemented later
    //
    /*
    $orderId = filter_var($orderId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (empty($customerId)) {
      $this->message->sendError('customer_id is required.');
    }
    if ($orderId === false) {
      $this->message->sendError('order_id must be a positive integer.', 400);
    }
    if (empty($message)) {
      $this->message->sendError('message is required.', 400);
    }
    */
    //
    // Test Code
    //
    if (empty($customerId) || empty($orderId) || empty($message)) {
      $this->message->sendError('customer_id, order_id and message are required.');
    }

    $Qorder = $this->db->prepare('select orders_status,
                                         orders_status_invoice
                                  from :table_orders
                                  where orders_id = :order_id
                                  and customers_id = :customers_id
                                  ');
    $Qorder->bindInt(':order_id', $orderId);
    $Qorder->bindInt(':customers_id', $customerId);
    $Qorder->execute();

    if ($Qorder->rowCount() === 0) {
      $this->message->sendError('Order not found or access denied.');
    }

    $status = (int)$Qorder->valueInt('orders_status');
    $invoiceStatus = (int)$Qorder->valueInt('orders_status_invoice');
    $this->db->save('orders_status_history', [
      'orders_id' => $orderId,
      'orders_status_id' => $status,
      'orders_status_invoice_id' => $invoiceStatus,
      'admin_user_name' => '',
      'date_added' => 'now()',
      'customer_notified' => 0,
      'comments' => '[customer_message] ' . $message
    ]);
    $this->message->sendSuccess(['action' => 'send_message', 'order_id' => $orderId]);
  }

  /**
   * Retrieves the order history for a specific order.
   *
   * @param int $orderId The order ID.
   * @return void
   */
  public function getOrderHistory(int $orderId): void
  {
    $customerId = $this->getCustomerId();

    //
    // Correct Code to be implemented later
    //
    /*
    $orderId = filter_var($orderId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (empty($customerId)) {
      $this->message->sendError('customer_id is required.');
    }
    if ($orderId === false) {
      $this->message->sendError('order_id must be a positive integer.', 400);
    }
    */
    //
    // Test Code
    //
    if (empty($customerId) || empty($orderId)) {
      $this->message->sendError('customer_id and order_id are required.');
    }

    $Qverify = $this->db->prepare('select orders_id
                                    from :table_orders
                                    where orders_id = :order_id
                                    and customers_id = :customers_id
                                    ');
    $Qverify->bindInt(':order_id', $orderId);
    $Qverify->bindInt(':customers_id', $customerId);
    $Qverify->execute();

    if ($Qverify->rowCount() === 0) {
      $this->message->sendError('Order not found or access denied.');
    }

    $Qhistory = $this->db->prepare('select orders_status_history_id,
                                           orders_status_id,
                                           orders_status_invoice_id,
                                           admin_user_name,
                                           date_added,
                                           customer_notified,
                                           comments
                                    from :table_orders_status_history
                                    where orders_id = :order_id
                                    order by date_added desc
                                    ');
    $Qhistory->bindInt(':order_id', $orderId);
    $Qhistory->execute();

    $history = [];
    while ($Qhistory->fetch()) {
      $history[] = [
        'orders_status_history_id' => (int)$Qhistory->valueInt('orders_status_history_id'),
        'orders_status_id' => (int)$Qhistory->valueInt('orders_status_id'),
        'orders_status_invoice_id' => (int)$Qhistory->valueInt('orders_status_invoice_id'),
        'admin_user_name' => (string)$Qhistory->value('admin_user_name'),
        'date_added' => (string)$Qhistory->value('date_added'),
        'customer_notified' => (int)$Qhistory->valueInt('customer_notified'),
        'comments' => (string)$Qhistory->value('comments')
      ];
    }
    $this->message->sendSuccess(['history' => $history]);
  }

  /**
   * Retrieves the customer ID.
   *
   * @return int|null The customer ID, or null if not found.
   */
  private function getCustomerId(): ?int
  {
    /*
    // Correct Code to be implemented later
    // Prefer authenticated customer if available
    if (!\is_null($this->customer) && method_exists($this->customer, 'isLoggedOn') && $this->customer->isLoggedOn()) {
      if (method_exists($this->customer, 'getID')) {
        return (int)$this->customer->getID();
      }
}
    // Fallback to request data for authenticated API calls
    $cid = HTML::sanitize($_GET['customer_id'] ?? $_POST['customer_id'] ?? null);
    return is_numeric($cid) ? (int)$cid : null;
    */

    // Test Code
    $cid = 1;
    return $cid;
  }
}