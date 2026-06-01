<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\ReturnOrders\Sites\ClicShoppingAdmin\Pages\Home\Actions\OrdersReason;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Insert extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('ReturnOrders');
    $this->hooks = Registry::get('Hooks');
  }

  public function execute()
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    if (isset($_GET['oID'])) {
      $return_reason_id = HTML::sanitize($_GET['oID']);
    }

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
    $languages = $CLICSHOPPING_Language->getLanguages();

    for ($i = 0, $n = \count($languages); $i < $n; $i++) {
      $orders_status_name_array = HTML::sanitize($_POST['name']);
      $language_id = $languages[$i]['id'];

      $sql_data_array = [
        'name' => HTML::sanitize($orders_status_name_array[$language_id]),
      ];

      if (empty($return_reason_id)) {
        $Qnext = $this->app->db->get('return_orders_reason', 'max(return_reason_id) as return_reason_id');
        $return_reason_id = $Qnext->valueInt('return_reason_id') + 1;
      }

      $insert_sql_data = [
        'return_reason_id' => (int)$return_reason_id,
        'language_id' => (int)$language_id
      ];

      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $this->app->db->save('return_orders_reason', $sql_data_array);
    }

    $this->hooks->call('ReturnOrders', 'InsertOrdersStatus');

    Cache::clear('configuration');

    $this->app->redirect('OrdersReason&page' . $page . '&oID=' . $return_reason_id);
  }
}