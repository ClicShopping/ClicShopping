<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

?>
<div class="<?php echo $text_position; ?> col-md-<?php echo $content_width; ?>">
  <div class="card ModulesProductsInfoBackground">
    <div class="card-block">
      <div class="mt-1"></div>
      <div class="card-text">
        <div class="ModulesProductsInfoPrice">
          <?php echo CLICSHOPPING::getDef('text_price_info'); ?>
          <span class="productsPrice">
              <?php echo $product_price; ?>
            </span>
          <div class="ModulesProductsInfokiloPrice">
            <span class="kiloPrice"><?php echo $product_price_kilo; ?></span>
          </div>
        </div>
        <div class="mt-1"></div>
        <div class="ModulesProductsInfoMinOrderQuandityProductsDisplay">
          <span
            class="ModulesProductsInfoMinOrderQuandityProductsDisplay"><h3><?php echo $min_order_quantity_products_display; ?></h3></span>
        </div>
        <div class="mt-1"></div>
        <div class="text-end ModulesProductsInfoQuantityMinOrderProductInfo">
          <label for="Quantity Product" class="visually-hidden"></label>
          <span class="ModulesProductsInfoQuantityMinOrderProductInfo"><?php echo $input_quantity; ?></span>
        </div>
        <div class="mt-1"></div>
        <div class="modulesProductsInfoModulesProductsInfosubmitButton">
          <span class="modulesProductsInfoModulesProductsInfosubmitButton"><label
              for="modulesProductsInfoModulesProductsInfosubmitButton"><?php echo $submit_button; ?></label></span>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="py-2"></div>