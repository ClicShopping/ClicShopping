<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;

?>
<div class="col-12 col-md-<?php echo $bootstrap_column; ?> col-md-<?php echo $bootstrap_column; ?> p-1">
  <div class="mt-1"></div>
  <div class="card card-height">
    <div class="ModulesProductsSearchBoostrapColumn5CardHeight">
      <div class="mt-1"></div>
      <div class="card-img-top ModulesProductsSearchBoostrapColumn5Image">
        <?php echo $products_image . $ticker; ?>
      </div>
      <div class="card-body">
        <div class="ModulesProductsSearchBoostrapColumn5Title">
          <h3><?php echo HTML::link($products_name_url, $products_name); ?></h3></div>
        <div class="mt-1"></div>
        <div class="mt-1"></div>
        <?php
        if (!empty($products_short_description)) {
          ?>
          <div class="ModulesProductsSearchBoostrapColumn5ShortDescription">
            <h3><?php echo $products_short_description; ?></h3></div>
          <?php
        }
        ?>
      </div>
      <div>
        <?php
        if (!empty($products_stock)) {
          ?>
          <div class="ModulesProductsSearchBoostrapColumn5StockImage"><?php echo $products_stock; ?></div>
          <?php
        }
        if (!empty($min_order_quantity_products_display)) {
          ?>
          <div
            class="ModulesProductsSearchBoostrapColumn5QuantityMinOrder"><?php echo $min_order_quantity_products_display; ?></div>
          <?php
        }
        if (!empty($products_flash_discount)) {
          ?>
          <div class="mt-1"></div>
          <div class="EndDateFlashDiscount"> <?php echo $products_flash_discount; ?></div>
          <?php
        }
        ?>
      </div>
      <div>
        <div
          class="ModulesProductsSearchBoostrapColumn5TextPrice"><?php echo CLICSHOPPING::getDef('text_price') . ' ' . $product_price; ?></div>
      </div>
      <?php echo $form; ?>
      <div class="form-group form-group-center">
        <span class="ModulesProductsSearchBoostrapColumn5QuantityMinOrder"><?php echo $input_quantity; ?>&nbsp; </span>
        <span
          class="ModulesProductsSearchBoostrapColumn5ViewDetails"><?php echo $button_small_view_details; ?>&nbsp; </span>
        <span class="ModulesProductsSearchBoostrapColumn5SubmitButton"><label
            for="ModulesProductsSearchBoostrapColumn5SubmitButton"><?php echo $submit_button; ?></label></span>
      </div>
      <?php echo $endform; ?>
    </div>
  </div>
</div>
<?php echo $jsonLtd; ?>
