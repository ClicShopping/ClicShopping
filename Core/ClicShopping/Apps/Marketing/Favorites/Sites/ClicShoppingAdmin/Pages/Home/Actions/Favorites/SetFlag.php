<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Marketing\Favorites\Sites\ClicShoppingAdmin\Pages\Home\Actions\Favorites;

use ClicShopping\OM\Registry;

class SetFlag extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Favorites = Registry::get('Favorites');

    if (isset($_GET['flag'], $_GET['id'])) {
      static::getFavoritesProductsStatus($_GET['id'], $_GET['flag']);
    }

    $CLICSHOPPING_Favorites->redirect('Favorites', (isset($_GET['page']) ? 'page=' . (int)$_GET['page'] . '&' : '') . 'sID=' . (int)$_GET['id']);
  }

  /**
   * Status products favorites products -  Sets the status of a favrite product
   * @param $products_favorites_id
   * @param $status
   * @return int
   */
  public static function getFavoritesProductsStatus($products_favorites_id, $status)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if ($status == 1) {
      return $CLICSHOPPING_Db->save('products_favorites', ['status' => 1,
        'scheduled_date' => 'null',
        'expires_date' => 'null',
        'date_status_change' => 'null'
      ],
        ['products_favorites_id' => (int)$products_favorites_id]
      );
    } elseif ($status == 0) {
      return $CLICSHOPPING_Db->save('products_favorites', [
        'status' => 0,
        'date_status_change' => 'now()'
      ],
        ['products_favorites_id' => (int)$products_favorites_id]
      );
    } else {
      return -1;
    }
  }
}