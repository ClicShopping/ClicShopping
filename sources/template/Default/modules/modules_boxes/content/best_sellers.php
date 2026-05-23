<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

?>
<section class="boxe_best_sellers" id="boxe_best_sellers">
  <div class="mt-1"></div>
  <div class="boxeBannerContentsBestSellers"><?php echo $best_sellers_banner; ?></div>
  <div class="card boxeContainerBestSellers" itemscope itemtype="https://schema.org/ItemList">
    <meta itemprop="itemListOrder" content="https://schema.org/ItemListOrderDescending"/>
    <div class="card-header boxeHeadingBestSellers"
         itemprop="name"><?php echo CLICSHOPPING::getDef('module_boxes_best_sellers_box_title'); ?></div>
    <div class="card-body boxeContentArroundBestSellers">
      <div class="mt-1"></div>
      <?php echo $bestsellers_list; ?>
    </div>
  </div>
</section>
