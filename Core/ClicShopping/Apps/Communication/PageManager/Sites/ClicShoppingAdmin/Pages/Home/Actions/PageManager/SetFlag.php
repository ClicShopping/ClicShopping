<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\PageManager\Sites\ClicShoppingAdmin\Pages\Home\Actions\PageManager;

use ClicShopping\Apps\Communication\PageManager\Classes\ClicShoppingAdmin\Status;
use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class SetFlag extends \ClicShopping\OM\Domains\PagesActionsAbstract
{

  public function execute()
  {

    $CLICSHOPPING_PageManager = Registry::get('PageManager');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    Status::getPageManagerStatus($_GET['id'], $_GET['flag']);

    $CLICSHOPPING_MessageStack->add($CLICSHOPPING_PageManager->getDef('success_page_manager_status_updated'), 'success');

    Cache::clear('boxe_page_manager_primary-');
    Cache::clear('boxe_page_manager_secondary-');
    Cache::clear('page_manager_display_header_menu-');
    Cache::clear('page_manager_display_footer_menu-');
    Cache::clear('page_manager_display_footer-');
    Cache::clear('boxe_page_manager_display_information-');
    Cache::clear('boxe_page_manager_display_title-');

    $CLICSHOPPING_PageManager->redirect('PageManager&page=' . $page . '&bID=' . (int)$_GET['id']);
  }
}