<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Recommendations\Sites\Shop\Pages\Recommendations\Actions;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class Recommendations extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
    $CLICSHOPPING_Language = Registry::get('Language');

// templates
    $this->page->setFile('recommendations.php');
//Content
    $this->page->data['content'] = $CLICSHOPPING_Template->getTemplateFiles('products_recommendations');
//language
    $CLICSHOPPING_Language->loadDefinitions('products_recommendations');

    $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('navbar_title'), CLICSHOPPING::link(null, 'Products&Recommendations'));
  }
}
