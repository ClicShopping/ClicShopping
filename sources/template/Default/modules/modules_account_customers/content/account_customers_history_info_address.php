<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

?>
<div class="col-md-<?php echo $content_width; ?>">
  <div class="mt-1"></div>

  <div class="d-flex flex-wrap">
    <?php
    if ($CLICSHOPPING_Order->delivery !== false) {
      ?>
      <span class="col-md-6">
      <div
        class="card-header"><?php echo '<strong>' . CLICSHOPPING::getDef('module_account_customers_history_info_address_heading_delivery_address') . '</strong>'; ?></div>
      <div class="card-block">
        <div class="mt-1"></div>
        <?php echo $address_delivery; ?>
      </div>
    </span>
      <?php
      if (!empty($CLICSHOPPING_Order->info['shipping_method'])) {
        ?>
        <span class="col-md-6">
      <div
        class="card-header"><strong><?php echo CLICSHOPPING::getDef('module_account_customers_history_info_address_heading_shipping_method'); ?></strong></div>
      <div class="card-block">
        <div class="mt-1"></div>
        <?php echo $shipping_method; ?>
      </div>
    </span>
        <?php
      }
    }
    ?>
    <span class="col-md-6">
      <div>
        <div
          class="card-header"><strong><?php echo CLICSHOPPING::getDef('module_account_customers_history_info_address_heading_billing_address'); ?></strong></div>
        <div class="card-block">
          <div class="mt-1"></div>
          <?php echo $billing_address; ?></td>
        </div>
      </div>
    </span>
  </div>
  <div class="col-md-6">
    <div class="card-header">
      <strong><?php echo CLICSHOPPING::getDef('module_account_customers_history_info_address_heading_payment_method'); ?></strong>
    </div>
    <div class="card-block">
      <div class="mt-1"></div>
      <?php echo $payment_method; ?>
    </div>
  </div>
  <div class="clearfix"></div>
  <div class="mt-1"></div>
  <div class="hr"></div>
</div>

