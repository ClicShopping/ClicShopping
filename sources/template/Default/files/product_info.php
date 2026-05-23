<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
  use ClicShopping\OM\HTTP;
  use ClicShopping\OM\Registry;

$CLICSHOPPING_Template = Registry::get('Template');
$CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');

// ----------------------------------------------------------------//
//                      Product Display                            //
// ----------------------------------------------------------------//
// Note: Product validation is now handled in Products controller
// before headers are sent to prevent "headers already sent" errors

if ($CLICSHOPPING_ProductsCommon->getProductsGroupView() == 1 || $CLICSHOPPING_ProductsCommon->getProductsView() == 1) {
// ----------------------------------------------------------------
// ---- Display products with autorization  ----
// ------------------------------------------------------------
  require_once($CLICSHOPPING_Template->getTemplateFiles('breadcrumb'));
  $CLICSHOPPING_ProductsCommon->countUpdateProductsView();
  ?>
  <section class="product" id="product">
    <div class="contentContainer">
      <div class="contentText">
        <div class="productsInfoContent">
          <?php echo $CLICSHOPPING_Template->getBlocks('modules_products_info'); ?>
        </div>
      </div>
    </div>
  </section>
  <?php
}
