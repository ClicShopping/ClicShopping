<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Template = Registry::get('Template');

require_once($CLICSHOPPING_Template->getTemplateFiles('breadcrumb'));
?>
<div class="clearfix"></div>
<div class="mt-1"></div>
<section class="product_reviews_info" id="product_reviews_info">
  <div class="contentContainer">
    <div class="contentText">
      <div class="row m-1">
        <div class="col-md-12">
          <div class="page-title"><h4><?php echo CLICSHOPPING::getDef('heading_title_reviews'); ?></h4></div>
          <?php echo $CLICSHOPPING_Template->getBlocks('modules_products_reviews'); ?>
        </div>
      </div>
    </div>
  </div>
</section>

