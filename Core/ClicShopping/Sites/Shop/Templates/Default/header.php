<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

$CLICSHOPPING_NavigationHistory = Registry::get('NavigationHistory');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Template = Registry::get('Template');
$CLICSHOPPING_Customer = Registry::get('Customer');

if ($CLICSHOPPING_MessageStack->exists('header')) {
  echo $CLICSHOPPING_MessageStack->get('header');
}

if (\defined('MODE_VENTE_PRIVEE') && MODE_VENTE_PRIVEE == 'true') {
  if (!$CLICSHOPPING_Customer->isLoggedOn() && (!strstr($_SERVER['QUERY_STRING'], 'Account&LogIn'))) {
    if (
      (!strstr($_SERVER['QUERY_STRING'], 'Account&Create')) &&
      (!strstr($_SERVER['QUERY_STRING'], 'Account&PasswordForgotten')) &&
      (!strstr($_SERVER['QUERY_STRING'], 'Account&CreatePro.php')) &&
      (!strstr($_SERVER['QUERY_STRING'], 'Info&contact.php'))
    ) {
      $CLICSHOPPING_NavigationHistory->setSnapshot();

      CLICSHOPPING::redirect(null, 'Account&LogIn');
    }
  }
}

$CLICSHOPPING_Template->buildBlocks();

if (!$CLICSHOPPING_Template->hasBlocks('boxes_column_left')) {
  $CLICSHOPPING_Template->setGridContentWidth($CLICSHOPPING_Template->getGridContentWidth() + $CLICSHOPPING_Template->getGridColumnWidth());
}

if (!$CLICSHOPPING_Template->hasBlocks('boxes_column_right')) {
  $CLICSHOPPING_Template->setGridContentWidth($CLICSHOPPING_Template->getGridContentWidth() + $CLICSHOPPING_Template->getGridColumnWidth());
}
