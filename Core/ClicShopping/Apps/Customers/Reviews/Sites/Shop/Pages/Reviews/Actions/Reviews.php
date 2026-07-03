<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Sites\Shop\Pages\Reviews\Actions;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class Reviews extends \ClicShopping\OM\Domains\PagesActionsAbstract
{

  public function execute()
  {
    if (isset($_GET['products_id']) && isset($_GET['Products'])) {
      $CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
      $CLICSHOPPING_Template = Registry::get('Template');
      $CLICSHOPPING_Language = Registry::get('Language');
      $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');

// templates
      $this->page->setFile('reviews.php');
//Content
      $this->page->data['content'] = $CLICSHOPPING_Template->getTemplateFiles('reviews');
//language
      $CLICSHOPPING_Language->loadDefinitions('reviews');

      $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('navbar_title'), CLICSHOPPING::link(null, 'Products&Reviews&products_id=' . (int)$CLICSHOPPING_ProductsCommon->getId()));
    }
  }
}