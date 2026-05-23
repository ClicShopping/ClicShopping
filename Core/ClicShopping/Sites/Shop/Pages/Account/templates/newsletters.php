<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

require_once($CLICSHOPPING_Template->getTemplateHeaderFooter('header'));

$CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('success_newsletter_updated'), 'success');

require_once($CLICSHOPPING_Page->data['content']);

require_once($CLICSHOPPING_Template->getTemplateHeaderFooter('footer'));
