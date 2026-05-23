<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Sites\ClicShoppingAdmin\Pages\Home\Actions\Orders;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class UpdateCustomerAddress extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;
  private mixed $lang;
  private mixed $db;

  public function __construct()
  {
    $this->app = Registry::get('Orders');
    $this->lang = Registry::get('Language');
    $this->db = Registry::get('Db');
  }

  /**
   *
   */
  private function saveCustomerAddress(): void
  {
    $sql_data_array = [
      'customers_company' => HTML::sanitize($_POST['customers_company']) ?? '',
      'customers_firstname' => HTML::sanitize($_POST['customers_firstname']) ?? '',
      'customers_lastname' => HTML::sanitize($_POST['customers_lastname']) ?? ''
    ];

    $update_array = ['customers_id' => HTML::sanitize($_POST['customer_id'])];

    $this->app->db->save('customers', $sql_data_array, $update_array);
  }

  /**
   *
   */
  private function saveCustomerAddressBook(): void
  {
    $sql_data_array = [
      'entry_company' => HTML::sanitize($_POST['customers_company']) ?? '',
      'entry_firstname' => HTML::sanitize($_POST['customers_firstname']) ?? '',
      'entry_lastname' => HTML::sanitize($_POST['customers_lastname']) ?? '',
      'entry_street_address' => HTML::sanitize($_POST['customers_street_address']) ?? '',
      'entry_suburb' => HTML::sanitize($_POST['entry_suburb']) ?? '',
      'entry_postcode' => HTML::sanitize($_POST['entry_postcode']) ?? '',
      'entry_city' => HTML::sanitize($_POST['entry_city']) ?? '',
      'entry_state' => HTML::sanitize($_POST['state']) ?? '',
      'entry_country_id' => HTML::sanitize($_POST['country']) ?? '',
      'entry_zone_id' => HTML::sanitize($_POST['state']) ?? ''
    ];

    $update_array = [
      'address_book_id' => HTML::sanitize($_POST['address_book_id']),
      'customers_id' => HTML::sanitize($_POST['customer_id'])
    ];

    $this->app->db->save('address_book', $sql_data_array, $update_array);
  }

  /**
   *
   */
  private function saveCustomerOrderAddress(): void
  {
    $customer_name = HTML::sanitize($_POST['customers_firstname']) . ' ' . HTML::sanitize($_POST['customers_lastname']);

    $Qcountry = $this->app->db->get('countries', 'countries_name', ['countries_id' => HTML::sanitize($_POST['country'])]);
    $Qzones = $this->app->db->get('zones', 'zone_name', ['zone_id' => HTML::sanitize($_POST['state'])]);

    $sql_data_array = [
      'customers_name' => $customer_name,
      'customers_company' => HTML::sanitize($_POST['customers_company']) ?? '',
      'customers_street_address' => HTML::sanitize($_POST['customers_street_address']) ?? '',
      'customers_suburb' => HTML::sanitize($_POST['entry_suburb']) ?? '',
      'customers_postcode' => HTML::sanitize($_POST['entry_postcode']) ?? '',
      'customers_city' => HTML::sanitize($_POST['entry_city']) ?? '',
      'customers_state' => $Qzones->value('zone_name'),
      'customers_country' => $Qcountry->value('countries_name')
    ];

    $update_array = [
      'orders_id' => HTML::sanitize($_POST['order_id']),
      'customers_id' => HTML::sanitize($_POST['customer_id'])
    ];

    $this->app->db->save('orders', $sql_data_array, $update_array);
  }


  public function execute()
  {
    if (isset($_GET['UpdateCustomerAddress'])) {
      $this->saveCustomerAddress();
      $this->saveCustomerAddressBook();
      $this->saveCustomerOrderAddress();

      $this->app->redirect('Edit&oID=' . HTML::sanitize($_POST['order_id']));
    }
  }
}
