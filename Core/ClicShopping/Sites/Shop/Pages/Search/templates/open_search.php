<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\Registry;

header('Content-Type: text/xml');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

echo $output;