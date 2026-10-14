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

if (!$CLICSHOPPING_Customer->isLoggedOn()) {
  CLICSHOPPING::redirect(null, 'Account&LogIn');
}
