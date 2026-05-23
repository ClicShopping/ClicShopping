<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

?>
<section class="boxe_order_history" id="boxe_order_history">
  <div class="mt-1"></div>
  <div class="boxeBannerContentsHistory"><?php echo $order_history_banner; ?></div>
  <div class="card boxeContainerHistory">
    <div class="card-header boxeHeadingHistory">
      <span
        class="card-title boxeTitleHistory"><?php echo CLICSHOPPING::getDef('module_boxes_order_history_box_title'); ?></span>
    </div>
    <div class="card-body boxeContentArroundHistory">
      <div class="mt-1"></div>
      <div class="card-text boxeContentsHistory"><?php echo $customer_orders_string; ?></div>
    </div>
  </div>
</section>