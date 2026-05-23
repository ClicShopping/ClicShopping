<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Gdpr\Sites\ClicShoppingAdmin\Pages\Home\Actions\Gdpr;

use ClicShopping\Apps\Customers\Gdpr\Classes\ClicShoppingAdmin\Gdpr as GdprAdmin;
use ClicShopping\OM\Registry;

class DeleteAll extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Gdpr = Registry::get('Gdpr');
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
    
    if (isset($_POST['selected'])) {
      foreach ($_POST['selected'] as $id) {
        GdprAdmin::deleteCustomersData($id);
      }
    }

    $CLICSHOPPING_Gdpr->redirect('Customers', 'page=' . $page);
  }
}