<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

?>
  <div
    class="col-12 col-sm-<?php echo $bootstrap_column; ?> col-md-<?php echo $bootstrap_column; ?> ModulesProductsNewBoostrapColumn5">
    <div class="mt-1"></div>
    <div class="card card-height">
      <div class="ModulesProductsNewBoostrapColumn5CardHeight">
        <div class="mt-1"></div>
        <div class="card-img-top ModulesProductsListingBoostrapColumn5Image">
          <?php echo $products_image . $ticker; ?>
        </div>
        <div class="card-body">
          <div class="ModulesProductsListingBoostrapColumn5Title"><h3><?php echo $manufacturer_name; ?></h3></div>
          <div class="mt-1"></div>
          <div class="mt-1"></div>
          <?php
          if (!empty($products_short_description)) {
            ?>
            <div class="ModulesProductsListingBoostrapColumn5ShortDescription">
              <h3><?php echo $products_short_description; ?></h3></div>
            <?php
          }
          ?>
        </div>
        <div>
          <?php
          if (!empty($products_stock)) {
            ?>
            <div class="ModulesProductsListingBoostrapColumn5StockImage"><?php echo $products_stock; ?></div>
            <?php
          }
          if (!empty($min_order_quantity_products_display)) {
            ?>
            <div
              class="ModulesProductsListingBoostrapColumn5QuantityMinOrder"><?php echo $min_order_quantity_products_display; ?></div>
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

        <div
          class="ModulesProductsListingBoostrapColumn5TextPrice"><?php echo CLICSHOPPING::getDef('text_price') . ' ' . $product_price; ?></div>
        <?php echo $form; ?>
        <div class="form-group form-group-center">
          <span
            class="ModulesProductsListingBoostrapColumn5QuantityMinOrder"><?php echo $input_quantity; ?>&nbsp; </span>
          <span
            class="ModulesProductsListingBoostrapColumn5ViewDetails"><?php echo $button_small_view_details; ?>&nbsp; </span>
          <span class="ModulesProductsListingBoostrapColumn5SubmitButton"><label
              for="ModulesProductsListingBoostrapColumn5SubmitButton"><?php echo $submit_button; ?></label></span>
        </div>
        <?php echo $endform; ?>
      </div>
    </div>
  </div>
<?php echo $jsonLtd; ?>