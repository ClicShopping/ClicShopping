<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\ReturnOrders\Sites\ClicShoppingAdmin\Pages\Home\Actions\ReturnOrders;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class SetFlag extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_ReturnOrders = Registry::get('ReturnOrders');

    $return_id = HTML::sanitize($_GET['rID']);

    static::getProductReturnOrdersStatus($_GET['rID'], $_GET['flag']);

    $CLICSHOPPING_ReturnOrders->redirect('ReturnOrders&', (isset($_GET['page']) ? 'page=' . (int)$_GET['page'] . '&' : '') . 'rID=' . $return_id);
  }


  /**
   * Status products return_orders - Sets the return_orders of a productts
   *
   * @param int $return_id
   * @param int $opened
   * @return string status on or off
   *
   */
  private static function getProductReturnOrdersStatus(int $return_id, int $opened)
  {
    $CLICSHOPPING_ReturnOrders = Registry::get('ReturnOrders');

    if ($opened === 1) {
      return $CLICSHOPPING_ReturnOrders->db->save('return_orders', [
        'opened' => 1,
        'date_modified' => 'now()'
      ],
        ['return_id' => (int)$return_id]
      );
    } elseif ($opened === 0) {
      return $CLICSHOPPING_ReturnOrders->db->save('return_orders', [
        'opened' => 0,
        'date_modified' => 'now()'
      ],
        ['return_id' => (int)$return_id]
      );
    } else {
      return -1;
    }
  }
}