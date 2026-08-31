<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Recommendations\Sites\ClicShoppingAdmin\Pages\Home\Actions\Recommendations;

use ClicShopping\OM\Registry;

class DeleteAll extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Recommendations = Registry::get('Recommendations');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_POST['selected'], $_GET['DeleteAll'], $_GET['Recommendations'])) {
      foreach ($_POST['selected'] as $id) {
        $CLICSHOPPING_Recommendations->db->delete('customers_basket', ['products_id' => (int)$id]);

        $CLICSHOPPING_Hooks->call('Recommendations', 'RemoveRecommendations');
      }
    }

    $CLICSHOPPING_Recommendations->redirect('ProductsRecommendation', 'page=' . $page);
  }
}