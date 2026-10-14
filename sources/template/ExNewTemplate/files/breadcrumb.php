<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Service = Registry::get('Service');
$CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
$CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
?>

<div class="mt-1"></div>
<div class="contentContainer">
  <div class="contentText">

    <?php
    if (\defined('MODULE_HEADER_BREADCRUMP_STATUS')) {
      if (MODULE_HEADER_BREADCRUMP_STATUS == 'False' || empty(ClicShopping::getDef('module_header_breadcrump_title'))) {
        ?>
        <div class="mt-1"></div>
        <div class="col-md-12 breadcrumb">
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
              <li>
                <?php
                if ($CLICSHOPPING_Service->isStarted('Breadcrumb')) {
                  echo $CLICSHOPPING_Breadcrumb->get(' &raquo; ');
                }
                ?>
              </li>
            </ol>
          </nav>
        </div>
        <?php
        if (!CLICSHOPPING::getBaseNameIndex() && $CLICSHOPPING_ProductsCommon->getID()) {
          ?>
          <div class="page-header">
            <h1><?php echo CLICSHOPPING::getDef('heading_title'); ?></h1>
          </div>
          <?php
        }
      }
    } else {
      ?>
      <div class="mt-1"></div>
      <div class="col-md-12 breadcrumb">
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li>
              <?php
              if ($CLICSHOPPING_Service->isStarted('Breadcrumb')) {
                echo $CLICSHOPPING_Breadcrumb->get(' &raquo; ');
              }
              ?>
              </span>
            </li>
          </ol>
        </nav>
      </div>
      <?php
      if (!CLICSHOPPING::getBaseNameIndex() && $CLICSHOPPING_ProductsCommon->getID()) {
        ?>
        <div class="page-header">
          <h1><?php echo CLICSHOPPING::getDef('heading_title'); ?></h1>
        </div>
        <?php
      }
    }
    ?>
  </div>
</div>
