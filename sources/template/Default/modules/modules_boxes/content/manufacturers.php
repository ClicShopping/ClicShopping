<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

?>
<section class="boxe_manufacturers" id="boxe_manufacturers">
  <div class="mt-1"></div>
  <div class="boxeBannerContentsManufacturer"><?php echo $manufacturer_banner; ?></div>
  <div class="card boxeContainerManufacturer">
    <div class="card-header boxeHeadingManufacturer">
      <span
        class="card-title boxeTitleManufacturer"><?php echo CLICSHOPPING::getDef('module_boxes_manufacturers_title'); ?></span>
    </div>
    <div class="card-body boxeContentArroundManufacturer">
      <div class="mt-1"></div>
      <div class="card-text boxeContentsManufacturer"><?php echo $output; ?></div>
    </div>
    <div class="card-footer boxeBottomManufacturer"></div>
  </div>
  <div class="mt-1"></div>
</section>