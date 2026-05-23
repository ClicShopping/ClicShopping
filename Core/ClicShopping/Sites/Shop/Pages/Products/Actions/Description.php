<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Products\Actions;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Service\Shop\WhosOnline;

class Description extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $CLICSHOPPING_ProductsFunctionTemplate = Registry::get('ProductsFunctionTemplate');

    $spider_flag = WhosOnline::getResultSpiderFlag();

    if (!$CLICSHOPPING_ProductsCommon->getID()) {
      CLICSHOPPING::redirect();
    }

    $id = (int)$CLICSHOPPING_ProductsCommon->getID();

    if ($CLICSHOPPING_ProductsCommon->getProductsGroupView() == 1 || $CLICSHOPPING_ProductsCommon->getProductsView() == 1) {

      if ($spider_flag === false) {
        $CLICSHOPPING_ProductsCommon->countUpdateProductsView();
      }
    }// end product group view

// templates
    $this->page->setFile('description.php');
//Content
    $this->page->data['content'] = $CLICSHOPPING_Template->getTemplateFiles('product_info');

//language
    $CLICSHOPPING_Language->loadDefinitions('product_info');

    $products_name_url = $CLICSHOPPING_ProductsFunctionTemplate->getProductsUrlRewrited()->getProductNameUrl($id);

    $CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('navbar_title'), $products_name_url);
    $CLICSHOPPING_Breadcrumb->add($CLICSHOPPING_ProductsCommon->getProductsName(), $products_name_url);
  }
}