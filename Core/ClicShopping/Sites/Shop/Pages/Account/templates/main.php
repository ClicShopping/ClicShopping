<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Customer = Registry::get('Customer');
$CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
$CLICSHOPPING_Template = Registry::get('Template');
$CLICSHOPPING_NavigationHistory = Registry::get('NavigationHistory');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Language = Registry::get('Language');

if (!$CLICSHOPPING_Customer->isLoggedOn()) {
  $CLICSHOPPING_NavigationHistory->setSnapshot();
  CLICSHOPPING::redirect(null, 'Account&LogIn');
}

// templates
$CLICSHOPPING_Language->loadDefinitions('account');

$CLICSHOPPING_Breadcrumb->add(CLICSHOPPING::getDef('navbar_title'), CLICSHOPPING::link(null, 'Account&Main'));

require_once($CLICSHOPPING_Template->getTemplateHeaderFooter('header'));

require_once($CLICSHOPPING_Template->getTemplateFiles('account'));

require_once($CLICSHOPPING_Template->getTemplateHeaderFooter('footer'));
