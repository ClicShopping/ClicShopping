<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

?>
<section class="boxe_information_customize" id="boxe_information_customize">
  <div class="mt-1"></div>
  <div class="boxeBannerContentsPageManagerCustomize"><?php echo $pm_customomize_banner; ?></div>
  <div class="card boxeContainerPageManagerCustomize">
    <div class="card-header boxeHeadingPageManagerCustomize">
      <span
        class="card-title boxeTitlePageManagerCustomize"><?php echo CLICSHOPPING::getDef('module_boxes_page_manager_customize_box_title'); ?></span>
    </div>
    <div class="card-body boxeContentArroundPageManagerCustomize">
      <div class="card-text boxeContentsPageManagerCustomize">
        <ul class="boxeListManagerPageManagerCustomize">
          <li class="list-inline-item boxeListManagerPageManagerCustomize"><?php echo $link; ?></li>
        </ul>
      </div>
    </div>
    <div class="card-footer boxeBottomPageManagerCustomize"></div>
  </div>
</section>