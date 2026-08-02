<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\PageManager\Sites\ClicShoppingAdmin\Pages\Home\Actions\PageManager;

use ClicShopping\Apps\Communication\PageManager\PageManager as PageManagerApp;
use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class DeleteAll extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_PageManager = Registry::get('PageManager');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (!\is_null($_POST['selected']) && isset($_POST['selected']) && \is_array($_POST['selected'])) {
      foreach ($_POST['selected'] as $id) {
        if (in_array((int)$id, PageManagerApp::LOCKED_PAGES_ID, true)) {
          continue;
        }

        $CLICSHOPPING_PageManager->db->delete('pages_manager', ['pages_id' => (int)$id]);
        $CLICSHOPPING_PageManager->db->delete('pages_manager_description', ['pages_id' => (int)$id]);

        $CLICSHOPPING_Hooks->call('PageManager', 'DeleteAll');
      }
    }

    Cache::clear('boxe_page_manager_primary-');
    Cache::clear('boxe_page_manager_secondary-');
    Cache::clear('page_manager_display_header_menu-');
    Cache::clear('page_manager_display_footer_menu-');
    Cache::clear('page_manager_display_footer-');
    Cache::clear('boxe_page_manager_display_information-');
    Cache::clear('boxe_page_manager_display_title-');

    $CLICSHOPPING_PageManager->redirect('PageManager&page=' . $page);
  }
}