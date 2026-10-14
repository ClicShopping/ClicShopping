<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

// Start the clock for the page parse time log
define('PAGE_PARSE_START_TIME', microtime());

define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../Core/ClicShopping/') . DIRECTORY_SEPARATOR);

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();

CLICSHOPPING::loadSite('ClicShoppingAdmin');
