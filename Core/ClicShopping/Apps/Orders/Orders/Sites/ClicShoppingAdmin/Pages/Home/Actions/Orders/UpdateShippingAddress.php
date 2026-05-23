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

class UpdateShippingAddress extends \ClicShopping\OM\Domains\PagesActionsAbstract
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
  private function saveShippingOrderAddress(): void
  {
    $Qcountry = $this->app->db->get('countries', 'countries_name', ['countries_id' => HTML::sanitize($_POST['country'])]);
    $Qzones = $this->app->db->get('zones', 'zone_name', ['zone_id' => HTML::sanitize($_POST['state'])]);

    $sql_data_array = [
      'delivery_name' => HTML::sanitize($_POST['delivery_name']) ?? '',
      'delivery_company' => HTML::sanitize($_POST['customers_company']) ?? '',
      'delivery_street_address' => HTML::sanitize($_POST['delivery_street_address']) ?? '',
      'delivery_suburb' => HTML::sanitize($_POST['entry_suburb']) ?? '',
      'delivery_postcode' => HTML::sanitize($_POST['entry_postcode']) ?? '',
      'delivery_city' => HTML::sanitize($_POST['entry_city']) ?? '',
      'delivery_state' => $Qzones->value('zone_name'),
      'delivery_country' => $Qcountry->value('countries_name')
    ];

    $update_array = [
      'orders_id' => HTML::sanitize($_POST['order_id'])
    ];

    $this->app->db->save('orders', $sql_data_array, $update_array);
  }


  public function execute()
  {
    if (isset($_GET['UpdateShippingAddress'])) {
      $this->saveShippingOrderAddress();

      $this->app->redirect('Edit&oID=' . HTML::sanitize($_POST['order_id']));
    }
  }
}
