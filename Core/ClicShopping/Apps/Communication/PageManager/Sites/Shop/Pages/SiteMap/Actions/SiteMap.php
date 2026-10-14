<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\PageManager\Sites\Shop\Pages\SiteMap\Actions;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class SiteMap extends \ClicShopping\OM\Domains\PagesActionsAbstract
{

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
    $CLICSHOPPING_PageManager = Registry::get('PageManager');

// templates
    $this->page->setFile('sitemap.php');
//Content
    $this->page->data['content'] = $CLICSHOPPING_Template->getTemplateFiles('sitemap');
//language
    $CLICSHOPPING_PageManager->loadDefinitions('Sites/Shop/SiteMap/sitemap');

    $CLICSHOPPING_Breadcrumb->add($CLICSHOPPING_PageManager->getDef('navbar_title'), CLICSHOPPING::link(null, 'Info&SiteMap'));

  }
}
