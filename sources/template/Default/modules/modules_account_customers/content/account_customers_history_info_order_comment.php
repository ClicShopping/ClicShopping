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
use ClicShopping\Sites\Shop\Pages\Account\Classes\HistoryInfo;

?>
<div class="col-md-<?php echo $content_width; ?>">
  <div class="mt-1"></div>
  <div class="card">
    <div class="card-header">
      <div class="modulesAccountCustomerHistoryInforOrderCommentHeadingHistory">
        <h3><?php echo CLICSHOPPING::getDef('module_account_customers_history_info_order_comment_heading_history'); ?></h3>
      </div>
    </div>
    <div class="card-block">
      <div class="mt-1"></div>
      <div class="card-text">
        <div class="row">
          <?php

          foreach ($Qstatuse as $value) {
            $customer_support = HistoryInfo::getHistoryInfoSupportCustomer($value['orders_status_support_id']);

            echo '<div class="mt-1"></div>';
            echo '<div class="col-md-12">';
            echo '<span class="col-md-4 text-muted"><i class="bi bi-arrow-right-square-fill" aria-hidden="true">&nbsp;&nbsp;&nbsp;</i>' . DateTime::toShort($value['date_added']) . '</span> ';
            echo '<span class="col-md-8 modulesAccountCustomerHistoryInforOrderCommentStatusName">' . $value['orders_status_name'] . '</span>';

            if (!empty($customer_support['orders_status_support_name'])) {
              echo '<div class="col-md-12 modulesAccountCustomerHistoryInforOrderCommentStatusSupport">' . CLICSHOPPING::getDef('module_account_customers_history_info_order_comment_support') . $customer_support['orders_status_support_name'] . '</div>';
            }

            echo '</div>';
            echo '<div class="mt-1"></div>';
            echo '<div class="col-md-12">';
            echo '<span class="col-md-4"></span>';
            echo '<span class="col-md-8 modulesAccountCustomerHistoryInforOrderCommentText">' . (empty($value['comments']) ? '&nbsp;' : '' . nl2br(HTML::outputProtected($value['comments'])) . '</span>') . '</span>';
            echo '</div>';
            echo '<div class="mt-1"></div>';
          }
          ?>
        </div>
      </div>
    </div>
  </div>
</div>
