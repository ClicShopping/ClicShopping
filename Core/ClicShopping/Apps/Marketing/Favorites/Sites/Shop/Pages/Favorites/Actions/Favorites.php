<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Favorites\Sites\Shop\Pages\Favorites\Actions;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class Favorites extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
    $CLICSHOPPING_Language = Registry::get('Language');

// templates
    $this->page->setFile('favorites.php');
//Content
    $this->page->data['content'] = $CLICSHOPPING_Template->getTemplateFiles('products_favorites');
//language
    $CLICSHOPPING_Language->loadDefinitions('products_favorites');

    $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('navbar_title'), CLICSHOPPING::link(null, 'Products&Favorites'));
  }
}
