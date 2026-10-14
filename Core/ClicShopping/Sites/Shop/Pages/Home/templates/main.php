<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\Registry;

$CLICSHOPPING_Customer = Registry::get('Customer');
$CLICSHOPPING_Template = Registry::get('Template');
$CLICSHOPPING_Category = Registry::get('Category');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');

$CLICSHOPPING_Language->loadDefinitions('index');

if ($CLICSHOPPING_Category->getDepth() == 'products') {
  if ($CLICSHOPPING_Category->getCountCategoriesNested() > 0) {
    require_once($CLICSHOPPING_Template->getTemplateFiles('index_listing'));
  } else {
// nested
    require_once($CLICSHOPPING_Template->getTemplateFiles('index_categories'));
  }
} elseif ($CLICSHOPPING_Category->getDepth() == 'nested' || (isset($_GET['manufacturersId']) && !empty($_GET['manufacturersId']))) {
//CATEGORIES Page  2nd level / Listing
  require_once($CLICSHOPPING_Template->getTemplateFiles('index_listing'));
} else {
  //Index page
  require_once($CLICSHOPPING_Template->getTemplateFiles('index_default'));
}
