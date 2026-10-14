<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\Registry;

$CLICSHOPPING_MessageStack = Registry::get('MessageStack');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

require_once($CLICSHOPPING_Template->getTemplateHeaderFooter('header'));

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}

require_once($CLICSHOPPING_Page->data['content']);

require_once($CLICSHOPPING_Template->getTemplateHeaderFooter('footer'));
