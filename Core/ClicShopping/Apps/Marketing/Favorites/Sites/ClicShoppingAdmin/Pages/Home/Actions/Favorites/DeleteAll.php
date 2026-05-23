<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Favorites\Sites\ClicShoppingAdmin\Pages\Home\Actions\Favorites;

use ClicShopping\OM\Registry;

class DeleteAll extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Favorites = Registry::get('Favorites');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_POST['selected'])) {
      foreach ($_POST['selected'] as $id) {
        $Qdelete = $CLICSHOPPING_Favorites->db->prepare('delete
                                                            from :table_products_favorites
                                                            where products_favorites_id = :products_favorites_id
                                                          ');
        $Qdelete->bindInt(':products_favorites_id', (int)$id);
        $Qdelete->execute();

        $CLICSHOPPING_Hooks->call('Favorites', 'RemoveFavorites');
      }
    }

    $CLICSHOPPING_Favorites->redirect('Favorites', 'page=' . $page);
  }
}