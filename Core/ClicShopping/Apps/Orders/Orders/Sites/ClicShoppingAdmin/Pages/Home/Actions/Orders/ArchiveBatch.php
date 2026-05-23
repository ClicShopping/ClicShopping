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

class ArchiveBatch extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Orders');
  }

  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    $orders_id_start = HTML::sanitize($_POST['orders_id_start']);
    $orders_id_end = HTML::sanitize($_POST['orders_id_end']);

    if (!empty($orders_id_start) && !empty($orders_id_end)) {
      if ($orders_id_start != 0 && $orders_id_end != 0) {
        if ($orders_id_start > $orders_id_end) {
          $CLICSHOPPING_MessageStack->add('text_errors_order', 'warning');
          $this->app->redirect('Orders');
        }

        $between_orders_id = ' where orders_id between ' . $orders_id_start . ' and ' . $orders_id_end;
      } else {
        $between_orders_id = '';
      }

      $QordersInfo = $this->app->db->prepare('select orders_id
                                                from :table_orders
                                                 ' . $between_orders_id . '
                                               ');

      $QordersInfo->execute();

      while ($QordersInfo->fetch()) {
        $this->app->db->save('orders', [
          'orders_archive' => 1,
          'last_modified' => 'now()'
        ], [
            'orders_id' => $QordersInfo->valueInt('orders_id')
          ]
        );
      }

      $this->app->redirect('Orders');
    } else {
      $CLICSHOPPING_MessageStack->add('text_errors_archive', 'warning');
    }
  }
}