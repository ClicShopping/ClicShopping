<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Orders\Orders\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Hash;
use ClicShopping\OM\Registry;

class OrderAdmin extends \ClicShopping\Apps\Orders\Orders\Classes\Shop\Order
{
  public function __construct($order_id)
  {
    $this->info = [];
    $this->totals = [];
    $this->products = [];
    $this->customer = [];
    $this->delivery = [];

    $this->query($order_id);
  }

  /**
   * Retrieves and populates order details based on the provided order ID.
   * It includes information about the order, customer, delivery, billing,
   * products, and their attributes, as well as any associated totals.
   *
   * @param int $order_id The ID of the order to retrieve and process.
   * @return void
   */
  public function query(int $order_id): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $Qorder = $CLICSHOPPING_Db->get([
      'orders o',
      'orders_status s'
    ], [
      'o.*',
      's.orders_status_name'
    ], [
        'o.orders_id' => (int)$order_id,
        'o.orders_status' => ['rel' => 's.orders_status_id'],
        's.language_id' => $CLICSHOPPING_Language->getId()
      ]
    );

    $Qtotals = $CLICSHOPPING_Db->get('orders_total', [
      'title',
      'text',
      'class'
    ], [
      'orders_id' => (int)$order_id
    ],
      'sort_order'
    );

    while ($Qtotals->fetch()) {
      $this->totals[] = [
        'title' => $Qtotals->value('title'),
        'text' => $Qtotals->value('text'),
        'class' => $Qtotals->value('class')
      ];
    }

    $this->info = [
      'id' => $Qorder->valueInt('orders_id'),
      'total' => null,
      'currency' => $Qorder->value('currency'),
      'currency_value' => $Qorder->value('currency_value'),
      'payment_method' => $Qorder->value('payment_method'),
      'cc_type' => $Qorder->value('cc_type'),
      'cc_owner' => $Qorder->value('cc_owner'),
      'cc_number' => $Qorder->value('cc_number'),
      'cc_expires' => $Qorder->value('cc_expires'),
      'date_purchased' => $Qorder->value('date_purchased'),
      'status' => $Qorder->value('orders_status_name'),
      'orders_status' => $Qorder->valueInt('orders_status'),
      'orders_status_invoice' => $Qorder->valueInt('orders_status_invoice'),
      'last_modified' => $Qorder->value('last_modified'),
      'erp_invoice' => $Qorder->valueInt('erp_invoice')
    ];

    foreach ($this->totals as $t) {
      if ($t['class'] == 'ot_total' || $t['class'] == 'TO') {
        $this->info['total'] = $t['text'];
        break;
      }
    }

    $this->customer = [
      'name' => Hash::displayDecryptedDataText($Qorder->value('customers_name')),
      'company' => Hash::displayDecryptedDataText($Qorder->value('customers_company')),
      'siret' => $Qorder->value('customers_siret'),
      'ape' => $Qorder->value('customers_ape'),
      'tva_intracom' => $Qorder->value('customers_tva_intracom'),
      'street_address' =>  Hash::displayDecryptedDataText($Qorder->value('customers_street_address')),
      'suburb' => Hash::displayDecryptedDataText($Qorder->value('customers_suburb')),
      'city' => Hash::displayDecryptedDataText($Qorder->value('customers_city')),
      'postcode' => Hash::displayDecryptedDataText($Qorder->value('customers_postcode')),
      'state' => $Qorder->value('customers_state'),
      'country' => $Qorder->value('customers_country'),
      'format_id' => $Qorder->value('customers_address_format_id'),
      'telephone' => Hash::displayDecryptedDataText($Qorder->value('customers_telephone')),
      'cellular_phone' => Hash::displayDecryptedDataText($Qorder->value('customers_cellular_phone')),
      'email_address' => $Qorder->value('customers_email_address'),
      'client_computer_ip' => $Qorder->value('client_computer_ip'),
      'provider_name_client' => $Qorder->value('provider_name_client')
    ];

    $this->delivery = [
      'name' => Hash::displayDecryptedDataText($Qorder->value('delivery_name')),
      'company' => Hash::displayDecryptedDataText($Qorder->value('delivery_company')),
      'street_address' => Hash::displayDecryptedDataText($Qorder->value('delivery_street_address')),
      'suburb' => Hash::displayDecryptedDataText($Qorder->value('delivery_suburb')),
      'city' => Hash::displayDecryptedDataText($Qorder->value('delivery_city')),
      'postcode' => Hash::displayDecryptedDataText($Qorder->value('delivery_postcode')),
      'state' => $Qorder->value('delivery_state'),
      'country' => $Qorder->value('delivery_country'),
      'format_id' => $Qorder->value('delivery_address_format_id')
    ];

    $this->billing = [
      'name' => Hash::displayDecryptedDataText($Qorder->value('billing_name')),
      'company' => Hash::displayDecryptedDataText($Qorder->value('billing_company')),
      'street_address' => Hash::displayDecryptedDataText($Qorder->value('billing_street_address')),
      'suburb' => Hash::displayDecryptedDataText($Qorder->value('billing_suburb')),
      'city' => Hash::displayDecryptedDataText($Qorder->value('billing_city')),
      'postcode' => Hash::displayDecryptedDataText($Qorder->value('billing_postcode')),
      'state' => $Qorder->value('billing_state'),
      'country' => $Qorder->value('billing_country'),
      'format_id' => $Qorder->value('billing_address_format_id')
    ];

    $index = 0;

    $Qproducts = $CLICSHOPPING_Db->get('orders_products', [
      'orders_products_id',
      'products_id',
      'products_name',
      'products_model',
      'products_price',
      'products_tax',
      'products_quantity',
      'final_price'
    ], [
        'orders_id' => (int)$order_id
      ]
    );

    while ($Qproducts->fetch()) {
      $this->products[$index] = [
        'orders_products_id' => $Qproducts->value('orders_products_id'),
        'qty' => $Qproducts->value('products_quantity'),
        'products_id' => $Qproducts->valueInt('products_id'),
        'name' => $Qproducts->value('products_name'),
        'model' => $Qproducts->value('products_model'),
        'tax' => $Qproducts->value('products_tax'),
        'price' => $Qproducts->valueDecimal('products_price'),
        'final_price' => $Qproducts->valueDecimal('final_price')
      ];

      $Qattributes = $CLICSHOPPING_Db->get('orders_products_attributes', [
        'products_options',
        'products_options_values',
        'options_values_price',
        'price_prefix',
        'products_attributes_reference'
      ], [
          'orders_id' => (int)$order_id,
          'orders_products_id' => $Qproducts->valueInt('orders_products_id')
        ]
      );

      $Qattributes->execute();

      if ($Qattributes->fetch() !== false) {
        $i = 0;

        do {
          $this->products[$index]['attributes'][$i] = [
            'option' => $Qattributes->value('products_options'),
            'value' => $Qattributes->value('products_options_values'),
            'prefix' => $Qattributes->value('price_prefix'),
            'price' => $Qattributes->value('options_values_price'),
            'reference' => $Qattributes->value('products_attributes_reference')
          ];

          $i++;
        } while ($Qattributes->fetch());
      }
      $index++;
    }

    $CLICSHOPPING_Hooks->call('OrderAdmin', 'Query');
  }

  /**
   * Removes an order from the database along with its related records.
   * Optionally, restocks the products associated with the order.
   *
   * @param int $order_id The ID of the order to be removed.
   * @param bool $restock Indicates whether to restock the products from the order.
   *                      If true, the order's product quantities will be added back
   *                      to the available inventory.
   * @return void
   */
  public static function removeOrder(int $order_id, bool $restock = false): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    if (isset($restock)) {
      $Qproducts = $CLICSHOPPING_Db->get('orders_products', [
        'products_id',
        'products_quantity'
      ], [
          'orders_id' => (int)$order_id
        ]
      );

      $products_ids = [];

      while ($Qproducts->fetch()) {
        $product_id = $Qproducts->valueInt('products_id');
        $products_ids[] = $product_id;

        $Qupdate = $CLICSHOPPING_Db->prepare('update :table_products
                                                set products_quantity = products_quantity + :qty,
                                                    products_ordered = products_ordered - :qty
                                                where products_id = :products_id
                                               ');
        $Qupdate->bindInt(':qty', $Qproducts->valueInt('products_quantity'));
        $Qupdate->bindInt(':products_id', $product_id);
        $Qupdate->execute();
      }

      // Delete products_groups for ALL products in the order (not just the last one)
      if (!empty($products_ids)) {
        // Build parameterized delete with named placeholders
        $conditions = [];
        $params = [];
        foreach ($products_ids as $idx => $pid) {
          $placeholder = ':pid_' . $idx;
          $conditions[] = 'products_id = ' . $placeholder;
          $params[$placeholder] = (int)$pid;
        }
        $where_clause = implode(' OR ', $conditions);
        $delete_sql = 'delete from :table_products_groups where ' . $where_clause;
        $Qdelete = $CLICSHOPPING_Db->prepare($delete_sql);
        foreach ($params as $name => $value) {
          $Qdelete->bindInt($name, $value);
        }
        $Qdelete->execute();
      }
    }

    $CLICSHOPPING_Db->delete('orders', ['orders_id' => (int)$order_id]);
    $CLICSHOPPING_Db->delete('orders_products', ['orders_id' => (int)$order_id]);
    $CLICSHOPPING_Db->delete('orders_products_attributes', ['orders_id' => (int)$order_id]);
    $CLICSHOPPING_Db->delete('orders_status_history', ['orders_id' => (int)$order_id]);
    $CLICSHOPPING_Db->delete('orders_total', ['orders_id' => (int)$order_id]);
    $CLICSHOPPING_Db->delete('orders_pages_manager', ['orders_id' => (int)$order_id]);
    $CLICSHOPPING_Db->delete('orders_embedding', ['entity_id' => (int)$order_id]);

    $CLICSHOPPING_Hooks->call('OrderAdmin', 'removeOrder');
  }

  /**
   * Retrieves the list of all order statuses available in the system with their IDs and names
   * based on the current language setting.
   *
   * @return array An array of order statuses, where each status is represented as an associative
   *               array with 'id' (the unique identifier of the status) and 'text' (the name of the status).
   */

  public static function getOrdersStatus(): array
  {
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Db = Registry::get('Db');

    $orders_status_array = [];

    $Qstatus = $CLICSHOPPING_Db->get('orders_status',
      ['orders_status_id',
      'orders_status_name'
    ], [
      'language_id' => (int)$CLICSHOPPING_Language->getId()
    ],
      'orders_status_id'
    );

    while ($Qstatus->fetch()) {
      $orders_status_array[] = ['id' => $Qstatus->valueInt('orders_status_id'),
        'text' => $Qstatus->value('orders_status_name')
      ];
    }

    return $orders_status_array;
  }

  /**
   * Retrieves the file path or URL of the PDF invoice logo if it exists.
   *
   * This method checks for the presence of the invoice logo file in the specified
   * directory under the shop's template images. If the file exists, it returns
   * its URL; otherwise, it returns false.
   *
   * @return string|bool Returns the URL of the invoice logo as a string if the file
   * exists, or false if the file does not exist.
   */
  public static function getOrderPdfInvoiceLogo(): string|bool
  {
    $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

    if (is_file($CLICSHOPPING_Template->getDirectoryPathTemplateShopImages() . 'logos/invoice/' . INVOICE_LOGO)) {
      $result = $CLICSHOPPING_Template->getHttpTemplateShopImages() . 'logos/invoice/' . INVOICE_LOGO;
    } else {
      $result = false;
    }

    return $result;
  }
}