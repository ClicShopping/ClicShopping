<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

?>
<section class="boxe_currencies" id="boxe_currencies">
  <div class="mt-1"></div>
  <div class="boxeBannerContentsCurrencies"><?php echo $currencies_banner; ?></div>
  <div class="card boxeContainerCurrencies">
    <div class="card-header boxeHeadingCurrencies">
      <span
        class="card-title boxeTitleCurrencies"><?php echo CLICSHOPPING::getDef('module_boxes_currencies_box_title'); ?></span>
    </div>
    <div class="card-body boxeContentArroundCurrencies">
      <div class="mt-1"></div>
      <div class="card-text boxeContentsCurrencies"><?php echo $output; ?></div>
    </div>
    <div class="card-footer boxeBottomCurrencies"></div>
  </div>
</section>
