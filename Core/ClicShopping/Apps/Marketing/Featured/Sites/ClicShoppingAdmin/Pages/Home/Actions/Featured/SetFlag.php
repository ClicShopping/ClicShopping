<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Marketing\Featured\Sites\ClicShoppingAdmin\Pages\Home\Actions\Featured;

use ClicShopping\OM\Registry;

class SetFlag extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Featured = Registry::get('Featured');

    if (isset($_GET['flag'], $_GET['id'])) {
      static::getFeaturedProductsStatus($_GET['id'], $_GET['flag']);
    }

    $CLICSHOPPING_Featured->redirect('Featured', (isset($_GET['page']) ? 'page=' . (int)$_GET['page'] . '&' : '') . 'sID=' . (int)$_GET['id']);
  }

  /**
   * Status products featured products -  Sets the status of a favrite product
   * @param int $products_featured_id
   * @param int $status
   * @return int
   */
  public static function getFeaturedProductsStatus(int $products_featured_id, int $status)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if ($status == 1) {

      return $CLICSHOPPING_Db->save('products_featured', [
        'status' => 1,
        'scheduled_date' => 'null',
        'expires_date' => 'null',
        'date_status_change' => 'null'
      ],
        ['products_featured_id' => (int)$products_featured_id]
      );

    } elseif ($status == 0) {

      return $CLICSHOPPING_Db->save('products_featured', [
        'status' => 0,
        'date_status_change' => 'now()'
      ],
        ['products_featured_id' => (int)$products_featured_id]
      );

    } else {
      return -1;
    }
  }
}