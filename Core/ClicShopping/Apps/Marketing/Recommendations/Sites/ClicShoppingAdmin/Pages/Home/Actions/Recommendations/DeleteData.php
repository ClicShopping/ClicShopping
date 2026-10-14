<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Recommendations\Sites\ClicShoppingAdmin\Pages\Home\Actions\Recommendations;

use ClicShopping\OM\Registry;

class DeleteData extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Recommendations = Registry::get('Recommendations');
    $CLICSHOPPING_Db = Registry::get('Db');

    if (isset($_GET['DeleteData'])) {
      $CLICSHOPPING_Db->delete('products_recommendations');
      $CLICSHOPPING_Db->delete('products_recommendations_to_categories');
    }

    $CLICSHOPPING_Recommendations->redirect('Recommendations');
  }
}