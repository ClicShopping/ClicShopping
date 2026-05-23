<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


require_once('Core/OM.php');

$page_contents = 'install.php';

if (isset($_GET['step']) && is_numeric($_GET['step'])) {
  switch ($_GET['step']) {
    case '2':
      $page_contents = 'install_2.php';
      break;

    case '3':
      $page_contents = 'install_3.php';
      break;

    case '4':
      $page_contents = 'install_4.php';
      break;
  }
}

require_once('templates/main_page.php');
