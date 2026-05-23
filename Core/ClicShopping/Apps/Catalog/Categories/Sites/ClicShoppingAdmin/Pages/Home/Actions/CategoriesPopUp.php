<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Categories\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class CategoriesPopUp extends \ClicShopping\OM\Domains\PagesActionsAbstract
{

  public function execute()
  {
    $CLICSHOPPING_Categories = Registry::get('Categories');

    $this->page->setUseSiteTemplate(false); //don't display Header / Footer
    $this->page->setFile('categories_popup.php');
    $this->page->data['action'] = 'CategoriesPopUp';

    $CLICSHOPPING_Categories->loadDefinitions('Sites/ClicShoppingAdmin/categories_popup');
  }
}