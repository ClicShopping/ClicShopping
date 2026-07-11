<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Classes\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

use ClicShopping\Sites\Shop\AddressBook;
use ClicShopping\Sites\Shop\Tax;

use ClicShopping\Apps\Configuration\TemplateEmail\Classes\Shop\TemplateEmail;
use ClicShopping\Apps\Marketing\DiscountCoupon\Classes\Shop\DiscountCouponCustomer;
use function count;
use function defined;
use function is_array;
use function is_null;
use function is_object;

class Order
{
  public array $info;
  public array $totals;
  public array $products;
  public array $customer;
  public array $delivery;
  public array $billing;
  public int $order_id;
  public string $comment;
  protected int $id;
  protected int $insertID;
  public $coupon;
  public $content_type;

  private mixed $db;
  private mixed $lang;
  protected $mail;
  private ?OrderNotifier $notifier = null;
  private ?PaymentModuleResolver $paymentResolver = null;
  private ?OrderStockManager $stockManager = null;
  private ?OrderWriter $orderWriter = null;
  private ?OrderCartBuilder $cartBuilder = null;

  public function __construct( int|null $order_id = null)
  {
    $this->db = Registry::get('Db');
    $this->lang = Registry::get('Language');
    $this->mail = Registry::get('Mail');

    $this->info = [];
    $this->totals = [];
    $this->products = [];
    $this->customer = [];
    $this->delivery = [];
    $this->billing = [];

    if (isset($_GET['order_id']) && is_numeric($_GET['order_id'])) {
      $this->id = (int)$_GET['order_id'];
      $this->query($this->id);
    } elseif (!is_null($order_id)) {
      $this->query((int)$order_id);
    } else {
      $this->cart();
    }
  }

  /**
   * Retrieves and processes order information, including order details, customer information,
   * delivery and billing addresses, product details, and various related attributes.
   *
   * @param int $order_id The ID of the order to fetch data for.
   * @return void
   */
  public function query(int $order_id)
  {
    $order_total = $shipping_title = '';

    $Qorder = $this->db->prepare('select *
                                    from :table_orders
                                    where orders_id = :orders_id
                                   ');
    $Qorder->bindInt(':orders_id', $order_id);
    $Qorder->execute();

// orders total
    $Qtotals = $this->db->prepare('select title,
                                             text
                                     from :table_orders_total
                                     where orders_id = :orders_id
                                     order by sort_order
                                    ');
    $Qtotals->bindInt(':orders_id', $order_id);
    $Qtotals->execute();

    while ($Qtotals->fetch()) {
      $this->totals[] = [
        'title' => $Qtotals->value('title'),
        'text' => $Qtotals->value('text')
      ];

      if ($Qtotals->value('class') == 'ot_total' || $Qtotals->value('class') == 'TO') {
        $order_total = strip_tags($Qtotals->value('text'));
      } elseif ($Qtotals->value('class') == 'ot_shipping' || $Qtotals->value('class') == 'SH') {
        $shipping_title = strip_tags($Qtotals->value('title'));

        if (substr($shipping_title, -1) == ':') {
          $shipping_title = substr($shipping_title, 0, -1);
        }
      }
    }

// order status
    $Qstatus = $this->db->prepare('select orders_status_name
                                     from :table_orders_status
                                     where orders_status_id = :orders_status_id
                                     and language_id = :language_id
                                    ');
    $Qstatus->bindInt(':orders_status_id', (int)$Qorder->value('orders_status'));
    $Qstatus->bindInt(':language_id', $this->lang->getId());
    $Qstatus->execute();

// status invoice
    $QorderStatusInvoice = $this->db->prepare('select orders_status_invoice_name
                                                 from :table_orders_status_invoice
                                                 where orders_status_invoice_id = :orders_status_invoice_id
                                                 and language_id = :language_id
                                                ');
    $QorderStatusInvoice->bindInt(':orders_status_invoice_id', $Qorder->value('orders_status_invoice'));
    $QorderStatusInvoice->bindInt(':language_id', $this->lang->getId());
    $QorderStatusInvoice->execute();

    $this->info = [
      'currency' => $Qorder->value('currency'),
      'currency_value' => $Qorder->valueDecimal('currency_value'),
      'payment_method' => $Qorder->value('payment_method'),
      'cc_type' => $Qorder->value('cc_type'),
      'cc_owner' => $Qorder->value('cc_owner'),
      'cc_number' => $Qorder->value('cc_number'),
      'cc_expires' => $Qorder->value('cc_expires'),
      'date_purchased' => $Qorder->value('date_purchased'),
      'orders_status' => $Qstatus->value('orders_status_name'),
      'orders_status_invoice' => $QorderStatusInvoice->value('orders_status_invoice_name'),
      'last_modified' => $Qorder->value('last_modified'),
      'total' => $order_total,
      'shipping_method' => $shipping_title
    ];

    $this->customer = [
      'id' => $Qorder->valueInt('customers_id'),
      'group_id' => $Qorder->valueInt('customers_group_id'),
      'name' => Hash::displayDecryptedDataText($Qorder->value('customers_name')),
      'company' => $Qorder->value('customers_company'),
      'street_address' => $Qorder->value('customers_street_address'),
      'suburb' => $Qorder->value('customers_suburb'),
      'city' => $Qorder->value('customers_city'),
      'postcode' => $Qorder->value('customers_postcode'),
      'state' => $Qorder->value('customers_state'),
      'country' => array('title' => $Qorder->value('customers_country')),
      'format_id' => $Qorder->valueInt('customers_address_format_id'),
      'telephone' => $Qorder->value('customers_telephone'),
      'cellular_phone' => $Qorder->value('customers_cellular_phone'),
      'email_address' => $Qorder->value('customers_email_address')
    ];

    $this->delivery = [
      'name' => Hash::displayDecryptedDataText($Qorder->value('delivery_name')),
      'company' => $Qorder->value('delivery_company'),
      'street_address' => Hash::displayDecryptedDataText($Qorder->value('delivery_street_address')),
      'suburb' => Hash::displayDecryptedDataText($Qorder->value('delivery_suburb')),
      'city' => Hash::displayDecryptedDataText($Qorder->value('delivery_city')),
      'postcode' => Hash::displayDecryptedDataText($Qorder->value('delivery_postcode')),
      'state' => $Qorder->value('delivery_state'),
      'country' => array('title' => $Qorder->value('delivery_country')),
      'format_id' => $Qorder->valueInt('delivery_address_format_id')
    ];

    if (empty($this->delivery['name']) && empty($this->delivery['street_address'])) {
      $this->delivery = false;
    }

    $this->billing = [
      'name' => Hash::displayDecryptedDataText($Qorder->value('billing_name')),
      'company' => $Qorder->value('billing_company'),
      'street_address' => Hash::displayDecryptedDataText($Qorder->value('billing_street_address')),
      'suburb' => Hash::displayDecryptedDataText($Qorder->value('billing_suburb')),
      'city' => Hash::displayDecryptedDataText($Qorder->value('billing_city')),
      'postcode' => Hash::displayDecryptedDataText($Qorder->value('billing_postcode')),
      'state' => $Qorder->value('billing_state'),
      'country' => array('title' => $Qorder->value('billing_country')),
      'format_id' => $Qorder->valueInt('billing_address_format_id')
    ];

    $index = 0;

    $QOrdersProducts = $this->db->prepare('select products_quantity,
                                                    products_id,
                                                    products_name,
                                                    products_model,
                                                    products_tax,
                                                    products_price,
                                                    final_price,
                                                    orders_products_id
                                            from :table_orders_products
                                            where orders_id = :orders_id
                                          ');
    $QOrdersProducts->bindInt(':orders_id', $order_id);
    $QOrdersProducts->execute();

    while ($QOrdersProducts->fetch()) {
      $this->products[$index] = [
        'qty' => $QOrdersProducts->valueInt('products_quantity'),
        'id' => $QOrdersProducts->valueInt('products_id'),
        'name' => $QOrdersProducts->value('products_name'),
        'model' => $QOrdersProducts->value('products_model'),
        'tax' => $QOrdersProducts->valueDecimal('products_tax'),
        'price' => $QOrdersProducts->valueDecimal('products_price'),
        'final_price' => $QOrdersProducts->valueDecimal('final_price')
      ];

      $subindex = 0;

//*********************
// attributes
//*********************
      $Qattributes = $this->db->prepare('select *
                                           from :table_orders_products_attributes
                                           where orders_id = :orders_id
                                           and orders_products_id = :orders_products_id
                                         ');

      $Qattributes->bindInt(':orders_id', $order_id);
      $Qattributes->bindInt(':orders_products_id', $QOrdersProducts->valueInt('orders_products_id'));
      $Qattributes->execute();

      if ($Qattributes->fetch() !== false) {
        do {
          $this->products[$index]['attributes'][$subindex] = [
            'option' => $Qattributes->value('products_options'),
            'value' => $Qattributes->value('products_options_values'),
            'prefix' => $Qattributes->value('price_prefix'),
            'price' => $Qattributes->valueDecimal('options_values_price'),
            'reference' => $Qattributes->value('products_attributes_reference')
          ];

          $subindex++;

        } while ($Qattributes->fetch());
      }

      $this->info['tax_groups']["{$this->products[$index]['tax']}"] = '1';

      $index++;
    }
  }

  /**
   * Initializes and returns an array representing customer information with default values.
   *
   * @return array An associative array with keys for customer and address details,
   *               all initialized to null or default values.
   */
  protected function getCustomerArrayInitialization(): array
  {
    $customer_address = [
      'customers_firstname' => null,
      'customers_lastname' => null,
      'customers_telephone' => null,
      'customers_cellular_phone' => null,
      'customers_email_address' => null,
      'customers_siret' => null,
      'customers_ape' => null,
      'customers_group_id' => null,
      'customers_tva_intracom' => null,
      'entry_company' => null,
      'entry_street_address' => null,
      'entry_suburb' => null,
      'entry_postcode' => null,
      'entry_city' => null,
      'entry_zone_id' => null,
      'zone_name' => null,
      'countries_id' => null,
      'countries_name' => null,
      'countries_iso_code_2' => null,
      'countries_iso_code_3' => null,
      'address_format_id' => 0,
      'entry_state' => null
    ];

    return $customer_address;
  }

  /**
   * Retrieves customer details based on the provided customer ID.
   *
   * @param int $id The unique identifier of the customer to retrieve.
   *
   * @return array An associative array containing customer details, including
   *               personal information, address, and associated country data.
   */
  protected function getcustomer(int $id): array
  {
    $Qcustomer = $this->db->prepare('select c.customers_firstname,
                                               c.customers_lastname,
                                               c.customers_group_id,
                                               c.customers_company,
                                               c.customers_telephone,
                                               c.customers_cellular_phone,
                                               c.customers_email_address,
                                               c.customers_siret,
                                               c.customers_ape,
                                               c.customers_tva_intracom,
                                               ab.entry_company,
                                               ab.entry_street_address,
                                               ab.entry_suburb,
                                               ab.entry_postcode,
                                               ab.entry_city,
                                               ab.entry_zone_id,
                                               z.zone_name,
                                               co.countries_id,
                                               co.countries_name,
                                               co.countries_iso_code_2,
                                               co.countries_iso_code_3,
                                               co.address_format_id,
                                               ab.entry_state
                                     from :table_customers c,
                                          :table_address_book ab left join :table_zones z on (ab.entry_zone_id = z.zone_id)
                                                                 left join :table_countries co on (ab.entry_country_id = co.countries_id)
                                    where c.customers_id = :customers_id
                                    and ab.customers_id = :customers_id
                                    and c.customers_default_address_id = ab.address_book_id
                                    ');
    $Qcustomer->bindInt(':customers_id', $id);
    $Qcustomer->execute();

    return $Qcustomer->toArray();
  }

  /**
   * Handles the processing of the shopping cart, including initializing customer address information,
   * determining shipping and billing addresses, configuring tax addresses, and finalizing order details.
   * This method takes into account customer group types (B2B or regular), session variables for
   * payment and shipping, and the content type of the cart.
   *
   * @return void
   */
  public function cart(): void
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Currencies = Registry::get('Currencies');
    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $CLICSHOPPING_Tax = Registry::get('Tax');

    $this->content_type = $CLICSHOPPING_ShoppingCart->get_content_type();

    if (($this->content_type != 'virtual') && (!isset($_SESSION['sendto']))) {
      $_SESSION['sendto'] = $CLICSHOPPING_Customer->getDefaultAddressID();
    }

// recuperation des informations clients B2B pour enregistrement commandes
    if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
      $customer_address = $this->getCustomerArrayInitialization();

      if ($CLICSHOPPING_Customer->getID()) {
        $customer_address = $this->getcustomer($CLICSHOPPING_Customer->getID());
      }

// recuperation des informations clients normaux pour enregistrement commandes avec en plus infos sur customers_group_id
    } else {
      $customer_address = $this->getCustomerArrayInitialization();

      if ($CLICSHOPPING_Customer->getID()) {
        $customer_address = $this->getcustomer($CLICSHOPPING_Customer->getID());
      }
    }

    $resolvedAddresses = $this->cartBuilder()->resolveAddresses($CLICSHOPPING_Customer, $this->content_type);
    $shipping_address = $resolvedAddresses['shipping_address'];
    $billing_address = $resolvedAddresses['billing_address'];
    $tax_address = $resolvedAddresses['tax_address'];

    if ((isset($_SESSION['payment']) && is_array($_SESSION['payment'])) || (isset($_SESSION['shipping']) && is_array($_SESSION['shipping']))) {
      $this->info = [
        'order_status' => defined('DEFAULT_ORDERS_STATUS_ID') ? (int)DEFAULT_ORDERS_STATUS_ID : 0,
        'order_status_invoice' => defined('DEFAULT_ORDERS_STATUS_INVOICE_ID') ? (int)DEFAULT_ORDERS_STATUS_INVOICE_ID : 0,
        'currency' => $_SESSION['currency'],
        'currency_value' => $CLICSHOPPING_Currencies->currencies[$_SESSION['currency']]['value'],
        'payment_method' => $_SESSION['payment'] ?? '',
        'cc_type' => '',
        'cc_owner' => '',
        'cc_number' => '',
        'cc_expires' => '',
        'shipping_method' => isset($_SESSION['shipping']) ? $_SESSION['shipping']['title'] : '',
        'shipping_cost' => isset($_SESSION['shipping']) ? $_SESSION['shipping']['cost'] : 0,
        'subtotal' => 0,
        'tax' => 0,
        'tax_groups' => [],
        'comments' => isset($_SESSION['comments']) && !empty($_SESSION['comments']) ? $_SESSION['comments'] : ''
      ];
    } else {
      $this->info = [
        'shipping_cost' => 0,
        'subtotal' => 0,
        'tax' => 0,
        'tax_groups' => [],
      ];
    }

    $paymentModule = $this->paymentResolver()->resolve($_SESSION['payment'] ?? null);
    $this->info = $this->paymentResolver()->applyToInfo($paymentModule, $this->info);

// prise en compte de la compagnie en fonction du mode B2B ou non
    if (!empty($customer_address['customers_company'])) {
      $company_name = $customer_address['customers_company'];
    } else {
      $company_name = $customer_address['entry_company'];
    }

    if (is_array($customer_address)) {
      $this->customer = [
        'firstname' => $customer_address['customers_firstname'],
        'customers_group_id' => $customer_address['customers_group_id'],
        'lastname' => $customer_address['customers_lastname'],
        'company' => $company_name,
        'street_address' => $customer_address['entry_street_address'],
        'suburb' => $customer_address['entry_suburb'],
        'city' => $customer_address['entry_city'],
        'postcode' => $customer_address['entry_postcode'],
        'state' => ((!is_null($customer_address['entry_state'])) ? $customer_address['entry_state'] : $customer_address['zone_name']),
        'zone_id' => $customer_address['entry_zone_id'],
        'country' => [
          'id' => $customer_address['countries_id'],
          'title' => $customer_address['countries_name'],
          'iso_code_2' => $customer_address['countries_iso_code_2'],
          'iso_code_3' => $customer_address['countries_iso_code_3']
        ],
        'format_id' => $customer_address['address_format_id'],
        'telephone' => $customer_address['customers_telephone'],
        'cellular_phone' => $customer_address['customers_cellular_phone'],
        'email_address' => $customer_address['customers_email_address']
      ];

// recuperation des informations societes pour les clients B2B qui est transmit au fichier checkout_process.php
      if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
        $this->customer['siret'] = $customer_address['customers_siret'];
        $this->customer['ape'] = $customer_address['customers_ape'];
        $this->customer['tva_intracom'] = $customer_address['customers_tva_intracom'];
      }
    }

    if (is_array($shipping_address)) {
      $this->delivery = [
        'firstname' => $shipping_address['entry_firstname'],
        'lastname' => $shipping_address['entry_lastname'],
        'company' => $shipping_address['entry_company'],
        'street_address' => $shipping_address['entry_street_address'],
        'suburb' => $shipping_address['entry_suburb'],
        'city' => $shipping_address['entry_city'],
        'postcode' => $shipping_address['entry_postcode'],
        'state' => ((!is_null($shipping_address['entry_state'])) ? $shipping_address['entry_state'] : $shipping_address['zone_name']),
        'zone_id' => $shipping_address['entry_zone_id'],
        'country' => array('id' => $shipping_address['countries_id'], 'title' => $shipping_address['countries_name'], 'iso_code_2' => $shipping_address['countries_iso_code_2'], 'iso_code_3' => $shipping_address['countries_iso_code_3']),
        'country_id' => $shipping_address['entry_country_id'],
        'format_id' => $shipping_address['address_format_id']
      ];
    }

    if (is_array($billing_address)) {
      $this->billing = [
        'firstname' => $billing_address['entry_firstname'],
        'lastname' => $billing_address['entry_lastname'],
        'company' => $billing_address['entry_company'],
        'street_address' => $billing_address['entry_street_address'],
        'suburb' => $billing_address['entry_suburb'],
        'city' => $billing_address['entry_city'],
        'postcode' => $billing_address['entry_postcode'],
        'state' => (!is_null($billing_address['entry_state']) ? $billing_address['entry_state'] : $billing_address['zone_name']),
        'zone_id' => $billing_address['entry_zone_id'],
        'country' => array('id' => $billing_address['countries_id'], 'title' => $billing_address['countries_name'], 'iso_code_2' => $billing_address['countries_iso_code_2'], 'iso_code_3' => $billing_address['countries_iso_code_3']),
        'country_id' => $billing_address['entry_country_id'],
        'format_id' => $billing_address['address_format_id']
      ];
    }

    $index = 0;

//**************************************
// coupon
//**************************************
    $this->getCodeCoupon();
    $valid_products_count = 0;

    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
    $CLICSHOPPING_ProductsAttributes = Registry::get('ProductsAttributes');

    $products = $CLICSHOPPING_ShoppingCart->get_products();

    if (is_array($products)) {
      if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
        $QgroupTax = $this->db->prepare('select group_order_taxe,
                                                group_tax
                                         from :table_customers_groups
                                         where customers_group_id = :customers_group_id
                                       ');
        $QgroupTax->bindInt(':customers_group_id', (int)$CLICSHOPPING_Customer->getCustomersGroupID());
        $QgroupTax->execute();

        $group_tax = $QgroupTax->fetch();
      } else {
        $group_tax = false;
      }

      for ($i = 0, $n = count($products); $i < $n; $i++) {
        // Display an indicator to identify if the product belongs at a customer group or not.
        $QproductsQuantityUnitId = $this->db->prepare('select products_quantity_unit_id_group
                                                         from :table_products_groups
                                                         where products_id = :products_id
                                                         and customers_group_id =  :customers_group_id
                                                        ');

        $QproductsQuantityUnitId->bindInt(':products_id', $products[$i]['id']);
        $QproductsQuantityUnitId->bindInt(':customers_group_id', $CLICSHOPPING_Customer->getCustomersGroupID());

        $QproductsQuantityUnitId->execute();

        $products_quantity_unit_id = $QproductsQuantityUnitId->valueInt('products_quantity_unit_id_group');

        if ($products_quantity_unit_id > 0) {
          $model[$i] = HTML::sanitize(defined('CONFIGURATION_PREFIX_MODEL') ? CONFIGURATION_PREFIX_MODEL : '') . $products[$i]['model'];
        } else {
          $model[$i] = $products[$i]['model'];
        }

        $attributes_price = $CLICSHOPPING_ShoppingCart->getAttributesPrice($products[$i]['id']);
        $final_price = $products[$i]['price'] + $attributes_price;

        $this->products[$index] = [
          'qty' => $products[$i]['quantity'],
          'name' => $products[$i]['name'],
          'model' => $model[$i],
          'tax' => $CLICSHOPPING_Tax->getTaxRate($products[$i]['tax_class_id'], $tax_address['entry_country_id'], $tax_address['entry_zone_id']),
          'tax_description' => $CLICSHOPPING_Tax->getTaxRateDescription($products[$i]['tax_class_id'], $tax_address['entry_country_id'], $tax_address['entry_zone_id']),
          'price' => $products[$i]['price'],
          'final_price' => $final_price,
          'weight' => $products[$i]['weight'],
          'id' => $products[$i]['id']
        ];

        // Requetes SQL pour savoir si le groupe B2B a les prix affiches en HT ou TTC
        if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
          $QordersCustomersPrice = $this->db->prepare('select customers_group_price
                                                         from :table_products_groups
                                                         where customers_group_id = :customers_group_id
                                                         and products_id = :products_id
                                                        ');
          $QordersCustomersPrice->bindInt(':customers_group_id', $CLICSHOPPING_Customer->getCustomersGroupID());
          $QordersCustomersPrice->bindInt(':products_id', $products[$i]['id']);
          $QordersCustomersPrice->execute();

          if ($QordersCustomersPrice->fetch()) {
            // Marketing : price is update by discount of the quantity and in function the product
            //Display only in shoppingCart
            $products_price = $QordersCustomersPrice->valueDecimal('customers_group_price');
            $quantity = $products[$i]['quantity'];

            $new_price_with_discount_quantity = $CLICSHOPPING_ProductsCommon->getProductsNewPriceByDiscountByQuantity($products[$i]['id'], $quantity, $products_price);

            if ($new_price_with_discount_quantity > 0) {
              $products_price = $CLICSHOPPING_ProductsCommon->getProductsNewPriceByDiscountByQuantity($_SESSION['ProductsID'], $quantity, $products_price);
              unset($_SESSION['ProductsID']);
            }

            $this->products[$index] = [
              'qty' => $products[$i]['quantity'],
              'name' => $products[$i]['name'],
              'model' => $model[$i],
              'tax' => $CLICSHOPPING_Tax->getTaxRate($products[$i]['tax_class_id'], $tax_address['entry_country_id'], $tax_address['entry_zone_id']),
              'tax_description' => $CLICSHOPPING_Tax->getTaxRateDescription($products[$i]['tax_class_id'], $tax_address['entry_country_id'], $tax_address['entry_zone_id']),
              'price' => $QordersCustomersPrice->valueDecimal('customers_group_price'),
              'final_price' => $QordersCustomersPrice->valueDecimal('customers_group_price') + $CLICSHOPPING_ShoppingCart->getAttributesPrice($products[$i]['id']),
              'weight' => $products[$i]['weight'],
              'id' => $products[$i]['id']
            ];
          }
        }

        if ($products[$i]['attributes']) {
          $subindex = 0;

          foreach ($products[$i]['attributes'] as $option => $value) {

            $Qattributes = $CLICSHOPPING_ProductsAttributes->getProductsAttributesInfo($products[$i]['id'], $option, $this->lang->getId(), $value);

            $this->products[$index]['attributes'][$subindex] = ['option' => $Qattributes->value('products_options_name'),
              'value' => $Qattributes->value('products_options_values_name'),
              'option_id' => $option,
              'value_id' => $value, //products_options_values_id
              'prefix' => $Qattributes->value('price_prefix'),
              'price' => $Qattributes->value('options_values_price'),
              'reference' => $Qattributes->value('products_attributes_reference'),
              'products_attributes_image' => $Qattributes->value('products_attributes_image')
            ];

            $subindex++;
          }
        }

        // discount coupons
        if (is_object($this->coupon)) {
          $discount = $this->coupon->getCalculateDiscount($this->products[$index], $valid_products_count);

          if ($discount['applied_discount'] > 0) {
            $valid_products_count++;
          }

          $shown_price = $this->coupon->getCalculateShownPrice($discount, $this->products[$index]);

          $this->info['subtotal'] += $shown_price['shown_price'];

          $shown_price = $shown_price['actual_shown_price'];
        } else {
          $shown_price = Tax::addTax($this->products[$index]['final_price'], $this->products[$index]['tax']) * $this->products[$index]['qty'];
          $this->info['subtotal'] += $shown_price;
        }

        $products_tax = $this->products[$index]['tax'];
        $products_tax_description = $this->products[$index]['tax_description'];

        if (((\defined('DISPLAY_PRICE_WITH_TAX') && DISPLAY_PRICE_WITH_TAX == 'true') && ($CLICSHOPPING_Customer->getCustomersGroupID() == 0)) || (($CLICSHOPPING_Customer->getCustomersGroupID() != 0) && ($group_tax['group_tax'] == 'true'))) {
          $this->info['tax'] += $shown_price - ($shown_price / (($products_tax < 10) ? "1.0" . str_replace('.', '', $products_tax) : "1." . str_replace('.', '', $products_tax)));

          if (isset($this->info['tax_groups']["$products_tax_description"])) {
            $this->info['tax_groups']["$products_tax_description"] += $shown_price - ($shown_price / (($products_tax < 10) ? "1.0" . str_replace('.', '', $products_tax) : "1." . str_replace('.', '', $products_tax)));

          } else {
            $this->info['tax_groups']["$products_tax_description"] = $shown_price - ($shown_price / (($products_tax < 10) ? "1.0" . str_replace('.', '', $products_tax) : "1." . str_replace('.', '', $products_tax)));
          }
        } else {
          $this->info['tax'] += ($products_tax / 100) * $shown_price;

          if (isset($this->info['tax_groups']["$products_tax_description"])) {
            $this->info['tax_groups']["$products_tax_description"] += ($products_tax / 100) * $shown_price;
          } else {
            $this->info['tax_groups']["$products_tax_description"] = ($products_tax / 100) * $shown_price;
          }
        }

        $index++;
      }
    }

    if (((\defined('DISPLAY_PRICE_WITH_TAX') && DISPLAY_PRICE_WITH_TAX == 'true') && $CLICSHOPPING_Customer->getCustomersGroupID() == 0) ||
      ($CLICSHOPPING_Customer->getCustomersGroupID() != 0 && $group_tax['group_tax'] == 'true') ||
      ($CLICSHOPPING_Customer->getCustomersGroupID() != 0 && $group_tax['group_order_taxe'] == 1)) {
      $this->info['total'] = $this->info['subtotal'] + $this->info['shipping_cost'];
    } else {
      $this->info['total'] = $this->info['subtotal'] + $this->info['tax'] + $this->info['shipping_cost'];
    }

// coupon
    $this->getFinalizeCouponDiscount();
  }

  /***********************************************************
   * Insert
   ***********************************************************/
  /**
   * Inserts a new order and associated details into the database, including customer information,
   * delivery and billing details, payment method, order totals, and product details. This method
   * also processes any applicable attributes and handles specific business-to-business (B2B)
   * information if the customer belongs to a group.
   *
   * The method initializes payment method handling, saves order information, manages order totals,
   * and processes products and their attributes associated with the order. Specific handling for
   * special payment modules (e.g., Atos) and group-specific product models is incorporated.
   *
   * Multiple registry objects are used to fetch order, product, and attribute information, while
   * ensuring compatibility with customer group relationships. Additionally, the method leverages
   * dynamic SQL data operations for saving relevant data into appropriate database tables.
   *
   * @return void
   */
  public function Insert()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_OrderTotal = Registry::get('OrderTotal');

    $paymentModule = $this->paymentResolver()->resolve($_SESSION['payment'] ?? null);
    $this->info = $this->paymentResolver()->applyToInfo($paymentModule, $this->info);

// Manage the atos module and the  Atos situation report in database.
// Do not modify
    if (defined('MODULE_PAYMENT_ATOS_STATUS') && MODULE_PAYMENT_ATOS_STATUS == 'True') {
      $cc_owner = $this->info['transaction_id'];
    } else {
      $cc_owner = $this->info['cc_owner'];
    }

    $firstname = Hash::displayDecryptedDataText($this->customer['firstname']);
    $lastname = Hash::displayDecryptedDataText($this->customer['lastname']);
    $customers_name =  Hash::encryptDatatext($firstname . ' ' . $lastname);

    $delivery_firstname = Hash::displayDecryptedDataText($this->delivery['firstname']);
    $delivery_lastname = Hash::displayDecryptedDataText($this->delivery['lastname']);
    $delivery_name =  Hash::encryptDatatext($delivery_firstname . ' ' . $delivery_lastname);

    $billing_firstname = Hash::displayDecryptedDataText($this->billing['firstname']);
    $billing_lastname = Hash::displayDecryptedDataText($this->billing['lastname']);
    $billing_name =  Hash::encryptDatatext($billing_firstname . ' ' . $billing_lastname);

    $sql_data_array = [
      'customers_id' => (int)$CLICSHOPPING_Customer->getID(),
      'customers_group_id' => (int)$this->customer['customers_group_id'],
      'customers_name' => $customers_name,
      'customers_company' => $this->customer['company'],
      'customers_street_address' => $this->customer['street_address'],
      'customers_suburb' => $this->customer['suburb'],
      'customers_city' => $this->customer['city'],
      'customers_postcode' => $this->customer['postcode'],
      'customers_state' => $this->customer['state'],
      'customers_country' => $this->customer['country']['title'],
      'customers_telephone' => $this->customer['telephone'],
      'customers_email_address' => $this->customer['email_address'],
      'customers_address_format_id' => (int)$this->customer['format_id'],
      'delivery_name' => $delivery_name,
      'delivery_company' => $this->delivery['company'],
      'delivery_street_address' => $this->delivery['street_address'],
      'delivery_suburb' => $this->delivery['suburb'],
      'delivery_city' => $this->delivery['city'],
      'delivery_postcode' => $this->delivery['postcode'],
      'delivery_state' => $this->delivery['state'],
      'delivery_country' => $this->delivery['country']['title'],
      'delivery_address_format_id' => (int)$this->delivery['format_id'],
      'billing_name' => $billing_name,
      'billing_company' => $this->billing['company'],
      'billing_street_address' => $this->billing['street_address'],
      'billing_suburb' => $this->billing['suburb'],
      'billing_city' => $this->billing['city'],
      'billing_postcode' => $this->billing['postcode'],
      'billing_state' => $this->billing['state'],
      'billing_country' => $this->billing['country']['title'],
      'billing_address_format_id' => (int)$this->billing['format_id'],
      'payment_method' => $this->info['payment_method'],
      'cc_type' => $this->info['cc_type'],
      'cc_owner' => $cc_owner,
      'cc_number' => $this->info['cc_number'],
      'cc_expires' => $this->info['cc_expires'],
      'date_purchased' => 'now()',
      'orders_status' => $this->info['order_status'],
      'orders_status_invoice' => $this->info['order_status_invoice'],
      'currency' => $this->info['currency'],
      'currency_value' => $this->info['currency_value'],
      'customers_cellular_phone' => $this->customer['cellular_phone']
    ];

// recuperation des informations societes pour les clients B2B (voir fichier la classe OrderAdmin)
    if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
      $sql_data_array['customers_siret'] = $this->customer['siret'];
      $sql_data_array['customers_ape'] = $this->customer['ape'];
      $sql_data_array['customers_tva_intracom'] = $this->customer['tva_intracom'];
    }

    $writer = $this->orderWriter();

    $this->insertID = $writer->insertOrder($sql_data_array);

    $writer->insertOrderTotals($this->insertID, $CLICSHOPPING_OrderTotal->process());

    $writer->insertOrderProducts($this->insertID, $this->products, (int)$CLICSHOPPING_Customer->getCustomersGroupID(), $this->lang->getId());

    $this->saveGdpr($this->insertID, $CLICSHOPPING_Customer->getID());

    unset($_SESSION['coupon']);

    return $this->insertID;
  }

  /**
   * Retrieves the ID of the most recently inserted order.
   *
   * @return int The ID of the last inserted order.
   */
  public function getLastOrderId()
  {
    return $this->insertID;
  }

  /**
   * Saves GDPR-related information for a customer's order, including IP address
   * and provider name, based on the customer's GDPR preferences.
   *
   * @param int $last_order_id The ID of the last order to update GDPR information for.
   * @param int $customer_id The ID of the customer whose GDPR preferences need to be considered.
   * @return void
   */
  public function saveGdpr(int $last_order_id, int $customer_id): void
  {
    $Qgdpr = $this->db->prepare('select no_ip_address
                                   from :table_customers_gdpr
                                   where customers_id = :customers_id
                                 ');

    $Qgdpr->bindInt(':customers_id', $customer_id);
    $Qgdpr->execute();

    if ($Qgdpr->valueInt('no_ip_address') == 1) {
      $ip_address = '';
      $provider_name = '';
    } else {
      $ip_address = HTTP::getIPAddress();
      $provider_name = HTTP::getProviderNameCustomer();
    }

    $update_array = ['orders_id' => $last_order_id];

    $array = [
      'client_computer_ip' => $ip_address,
      'provider_name_client' => $provider_name,
    ];

    $this->db->save('orders', $array, $update_array);
  }

  /***********************************************************
   * Process
   ***********************************************************/
  /**
   * Processes the finalization of an order, updating stock levels, product order statistics,
   * notifying customers, and performing relevant related actions.
   *
   * @param int $last_order_id The ID of the most recent order to process.
   * @return void
   */
  public function process(int $last_order_id): void
  {
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $CLICSHOPPING_Hooks->call('Orders', 'PreActionProcess', ['order_id' => $last_order_id]);
    $CLICSHOPPING_Hooks->call('Orders', 'PreActionAIProcess', ['order_id' => $last_order_id]);

    $this->stockManager()->applyForOrder($last_order_id, $this->getNotifier());

    $this->adminOrdersStatusHistory($last_order_id);
    $this->sendCustomerEmail($last_order_id);

    $CLICSHOPPING_Hooks->call('Orders', 'Process', ['order_id' => $last_order_id]);
    $CLICSHOPPING_Hooks->call('Orders', 'AIProcess', ['order_id' => $last_order_id]);
  }

  /**
   * Updates the order status history for a given order in the admin panel.
   * This method records the status change, notification preference, and any additional comments.
   *
   * @param int $order_id The ID of the order for which the status history is being updated.
   * @param string $comment Optional additional comments to be recorded in the status history.
   * @return void
   */
  public function adminOrdersStatusHistory(int $order_id, string $comment = ''): void
  {
    $customer_notification = (\defined('SEND_EMAILS') && SEND_EMAILS == 'true') ? '1' : '0';

    $sql_data_array = [
      'orders_id' => (int)$order_id,
      'orders_status_id' => (int)$this->info['order_status'],
      'orders_status_invoice_id' => (int)$this->info['order_status_invoice'],
      'admin_user_name' => '',
      'date_added' => 'now()',
      'customer_notified' => (int)$customer_notification,
      'comments' => $this->info['comments'] . $comment
    ];

    $this->db->save('orders_status_history', $sql_data_array);
  }

  /**
   * Lazily builds and returns the notifier responsible for the order e-mails
   * (customer confirmation + store-owner stock alerts). Kept lazy because the
   * Order constructor already runs cart()/query() and most Order lifecycles
   * never send an e-mail.
   *
   * @return OrderNotifier
   */
  private function getNotifier(): OrderNotifier
  {
    if ($this->notifier === null) {
      $this->notifier = new OrderNotifier($this);
    }

    return $this->notifier;
  }

  /**
   * Lazily builds and returns the stateless payment-module resolver shared by
   * cart() and Insert() to resolve the active payment module from the session
   * and apply its title / forced order status onto the order info.
   *
   * @return PaymentModuleResolver
   */
  private function paymentResolver(): PaymentModuleResolver
  {
    if ($this->paymentResolver === null) {
      $this->paymentResolver = new PaymentModuleResolver();
    }

    return $this->paymentResolver;
  }

  /**
   * Lazily builds and returns the stock manager applying the post-checkout stock
   * side effects (decrement, sold-out disabling, alerts, best-sellers counter).
   *
   * @return OrderStockManager
   */
  private function stockManager(): OrderStockManager
  {
    if ($this->stockManager === null) {
      $this->stockManager = new OrderStockManager();
    }

    return $this->stockManager;
  }

  /**
   * Lazily builds and returns the writer persisting the order rows
   * (orders / orders_total / orders_products + attributes + download).
   *
   * @return OrderWriter
   */
  private function orderWriter(): OrderWriter
  {
    if ($this->orderWriter === null) {
      $this->orderWriter = new OrderWriter();
    }

    return $this->orderWriter;
  }

  /**
   * Lazily builds and returns the cart builder resolving the shipping/billing/tax
   * addresses from the session and address book during cart().
   *
   * @return OrderCartBuilder
   */
  private function cartBuilder(): OrderCartBuilder
  {
    if ($this->cartBuilder === null) {
      $this->cartBuilder = new OrderCartBuilder();
    }

    return $this->cartBuilder;
  }

  /**
   * Sends the customer order-confirmation e-mail.
   *
   * Delegates to {@see OrderNotifier::sendCustomerEmail()}; kept as a public
   * method for backward compatibility with the historical Order API.
   *
   * @param int $order_id The ID of the order to send the email for.
   * @return void
   */
  public function sendCustomerEmail(int $order_id): void
  {
    $this->getNotifier()->sendCustomerEmail($order_id);
  }

  /**
   * Sends the store-owner "product sold out" stock alert.
   *
   * Delegates to {@see OrderNotifier::sendProductsSoldOutAlert()}; kept as a
   * public method for backward compatibility with the historical Order API.
   *
   * @param int $insert_id The ID of the order associated with the product stock updates.
   * @return void
   */
  public function sendEmailAlertProductsSoldOut(int $insert_id): void
  {
    $this->getNotifier()->sendProductsSoldOutAlert($insert_id);
  }

  /**
   * Sends the store-owner "reorder level / low stock" alert.
   *
   * Delegates to {@see OrderNotifier::sendStockWarningAlert()}; kept as a
   * public method for backward compatibility with the historical Order API.
   *
   * @param int $insert_id The ID of the order to evaluate for stock-level alerts.
   * @return void
   */
  public function sendEmailAlertStockWarning(int $insert_id): void
  {
    $this->getNotifier()->sendStockWarningAlert($insert_id);
  }

  /**
   * Validates and processes the discount coupon code from the session,
   * ensuring the coupon is applicable to the products in the shopping cart.
   *
   * @return false|void Returns false if the discount coupon module is disabled or not active.
   */
  private function getCodeCoupon()
  {
    if (!defined('CLICSHOPPING_APP_DISCOUNT_COUPON_DC_STATUS') || CLICSHOPPING_APP_DISCOUNT_COUPON_DC_STATUS == 'False') {
      return false;
    }

    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');

    $products = $CLICSHOPPING_ShoppingCart->get_products();

    if (isset($_SESSION['coupon']) && !empty($_SESSION['coupon'])) {
      $code_coupon = HTML::sanitize($_SESSION['coupon']);

      if (!Registry::exists('DiscountCouponCustomer')) {
        Registry::set('DiscountCouponCustomer', new DiscountCouponCustomer($code_coupon));
        $this->coupon = Registry::get('DiscountCouponCustomer');
      }

      $this->coupon->getTotalValidProducts($products);
    }
  }

  /**
   * Applies and finalizes the discount from a coupon to the current order total,
   * if the Discount Coupon module is active and the coupon object is available.
   *
   * @return mixed Returns the updated total with the coupon discount applied if applicable,
   *               or false if the Discount Coupon module is inactive.
   */
  private function getFinalizeCouponDiscount()
  {
    if (!defined('CLICSHOPPING_APP_DISCOUNT_COUPON_DC_STATUS') || CLICSHOPPING_APP_DISCOUNT_COUPON_DC_STATUS == 'False') {
      return false;
    }

    if (is_object($this->coupon)) {
      $this->info['total'] = $this->coupon->getFinalizeDiscount($this->info);

      return $this->info['total'];
    }
  }

  /**
   * Checks whether the customer has previously purchased a specific product.
   *
   * @return bool Returns true if the customer has purchased the product, false otherwise.
   */
  public function hasPurchasedProduct()
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');

    if ($CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
      $Qhaspurchased = $CLICSHOPPING_Db->prepare('select count(*) as total
                                                    from :table_orders o,
                                                         :table_orders_products op,
                                                         :table_products p
                                                    where o.customers_id = :customers_id
                                                    and o.orders_id = op.orders_id
                                                    and op.products_id = p.products_id
                                                    and op.products_id = :products_id
                                                    and o.customers_group_id = 0
                                                    ');
      $Qhaspurchased->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
      $Qhaspurchased->bindInt(':products_id', $CLICSHOPPING_ProductsCommon->getID());
      $Qhaspurchased->execute();

    } else {
      $Qhaspurchased = $CLICSHOPPING_Db->prepare('select count(*) as total
                                                    from :table_orders o,
                                                         :table_orders_products op,
                                                         :table_products p
                                                    where o.customers_id = :customers_id
                                                    and o.orders_id = op.orders_id
                                                    and op.products_id = p.products_id
                                                    and op.products_id = :products_id
                                                    and o.customers_group_id > 0
                                                    ');
      $Qhaspurchased->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
      $Qhaspurchased->bindInt(':products_id', $CLICSHOPPING_ProductsCommon->getID());
      $Qhaspurchased->execute();
    }

    return ($Qhaspurchased->fetch() !== false);
  }
}
