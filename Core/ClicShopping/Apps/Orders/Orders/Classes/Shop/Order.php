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
  protected int $_id;
  protected int $insertID;
  public $coupon;
  public $content_type;

  private mixed $db;
  private mixed $lang;
  protected $mail;

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
      $this->_id = (int)$_GET['order_id'];
      $this->query($this->_id);
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

    if (is_array($_SESSION['sendto']) && !empty($_SESSION['sendto'])) {
      $shipping_address = [
        'entry_firstname' => $_SESSION['sendto']['firstname'],
        'entry_lastname' => $_SESSION['sendto']['lastname'],
        'entry_company' => $_SESSION['sendto']['company'],
        'entry_street_address' => $_SESSION['sendto']['street_address'],
        'entry_suburb' => $_SESSION['sendto']['suburb'],
        'entry_postcode' => $_SESSION['sendto']['postcode'],
        'entry_city' => $_SESSION['sendto']['city'],
        'entry_zone_id' => $_SESSION['sendto']['zone_id'],
        'zone_name' => $_SESSION['sendto']['zone_name'],
        'entry_country_id' => $_SESSION['sendto']['country_id'],
        'countries_id' => $_SESSION['sendto']['country_id'],
        'countries_name' => $_SESSION['sendto']['country_name'],
        'countries_iso_code_2' => $_SESSION['sendto']['country_iso_code_2'],
        'countries_iso_code_3' => $_SESSION['sendto']['country_iso_code_3'],
        'address_format_id' => $_SESSION['sendto']['address_format_id'],
        'entry_state' => $_SESSION['sendto']['zone_name']
      ];
    } elseif (is_numeric($_SESSION['sendto'])) {
      $Qaddress = $this->db->prepare('select ab.entry_firstname,
                                               ab.entry_lastname,
                                               ab.entry_company,
                                               ab.entry_street_address,
                                               ab.entry_suburb,
                                               ab.entry_postcode,
                                               ab.entry_city,
                                               ab.entry_zone_id,
                                               ab.entry_country_id,
                                               ab.entry_state,      
                                               z.zone_name,
                                               c.countries_id,
                                               c.countries_name,
                                               c.countries_iso_code_2,
                                               c.countries_iso_code_3,
                                               c.address_format_id
                                       from :table_address_book ab left join :table_zones z on (ab.entry_zone_id = z.zone_id)
                                                                   left join :table_countries c on (ab.entry_country_id = c.countries_id)
                                       where ab.customers_id = :customers_id
                                       and ab.address_book_id = :address_book_id
                                    ');
      $Qaddress->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
      $Qaddress->bindInt(':address_book_id', (int)$_SESSION['sendto']);
      $Qaddress->execute();

      $shipping_address = $Qaddress->toArray();
    } else {
      $shipping_address = [
        'entry_firstname' => null,
        'entry_lastname' => null,
        'entry_company' => null,
        'entry_street_address' => null,
        'entry_suburb' => null,
        'entry_postcode' => null,
        'entry_city' => null,
        'entry_zone_id' => null,
        'zone_name' => null,
        'entry_country_id' => null,
        'countries_id' => null,
        'countries_name' => null,
        'countries_iso_code_2' => null,
        'countries_iso_code_3' => null,
        'address_format_id' => 0,
        'entry_state' => null
      ];
    }

    if (isset($_SESSION['billto']) && is_array($_SESSION['billto']) && !empty($_SESSION['billto'])) {
      $billing_address = [
        'entry_firstname' => $_SESSION['billto']['firstname'],
        'entry_lastname' => $_SESSION['billto']['lastname'],
        'entry_company' => $_SESSION['billto']['company'],
        'entry_street_address' => $_SESSION['billto']['street_address'],
        'entry_suburb' => $_SESSION['billto']['suburb'],
        'entry_postcode' => $_SESSION['billto']['postcode'],
        'entry_city' => $_SESSION['billto']['city'],
        'entry_zone_id' => $_SESSION['billto']['zone_id'],
        'zone_name' => $_SESSION['billto']['zone_name'],
        'entry_country_id' => $_SESSION['billto']['country_id'],
        'countries_id' => $_SESSION['billto']['country_id'],
        'countries_name' => $_SESSION['billto']['country_name'],
        'countries_iso_code_2' => $_SESSION['billto']['country_iso_code_2'],
        'countries_iso_code_3' => $_SESSION['billto']['country_iso_code_3'],
        'address_format_id' => $_SESSION['billto']['address_format_id'],
        'entry_state' => $_SESSION['billto']['zone_name']
      ];
    } else {
      $Qaddress = $this->db->prepare('select ab.entry_firstname,
                                                ab.entry_lastname,
                                                ab.entry_company,
                                                ab.entry_street_address,
                                                ab.entry_suburb,
                                                ab.entry_postcode,
                                                ab.entry_city,
                                                ab.entry_zone_id,
                                                z.zone_name,
                                                ab.entry_country_id,
                                                c.countries_id,
                                                c.countries_name,
                                                c.countries_iso_code_2,
                                                c.countries_iso_code_3,
                                                c.address_format_id,
                                                ab.entry_state
                                         from :table_address_book ab left join :table_zones z on (ab.entry_zone_id = z.zone_id)
                                                                     left join :table_countries c on (ab.entry_country_id = c.countries_id)
                                         where ab.customers_id = :customers_id
                                         and ab.address_book_id = :address_book_id
                                        ');
      $Qaddress->bindInt(':customers_id', $CLICSHOPPING_Customer->getID());
      $Qaddress->bindInt(':address_book_id', $CLICSHOPPING_Customer->getDefaultAddressID());
      $Qaddress->execute();

      $billing_address = $Qaddress->toArray();
    }

    if ($this->content_type == 'virtual') {
      $tax_address = [
        'entry_country_id' => $billing_address['entry_country_id'],
        'entry_zone_id' => $billing_address['entry_zone_id']
      ];
    } else {
      $tax_address = [
        'entry_country_id' => $shipping_address['entry_country_id'],
        'entry_zone_id' => $shipping_address['entry_zone_id']
      ];
    }

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

    if (isset($_SESSION['payment'])) {
      if (str_contains($_SESSION['payment'], '\\')) {
        $code = 'Payment_' . str_replace('\\', '_', $_SESSION['payment']);

        if (Registry::exists($code)) {
          $CLICSHOPPING_PM = Registry::get($code);
        }
      }

      if (isset($CLICSHOPPING_PM)) {
        if (isset($CLICSHOPPING_PM->public_title)) {
          $this->info['payment_method'] = $CLICSHOPPING_PM->public_title;
        } else {
          $this->info['payment_method'] = $CLICSHOPPING_PM->title;
        }

        if (isset($CLICSHOPPING_PM->order_status) && is_numeric($CLICSHOPPING_PM->order_status) && ($CLICSHOPPING_PM->order_status > 0)) {
          $this->info['order_status'] = $CLICSHOPPING_PM->order_status;
        }
      }
    }

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
    $CLICSHOPPING_Prod = Registry::get('Prod');
    $CLICSHOPPING_ProductsAttributes = Registry::get('ProductsAttributes');
    $CLICSHOPPING_OrderTotal = Registry::get('OrderTotal');

    if (isset($_SESSION['payment'])) {
      if (str_contains($_SESSION['payment'], '\\')) {
        $code = 'Payment_' . str_replace('\\', '_', $_SESSION['payment']);

        if (Registry::exists($code)) {
          $CLICSHOPPING_PM = Registry::get($code);
        }
      }

      if (isset($CLICSHOPPING_PM)) {
        if (isset($CLICSHOPPING_PM->public_title)) {
          $this->info['payment_method'] = $CLICSHOPPING_PM->public_title;
        } else {
          $this->info['payment_method'] = $CLICSHOPPING_PM->title;
        }

        if (isset($CLICSHOPPING_PM->order_status) && is_numeric($CLICSHOPPING_PM->order_status) && ($CLICSHOPPING_PM->order_status > 0)) {
          $this->info['order_status'] = $CLICSHOPPING_PM->order_status;
        }
      }
    }

// Manage the atos module and the  Atos situation report in database.
// Do not modify
    if (defined('MODULE_PAYMENT_ATOS_STATUS') && MODULE_PAYMENT_ATOS_STATUS == 'True') {
      $cc_owner = $this->info['transaction_id'];
    } else {
      $cc_owner = $this->info['cc_owner'];
    }

    $firstname = Hash::displayDecryptedDataText($this->customer['firstname']);
    $lastame = Hash::displayDecryptedDataText($this->customer['lastname']);
    $customers_name =  Hash::encryptDatatext($firstname . ' ' . $lastame);

    $delivery_firstname = Hash::displayDecryptedDataText($this->delivery['firstname']);
    $delivery_lastame = Hash::displayDecryptedDataText($this->delivery['lastname']);
    $delivery_name =  Hash::encryptDatatext($delivery_firstname . ' ' . $delivery_lastame);

    $billing_firstname = Hash::displayDecryptedDataText($this->billing['firstname']);
    $billing_lastame = Hash::displayDecryptedDataText($this->billing['lastname']);
    $billing_name =  Hash::encryptDatatext($billing_firstname . ' ' . $billing_lastame);

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

    $this->db->save('orders', $sql_data_array);

    $this->insertID = $this->db->lastInsertId();

// orders total
    $order_totals = $CLICSHOPPING_OrderTotal->process();

    for ($i = 0, $n = count($order_totals); $i < $n; $i++) {
      $sql_data_array = [
        'orders_id' => (int)$this->insertID,
        'title' => $order_totals[$i]['title'],
        'text' => $order_totals[$i]['text'],
        'value' => (float)$order_totals[$i]['value'],
        'class' => $order_totals[$i]['code'],
        'sort_order' => (int)$order_totals[$i]['sort_order']
      ];

      $this->db->save('orders_total', $sql_data_array);
    }

// initialized for the email confirmation
    for ($i = 0, $n = count($this->products); $i < $n; $i++) {
// search the good model
      if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
        $QproductsModuleCustomersGroup = $this->db->prepare('select products_model_group
                                                               from :table_products_groups
                                                               where products_id = :products_id
                                                               and customers_group_id = :customers_group_id
                                                              ');
        $QproductsModuleCustomersGroup->bindInt(':products_id', $CLICSHOPPING_Prod::getProductID($this->products[$i]['id']));
        $QproductsModuleCustomersGroup->bindInt(':customers_group_id', $CLICSHOPPING_Customer->getCustomersGroupID());
        $QproductsModuleCustomersGroup->execute();

        $products_model = $QproductsModuleCustomersGroup->value('products_model_group');

        if (empty($products_model)) {
          $products_model = $this->products[$i]['model'];
        } else {
          $products_model = 'no model';
        }
      } else {
        $products_model = $this->products[$i]['model'];
      }

// save data
      $sql_data_array = [
        'orders_id' => (int)$this->insertID,
        'products_id' => (int)$CLICSHOPPING_Prod::getProductID($this->products[$i]['id']),
        'products_model' => $products_model,
        'products_name' => $this->products[$i]['name'],
        'products_price' => (float)$this->products[$i]['price'],
        'final_price' => (float)$this->products[$i]['final_price'],
        'products_tax' => (float)$this->products[$i]['tax'],
        'products_quantity' => (int)$this->products[$i]['qty']
      ];

      $this->db->save('orders_products', $sql_data_array);

      $order_products_id = $this->db->lastInsertId();

      if (isset($this->products[$i]['attributes'])) {
        for ($j = 0, $n2 = count($this->products[$i]['attributes']); $j < $n2; $j++) {
          $Qattributes = $CLICSHOPPING_ProductsAttributes->getAttributesDownloaded($this->products[$i]['id'], $this->products[$i]['attributes'][$j]['option_id'], $this->products[$i]['attributes'][$j]['value_id'], $this->lang->getId());

          $sql_data_array = [
            'orders_id' => (int)$this->insertID,
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
              'orders_id' => (int)$this->insertID,
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
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Prod = Registry::get('Prod');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $CLICSHOPPING_Hooks->call('Orders', 'PreActionProcess', ['order_id' => $last_order_id]);
    $CLICSHOPPING_Hooks->call('Orders', 'PreActionAIProcess', ['order_id' => $last_order_id]);
    
    $Qproducts = $this->db->prepare('select products_id,
                                            products_quantity
                                      from :table_orders_products
                                      where orders_id = :orders_id
                                     ');
    $Qproducts->bindInt(':orders_id', $last_order_id);
    $Qproducts->execute();

    while ($Qproducts->fetch()) {
// Stock Update
      if (\defined('STOCK_LIMITED') && STOCK_LIMITED == 'true') {
        if (\defined('DOWNLOAD_ENABLED') && DOWNLOAD_ENABLED == 'true') {
          $stock_query_sql = 'select p.products_quantity,
                                      pad.products_attributes_filename
                                from :table_products p
                                left join :table_products_attributes pa  on p.products_id = pa.products_id
                                left join :table_products_attributes_download pad on pa.products_attributes_id = pad.products_attributes_id
                                where p.products_id = :products_id';

          $products_attributes = $this->products['attributes'] ?? '';

          if (is_array($products_attributes)) {
            $stock_query_sql .= ' and pa.options_id = :options_id
                                   and pa.options_values_id = :options_values_id
                                ';
          }

          $Qstock = $this->db->prepare($stock_query_sql);

          $Qstock->bindInt(':products_id', $CLICSHOPPING_Prod::getProductID($Qproducts->valueInt('products_id')));

          if (is_array($products_attributes)) {
            $Qstock->bindInt(':options_id', $products_attributes['option_id']);
            $Qstock->bindInt(':options_values_id', $products_attributes['value_id']);
          }

          $Qstock->execute();
        } else {
          $Qstock = $this->db->prepare('select products_quantity,
                                                products_quantity_alert
                                        from :table_products
                                        where products_id = :products_id
                                        ');

          $Qstock->bindInt(':products_id', $CLICSHOPPING_Prod::getProductID($Qproducts->valueInt('products_id')));
          $Qstock->execute();
        }

        if ($Qstock->fetch() !== false) {
// do not decrement quantities if products_attributes_filename exists
          if ((\defined('DOWNLOAD_ENABLED') && DOWNLOAD_ENABLED != 'true') || !is_null($Qstock->value('products_attributes_filename'))) {
// select the good qty in B2B ti decrease the stock. See shopping_cart top display out stock or not
            if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
              $QproductsQuantityCustomersGroup = $this->db->prepare('select products_quantity_fixed_group
                                                                        from :table_products_groups
                                                                        where products_id = :products_id
                                                                        and customers_group_id =  :customers_group_id
                                                                       ');
              $QproductsQuantityCustomersGroup->bindInt(':products_id', $CLICSHOPPING_Prod::getProductID($Qproducts->valueInt('products_id')));
              $QproductsQuantityCustomersGroup->bindInt(':customers_group_id', (int)$CLICSHOPPING_Customer->getCustomersGroupID());
              $QproductsQuantityCustomersGroup->execute();

              $products_quantity_customers_group = $QproductsQuantityCustomersGroup->fetch();

// do the exact qty in function the customer group and product
              $products_quantity_customers_group = $products_quantity_customers_group['products_quantity_fixed_group'];
            } else {
              $products_quantity_customers_group = 1;
            }

            if (\defined('STOCK_ALLOW_CHECKOUT') && STOCK_ALLOW_CHECKOUT == 'false') {
              $stock_left = $Qstock->valueInt('products_quantity') - ($Qproducts->valueInt('products_quantity') * $products_quantity_customers_group);
            } else {
              $stock_left = $Qstock->valueInt('products_quantity');
            }
          } else {
            $stock_left = $Qstock->valueInt('products_quantity');
          }

          if ($stock_left != $Qstock->valueInt('products_quantity')) {
            $this->db->save('products', ['products_quantity' => $stock_left], ['products_id' => $CLICSHOPPING_Prod::getProductID($Qproducts->valueInt('products_id'))]);
          }

          if (($stock_left < 1) && (\defined('STOCK_ALLOW_CHECKOUT') && STOCK_ALLOW_CHECKOUT == 'false')) {
            $this->db->save('products', ['products_status' => 0], ['products_id' => $CLICSHOPPING_Prod::getProductID($Qproducts->valueInt('products_id'))]);
          }

// alert an email if the product stock is < stock reorder level
// Alert by mail if a product is 0 or < 0
          $this->sendEmailAlertStockWarning($last_order_id);
// Email alert when a product is exhausted
          $this->sendEmailAlertProductsSoldOut($last_order_id);
        }
      }

// Update products_ordered (for bestsellers list)
      $Qupdate = $this->db->prepare('update :table_products
                                       set products_ordered = products_ordered + :products_ordered
                                       where products_id = :products_id');
      $Qupdate->bindInt(':products_ordered', $Qproducts->valueInt('products_quantity'));
      $Qupdate->bindInt(':products_id', $Qproducts->valueInt('products_id'));
      $Qupdate->execute();
    } // end while

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
   * Sends an email to the customer containing order details, including the order summary,
   * product information, delivery and billing addresses, payment method details,
   * and any additional comments or footer text. Optionally, extra order-related emails
   * can also be sent to specified recipients.
   *
   * @param int $order_id The ID of the order to send the email for.
   * @return void
   */
  public function sendCustomerEmail(int $order_id): void
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Currencies = Registry::get('Currencies');

    if (str_contains($_SESSION['payment'], '\\')) {
      $code = 'Payment_' . str_replace('\\', '_', $_SESSION['payment']);

      if (Registry::exists($code)) {
        $CLICSHOPPING_PM = Registry::get($code);
      }
    }

    $Qorder = $this->db->prepare('select *
                                     from :table_orders
                                     where orders_id = :orders_id
                                     limit 1
                                     ');
    $Qorder->bindInt(':orders_id', $order_id);
    $Qorder->execute();

    if ($Qorder->fetch() !== false) {
      $Qproducts = $this->db->prepare('select orders_products_id,
                                                 products_id,
                                                 products_model,
                                                 products_name,
                                                 products_price,
                                                 products_tax,
                                                 products_quantity
                                         from :table_orders_products
                                         where orders_id = :orders_id
                                         order by orders_products_id
                                         ');
      $Qproducts->bindInt(':orders_id', $order_id);
      $Qproducts->execute();

      $message_order = stripslashes(CLICSHOPPING::getDef('entry_text_order_number')) . ' ' . $order_id . "\n" . stripslashes(CLICSHOPPING::getDef('email_text_invoice_url'));

      $email_order = $message_order . ' ' . CLICSHOPPING::link(null, 'Account&HistoryInfo&order_id=' . $order_id) . "\n" . CLICSHOPPING::getDef('email_text_date_ordered') . ' ' . DateTime::strftime(CLICSHOPPING::getDef('date_format_long')) . "\n\n";

      if ($this->info['comments']) {
        $email_order .= HTML::outputProtected($this->info['comments']) . "\n\n";
      }

      $message_order = stripslashes(CLICSHOPPING::getDef('email_text_products'));

      $email_order .= html_entity_decode($message_order) . "\n" . CLICSHOPPING::getDef('email_separator') . "\n";

      while ($Qproducts->fetch()) {
        if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
          $QproductsModuleCustomersGroup = $this->db->prepare('select products_model_group
                                                                  from :table_products_groups
                                                                  where products_id = :products_id
                                                                  and customers_group_id =  :customers_group_id
                                                                ');

          $QproductsModuleCustomersGroup->bindInt(':products_id', $Qproducts->valueInt('products_id'));
          $QproductsModuleCustomersGroup->bindInt(':customers_group_id', $CLICSHOPPING_Customer->getCustomersGroupID());
          $QproductsModuleCustomersGroup->execute();

          $products_model = $QproductsModuleCustomersGroup->value('products_model_group');

          if (empty($products_model)) {
            $products_model = $Qproducts->value('products_model');
          }

        } else {
          $products_model = $Qproducts->value('products_model');
        }

        $email_order .= $Qproducts->valueInt('products_quantity') . ' x ' . $Qproducts->value('products_name') . ' (' . $products_model . ') = ' . html_entity_decode($CLICSHOPPING_Currencies->displayPrice($Qproducts->value('products_price'), $Qproducts->value('products_tax'), $Qproducts->valueInt('products_quantity'))) . "\n";
      }

      $email_order .= CLICSHOPPING::getDef('email_separator') . "\n";

      $Qtotals = $this->db->prepare('select title,
                                               text
                                       from :table_orders_total
                                       where orders_id = :orders_id
                                       order by sort_order
                                       ');
      $Qtotals->bindInt(':orders_id', $order_id);
      $Qtotals->execute();

      while ($Qtotals->fetch()) {
        $email_order .= strip_tags($Qtotals->value('title') . ' ' . $Qtotals->value('text'));
        $email_order = str_replace('&nbsp;', ' ', $email_order) . "\n";
      }

      if ($this->content_type != 'virtual') {
        $message_order = stripslashes(CLICSHOPPING::getDef('email_text_delivery_address'));
        $email_order .= "\n" . $message_order . "\n" . CLICSHOPPING::getDef('email_separator') . "\n" . AddressBook::addressLabel($CLICSHOPPING_Customer->getID(), $_SESSION['sendto'], 0, '', "\n") . "\n";
      }

      $message_order = stripslashes(CLICSHOPPING::getDef('email_text_billing_address'));
      $email_order .= "\n" . $message_order . "\n" . CLICSHOPPING::getDef('email_separator') . "\n" . AddressBook::addressLabel($CLICSHOPPING_Customer->getID(), $_SESSION['billto'], 0, '', "\n") . "\n\n";

      if (isset($CLICSHOPPING_PM)) {
        $message_order = stripslashes(CLICSHOPPING::getDef('email_text_payment_method'));
        $email_order .= $message_order . "\n" . CLICSHOPPING::getDef('email_separator') . "\n";

        $email_order .= $this->info['payment_method'] . "\n\n";

        if (isset($CLICSHOPPING_PM->email_footer)) {
          $email_order .= $CLICSHOPPING_PM->email_footer . "\n\n";
        }
      }

      if (isset($_SESSION['payment'])) {
        if (str_contains($_SESSION['payment'], '\\')) {
          $code = 'Payment_' . str_replace('\\', '_', $_SESSION['payment']);

          if (Registry::exists($code)) {
            $CLICSHOPPING_PM = Registry::get($code);
          }
        }

        if (isset($CLICSHOPPING_PM)) {
          $message_order = stripslashes(CLICSHOPPING::getDef('email_text_payment_method'));
          $email_order .= $message_order . "\n" . CLICSHOPPING::getDef('email_separator') . "\n";

          $payment_class = $CLICSHOPPING_PM;
          $email_order .= $this->info['payment_method'] . "\n\n";

          if (isset($payment_class->email_footer)) {
            $email_order .= $payment_class->email_footer . "\n";
          }
        }
      } // end $GLOBALS[$_SESSION['payment']]

      $email_order .= TemplateEmail::getTemplateEmailSignature() . "\n\n";
      $email_order .= TemplateEmail::getTemplateEmailTextFooter() . "\n";

      $to_email_address = Hash::displayDecryptedDataText($this->customer['email_address']);
      $to_name = Hash::displayDecryptedDataText($this->customer['firstname']) . ' ' . Hash::displayDecryptedDataText($this->customer['lastname']);
      $email_subject = CLICSHOPPING::getDef('email_text_subject', ['store_name' => STORE_NAME]);

      $to_addr = $to_email_address;
      $from_name = \defined('STORE_NAME') ? STORE_NAME : '';
      $from_addr = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
      $to_name = $to_name;
      $subject = $email_subject;

      $this->mail->addHtml($email_order);
      $this->mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);

// SEND_EXTRA_ORDER_EMAILS_TO does'nt work like this, test<test@test.com>, just with test@test.com
      if (!empty(\defined('SEND_EXTRA_ORDER_EMAILS_TO') ? SEND_EXTRA_ORDER_EMAILS_TO : '')) {
        $email_text_subject = stripslashes(CLICSHOPPING::getDef('email_text_subject', ['store_name' => STORE_NAME]));
        $email_text_subject = html_entity_decode($email_text_subject);

        if (!empty(SEND_EXTRA_ORDER_EMAILS_TO)) {
          $email[] = TemplateEmail::getExtractEmailAddress(SEND_EXTRA_ORDER_EMAILS_TO);

          if (is_array($email)) {
            foreach ($email as $key => $value) {
              $to_addr = $value[$key];
              $from_name = \defined('STORE_NAME') ? STORE_NAME : '';
              $from_addr = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
              $to_name = null;
              $subject = $email_text_subject;

              $this->mail->addHtml($email_order);
              $this->mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);
            }
          } else {
            $to_addr = $email;
            $from_name = \defined('STORE_NAME') ? STORE_NAME : '';
            $from_addr = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
            $to_name = null;
            $subject = $email_text_subject;

            $this->mail->addHtml($email_order);
            $this->mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);
          }
        }
      }
    }
  }

  /**
   * Sends an email alert to notify store administrators when products are sold out.
   * The alert is triggered based on stock levels and configurable stock checking settings.
   *
   * @param int $insert_id The ID of the order associated with the product stock updates.
   * @return void
   */
  public function sendEmailAlertProductsSoldOut(int $insert_id): void
  {
    $CLICSHOPPING_Prod = Registry::get('Prod');

    if (\defined('STOCK_ALERT_PRODUCT_SOLD_OUT') && STOCK_ALERT_PRODUCT_SOLD_OUT == 'true') {
      $Qproducts = $this->db->prepare('select orders_products_id,
                                                 products_id
                                                 products_model,
                                                 products_name,
                                                 products_quantity
                                         from :table_orders_products
                                         where orders_id = :orders_id
                                         order by orders_products_id
                                         ');
      $Qproducts->bindInt(':orders_id', $insert_id);
      $Qproducts->execute();

      if ($Qproducts->fetch() !== false) {
        while ($Qproducts->fetch()) {
          $Qstock = $this->db->prepare('select products_quantity_alert,
                                                  products_quantity
                                            from :table_products
                                            where products_id = :products_id
                                          ');

          $Qstock->bindInt(':products_id', $Qproducts->valueInt('products_id'));
          $Qstock->execute();

          $stock_left = $Qstock->valueInt('products_quantity');

          if (($stock_left < 1) && (\defined('STOCK_ALLOW_CHECKOUT') && STOCK_ALLOW_CHECKOUT == 'false') && (\defined('STOCK_CHECK') && STOCK_CHECK == 'true')) {
            $email_text_subject_stock = stripslashes(CLICSHOPPING::getDef('email_text_subject_stock', ['store_name' => defined('STORE_NAME') ? STORE_NAME : '']));
            $email_product_sold_out_stock = stripslashes(CLICSHOPPING::getDef('email_text_stock'));
            $email_product_sold_out_stock .= "\n" . CLICSHOPPING::getDef('email_text_date_alert') . ' ' . date(CLICSHOPPING::getDef('date_format_long')) . "\n" .
              CLICSHOPPING::getDef('email_text_model') . '  ' . $Qproducts->value('products_model') . "\n" .
              CLICSHOPPING::getDef('email_text_products_name') . ' ' . $Qproducts->value('products_name') . "\n" .
              CLICSHOPPING::getDef('email_text_id_product') . ' ' . $CLICSHOPPING_Prod::getProductID($Qproducts->value('products_id')) . "\n";

            $to_addr = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
            $from_name = \defined('STORE_NAME') ? STORE_NAME : '';
            $from_addr = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
            $to_name = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
            $subject = $email_text_subject_stock;

            $this->mail->addHtml($email_product_sold_out_stock);
            $this->mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);
          }
        } // end stock alert
      }  // end while
    }
  }

  /**
   * Sends an email alert when the stock quantity of a product reaches a specified reorder level
   * or falls below a defined threshold. It checks product stock levels and generates alerts
   * to notify the store owner about low stock or critical stock conditions.
   *
   * @param int $insert_id The ID of the order to evaluate for stock-level alerts.
   * @return void
   */
  public function sendEmailAlertStockWarning(int $insert_id): void
  {
    $CLICSHOPPING_Prod = Registry::get('Prod');

    if (\defined('STOCK_ALERT_PRODUCT_REORDER_LEVEL') && STOCK_ALERT_PRODUCT_REORDER_LEVEL == 'true') {
      if ((\defined('STOCK_ALLOW_CHECKOUT') && STOCK_ALLOW_CHECKOUT == 'false') && (\defined('STOCK_CHECK') && STOCK_CHECK == 'true')) {
        $Qproducts = $this->db->prepare('select orders_products_id,
                                                   products_id
                                                   products_model,
                                                   products_name,
                                                   products_quantity
                                           from :table_orders_products
                                           where orders_id = :orders_id
                                           order by orders_products_id
                                           ');
        $Qproducts->bindInt(':orders_id', $insert_id);
        $Qproducts->execute();

        if ($Qproducts->fetch() !== false) {
          while ($Qproducts->fetch()) {
            $Qstock = $this->db->prepare('select products_quantity_alert,
                                                    products_quantity
                                            from :table_products
                                            where products_id = :products_id
                                          ');

            $Qstock->bindInt(':products_id', $CLICSHOPPING_Prod::getProductID($Qproducts->valueInt('products_id')));
            $Qstock->execute();

            $stock_products_quantity_alert = $Qstock->valueInt('products_quantity_alert');

            $warning_stock = (int)STOCK_REORDER_LEVEL;
            $current_stock = $Qstock->valueInt('products_quantity');

// alert email if stock product alert < warning stock
            if (($stock_products_quantity_alert <= $warning_stock) && ($stock_products_quantity_alert != '0')) {
              $email_text_subject_stock = stripslashes(CLICSHOPPING::getDef('email_text_suject_stock', ['store_name' => STORE_NAME]));

              $reorder_stock_email = stripslashes(CLICSHOPPING::getDef('email_reorder_level_text_alert_stock'));
              $reorder_stock_email .= "\n" . CLICSHOPPING::getDef('email_text_date_alert') . ' ' . date(CLICSHOPPING::getDef('date_format_long')) . "\n" .
                CLICSHOPPING::getDef('email_text_model') . ' ' . $Qproducts->value('products_model') . "\n" .
                CLICSHOPPING::getDef('email_text_products_name') . ' ' . $Qproducts->value('products_name') . "\n" .
                CLICSHOPPING::getDef('email_text_id_product') . ' ' . $CLICSHOPPING_Prod::getProductID($Qproducts->value('products_id')) . "\n" .
                '<strong>' . CLICSHOPPING::getDef('email_text_product_url') . ' </strong>' . HTTP::getShopUrlDomain() . 'index.php?Products&Description&products_id=' . $Qproducts->value('products_id') . "\n" .
                '<strong>' . CLICSHOPPING::getDef('email_text_product_stock') . ' ' . $stock_products_quantity_alert . '</strong>';

              $to_addr = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
              $from_name = \defined('STORE_OWNER') ? STORE_OWNER : '';
              $from_addr = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
              $to_name = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
              $subject = $email_text_subject_stock;

              $this->mail->addHtml($reorder_stock_email);
              $this->mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);
            }

            if ($current_stock <= $warning_stock) {
              $email_text_subject_stock = stripslashes(CLICSHOPPING::getDef('email_text_suject_stock', ['store_name' => STORE_NAME]));

              $reorder_stock_email = stripslashes(CLICSHOPPING::getDef('email_reorder_level_text_stock'));
              $reorder_stock_email .= "\n" . CLICSHOPPING::getDef('email_text_date_alert') . ' ' . date(CLICSHOPPING::getDef('date_format_long')) . "\n" .
                CLICSHOPPING::getDef('email_text_model') . ' ' . $Qproducts->value('products_model') . "\n" .
                CLICSHOPPING::getDef('email_text_products_name') . ' ' . $Qproducts->value('products_name') . "\n" .
                CLICSHOPPING::getDef('email_text_id_product') . ' ' . $CLICSHOPPING_Prod::getProductID($Qproducts->value('products_id')) . "\n" .
                '<strong>' . CLICSHOPPING::getDef('email_text_product_url') . ' </strong>' . HTTP::getShopUrlDomain() . 'index.php?Products&Description&products_id=' . $Qproducts->value('products_id') . "\n" .
                '<strong>' . CLICSHOPPING::getDef('email_text_product_stock') . ' ' . $stock_products_quantity_alert . '</strong>';

              $to_addr = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
              $from_name = \defined('STORE_OWNER') ? STORE_OWNER : '';
              $from_addr = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
              $to_name = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
              $subject = $email_text_subject_stock;

              $this->mail->addHtml($reorder_stock_email);
              $this->mail->send($to_addr, $from_name, $from_addr, $to_name, $subject);
            }
          }
        }
      }
    }
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
