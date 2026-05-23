<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Orders\ReturnOrders\Sites\ClicShoppingAdmin\Pages\Home\Actions\OrdersAction;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class DeleteConfirm extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('ReturnOrders');
    $this->hooks = Registry::get('Hooks');
  }

  public function execute()
  {
    if (isset($_GET['oID'])) {
      $oID = HTML::sanitize($_GET['oID']);
      $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

      $this->app->db->delete('return_orders_action', ['return_action_id' => (int)$oID]);

      $this->hooks->call('ReturnOrders', 'DeleteConfirmOrdersAction');

      Cache::clear('configuration');

      $this->app->redirect('OrdersAction&page=' . $page);
    }
  }
}