<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Featured\Sites\ClicShoppingAdmin\Pages\Home\Actions\Featured;

use ClicShopping\OM\Registry;

class DeleteAll extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Featured = Registry::get('Featured');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_POST['selected']) && \is_array($_POST['selected']) && !empty($_POST['selected'])) {
      foreach ($_POST['selected'] as $id) {
        $Qdelete = $CLICSHOPPING_Featured->db->prepare('delete
                                            from :table_products_featured
                                            where products_featured_id = :products_featured_id
                                          ');
        $Qdelete->bindInt(':products_featured_id', (int)$id);
        $Qdelete->execute();
      }
    }

    $CLICSHOPPING_Featured->redirect('Featured', 'page=' . $page);
  }
}