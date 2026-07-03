<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\Registry;

$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Template = Registry::get('Template');

require_once($CLICSHOPPING_Template->getTemplateHeaderFooter('header'));

require_once($CLICSHOPPING_Page->data['content']);

require_once($CLICSHOPPING_Template->getTemplateHeaderFooter('footer'));