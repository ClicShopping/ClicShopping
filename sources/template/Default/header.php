<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Template = Registry::get('Template');
$CLICSHOPPING_Customer = Registry::get('Customer');
?>
<!DOCTYPE html>
<html <?php echo CLICSHOPPING::getDef('html_params'); ?>>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <?php
  // Meta Tag
  $source_folder = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/Module/Hooks/Shop/Header/';
  $output = 'HeaderOutput*';
  $call = 'HeaderCall*';
  $hook_call = 'Header';

  $CLICSHOPPING_Template->useRecursiveModulesHooksForTemplate($source_folder, $output, $call, $hook_call);
  ?>
</head>
<body id="body">
<div class="<?php echo BOOTSTRAP_CONTAINER; ?>" id="<?php echo BOOTSTRAP_CONTAINER; ?>">
  <div class="bodyWrapper" id="bodyWrapper">
    <header class="page-header" id="pageHeader">
      <?php
      if (MODE_VENTE_PRIVEE == 'false' || (MODE_VENTE_PRIVEE == 'true' && $CLICSHOPPING_Customer->isLoggedOn())) {
        echo $CLICSHOPPING_Template->getBlocks('modules_header');
      }
      ?>
    </header>
    <div class="d-flex flex-wrap frameWork" id="frameWork">
      <div id="bodyContent" role="main"
           class="col-12 col-lg-<?php echo $CLICSHOPPING_Template->getGridContentWidth(); ?> order-xs-1 order-lg-2 m-1">