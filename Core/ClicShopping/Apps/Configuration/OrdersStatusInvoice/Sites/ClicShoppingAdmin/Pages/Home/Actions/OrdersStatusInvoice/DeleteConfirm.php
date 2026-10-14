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

class DeleteConfirm extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('OrdersStatusInvoice');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
    $oID = HTML::sanitize($_GET['oID']);

    $Qstatus = $this->app->db->get('configuration', 'configuration_value', ['configuration_key' => 'DEFAULT_ORDERS_STATUS_INVOICE_ID']);

    if ($Qstatus->value('configuration_value') == $oID) {
      $this->app->db->save('configuration', [
        'configuration_value' => ''
      ], [
          'configuration_key' => 'DEFAULT_ORDERS_STATUS_INVOICE_ID'
        ]
      );
    }

    $this->app->db->delete('orders_status_invoice', ['orders_status_invoice_id' => (int)$oID]);

    Cache::clear('configuration');

    $this->app->redirect('OrdersStatusInvoice&page=' . $page);
  }
}