<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
$CLICSHOPPING_Template = Registry::get('Template');
$CLICSHOPPING_Language = Registry::get('Language');

$CLICSHOPPING_Language->loadDefinitions('shopping_cart');

// templates
$CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('navbar_title'), CLICSHOPPING::link(null, 'Cart'));

require_once($CLICSHOPPING_Template->getTemplateHeaderFooter('header'));

require_once($CLICSHOPPING_Template->getTemplateFiles('shopping_cart'));

require_once($CLICSHOPPING_Template->getTemplateHeaderFooter('footer'));
