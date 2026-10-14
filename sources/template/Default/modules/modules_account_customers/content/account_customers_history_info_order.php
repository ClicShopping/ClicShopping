<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTML;

?>
<div class="col-md-<?php echo $content_width; ?>">
  <div class="mt-1"></div>
  <div class="page-title moduleAccountCustomersHistoryInfoHeadingHorderHistory">
    <h3><?php echo CLICSHOPPING::getDef('module_account_customers_history_info_heading_order_history'); ?></h3></div>
  <div class="mt-1"></div>
  <div class="d-flex flex-wrap">
    <?php
    while ($Qstatuse->fetch()) {
      echo '<div class="col-md-12">';
      echo '<span class="text-muted"><i class="bi bi-clock-fill"></i> ' . DateTime::toShort($Qstatuse->value('date_added')) . '</span>';
      echo '<span style="padding-left:20px;">' . $Qstatuse->value('orders_status_name') . '</span>';
      echo '<div>';
      echo '<p>' . (empty($Qstatuse->value('comments')) ? '&nbsp;' : '<blockquote>' . nl2br(HTML::outputProtected($Qstatuse->value('comments'))) . '</blockquote>') . '</p>';
      echo '</div>';
      echo '</div>';
    }
    ?>
  </div>
  <div class="mt-1"></div>
  <div class="hr"></div>
</div>
