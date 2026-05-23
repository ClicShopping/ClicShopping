<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Archive\Sites\ClicShoppingAdmin\Pages\Home\Actions\Archive;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class SetFlag extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Archive = Registry::get('Archive');

    $products_id = HTML::sanitize($_GET['aID']);

    static::getProductArchiveStatus($_GET['aID'], $_GET['flag']);

    Cache::clear('categories');
    Cache::clear('products-also_purchased');
    Cache::clear('products_related');
    Cache::clear('products_cross_sell');
    Cache::clear('upcoming');

    $CLICSHOPPING_Archive->redirect('Archive&', (isset($_GET['page']) ? 'page=' . (int)$_GET['page'] . '&' : '') . 'aID=' . $products_id);
  }


  /**
   * Status products archive - Sets the archive of a productts
   *
   * @param string products_id, archive
   * @return string status on or off
   *
   *
   */
  private static function getProductArchiveStatus($products_id, $archive)
  {
    $CLICSHOPPING_Archive = Registry::get('Archive');

    if ($archive == 1) {
      return $CLICSHOPPING_Archive->db->save('products', ['products_status' => 1,
        'products_last_modified' => 'now()'
      ],
        ['products_id' => (int)$products_id]
      );
    } elseif ($archive == 0) {
      return $CLICSHOPPING_Archive->db->save('products', ['products_status' => 0,
        'products_last_modified' => 'now()'
      ],
        ['products_id' => (int)$products_id]
      );
    } else {
      return -1;
    }
  }
}