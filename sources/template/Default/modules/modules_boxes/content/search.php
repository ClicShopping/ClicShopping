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
<section class="boxe_search" id="boxe_search">
  <div class="mt-1"></div>
  <div class="boxeBannerContentsSearch"><?php echo $search_banner; ?></div>
  <div class="card boxeContainerSearch">
    <div class="card-header boxeHeadingSearch">
      <span
        class="card-title boxeTitleSearch"><?php echo CLICSHOPPING::getDef('module_boxes_search_box_title'); ?></span>
    </div>
    <div class="card-body boxeContentArroundSearch">
      <div class="mt-1"></div>
      <div class="card-text text-center boxeContentsSearch">
        <?php
        echo $output;
        echo '<div class="boxeContentLinkAdvancedSearch">' . HTML::link(CLICSHOPPING::link(null, 'Search&AdvancedSearch'), CLICSHOPPING::getDef('module_boxes_search_box_advanced_search')) . '</div>';
        ?>
      </div>
    </div>
    <div class="card-footer boxeBottomSearch"></div>
  </div>
</section>