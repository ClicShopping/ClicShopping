<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\OrdersStatusInvoice\Sites\ClicShoppingAdmin\Pages\Home\Actions\OrdersStatusInvoice;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('OrdersStatusInvoice');
  }

  public function execute()
  {
    $CLICSHOPPING_Language = Registry::get('Language');
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_GET['oID'])) {
      $orders_status_invoice_id = HTML::sanitize($_GET['oID']);
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $orders_status_invoice_name_array = $_POST['orders_status_invoice_name'];
        $language_id = $languages[$i]['id'];

        $sql_data_array = ['orders_status_invoice_name' => HTML::sanitize($orders_status_invoice_name_array[$language_id])];

        $this->app->db->save('orders_status_invoice', $sql_data_array, ['orders_status_invoice_id' => (int)$orders_status_invoice_id,
            'language_id' => (int)$language_id
          ]
        );
      }

      if (isset($_POST['default'])) {
        $this->app->db->save('configuration', [
          'configuration_value' => $orders_status_invoice_id
        ], [
            'configuration_key' => 'DEFAULT_ORDERS_STATUS_INVOICE_ID'
          ]
        );
      }

      Cache::clear('configuration');

      $this->app->redirect('OrdersStatusInvoice&page=' . $page . '&oID=' . $orders_status_invoice_id);
    } else {
      $this->app->redirect('OrdersStatusInvoice&page=' . $page);
    }
  }
}