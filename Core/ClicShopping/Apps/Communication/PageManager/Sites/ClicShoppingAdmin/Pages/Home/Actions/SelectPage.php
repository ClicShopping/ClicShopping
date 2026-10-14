<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\PageManager\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class SelectPage extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_PageManager = Registry::get('PageManager');

    $this->page->setFile('select_page.php');
    $this->page->data['action'] = 'select_page';

    $CLICSHOPPING_PageManager->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}