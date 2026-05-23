<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Marketing\Recommendations\Sites\ClicShoppingAdmin\Pages\Home\Actions\Recommendations;

use ClicShopping\OM\Registry;

class SetFlag extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Recommendations = Registry::get('Recommendations');

    if (isset($_GET['flag'], $_GET['id'])) {
      self::getRecommendationsProductsStatus($_GET['id'], $_GET['flag']);
    }

    $CLICSHOPPING_Recommendations->redirect('ProductsRecommendation', (isset($_GET['page']) ? 'page=' . (int)$_GET['page'] . '&' : ''));
  }

  /**
   * Status products Recommendations products -  Sets the status of a products recommendation product
   * @param int $id
   * @param int $status
   * @return int
   */
  private static function getRecommendationsProductsStatus(int $id, int $status): int
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if ($status == 1) {
      return $CLICSHOPPING_Db->save('products_recommendations', ['status' => 1], ['products_id' => (int)$id]);
    } elseif ($status == 0) {
      return $CLICSHOPPING_Db->save('products_recommendations', ['status' => 0], ['products_id' => (int)$id]);
    } else {
      return -1;
    }
  }
}