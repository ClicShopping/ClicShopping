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
<section class="boxe_shopping_cart" id="boxe_shopping_cart">
  <div class="mt-1"></div>
  <div class="ClicShoppingboxContentShoppingCartBanner"><?php echo $shopping_cart_banner; ?></div>
  <div class="card boxeContainerShoppingCart">
    <div class="card-header ClicShoppingboxHeadingShoppingCart">
      <span
        class="card-title boxeTitleShoppingCart"><?php echo HTML::link(CLICSHOPPING::link(null, 'Cart'), CLICSHOPPING::getDef('module_boxes_shopping_cart_box_title')); ?></span>
    </div>
    <div class="card-body boxeContentArroundShoppingCart">
      <div class="mt-1"></div>
      <?php echo $cart_contents_string; ?>
    </div>
    <div class="card-footer boxeBottomContentsShoppingCart"></div>
  </div>
</section>