<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\BannerManager\Sites\ClicShoppingAdmin\Pages\Home\Actions\BannerManager;

use ClicShopping\OM\Registry;

class DeleteAll extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_BannerManager = Registry::get('BannerManager');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_POST['selected']) && \is_array($_POST['selected']) && !empty($_POST['selected'])) {
      foreach ($_POST['selected'] as $id) {
        $CLICSHOPPING_BannerManager->db->delete('banners', ['banners_id' => (int)$id]);
        $CLICSHOPPING_BannerManager->db->delete('banners_history', ['banners_id' => (int)$id]);

        $CLICSHOPPING_Hooks->call('BannerManager', 'RemoveBanner');
      }
    }

    $CLICSHOPPING_BannerManager->redirect('BannerManager', 'page=' . $page);
  }
}