<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

chdir('../../');

require('Core/OM.php');

ob_start();

CLICSHOPPING::redirect();

// Afficher le contenu du buffer
ob_end_flush();