<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

?>
<section class="boxe_manufacturer_info" id="boxe_manufacturer_info">
  <div class="mt-1"></div>
  <div class="boxeBannerContentsManufacturerInfo"><?php echo $manufacturer_infos_banner; ?></div>
  <div class="card boxeContainerManufacturerInfo">
    <div class="card-header boxeHeadingManufacturerInfo">
      <span
        class="card-title boxeTitleManufacturerInfo"><?php echo CLICSHOPPING::getDef('module_boxes_manufacturer_info_box_title'); ?></span>
    </div>
    <div class="card-body boxeContentArroundManufacturerInfo">
      <div class="mt-1"></div>
      <div class="card-text boxeContentsManufacturerInfo"><?php echo $manufacturer_info_string; ?></div>
    </div>
    <div class="card-footer boxeBottomManufacturerInfo"></div>
  </div>
</section>