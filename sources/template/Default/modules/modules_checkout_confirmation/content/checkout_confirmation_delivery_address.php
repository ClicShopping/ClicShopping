<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

?>
<div class="col-md-<?php echo $content_width; ?> m1">
  <div class="mt-1"></div>
  <div class="page-title moduleCheckoutConfirmationDeliveryAddressPageHeader">
    <h3><?php echo CLICSHOPPING::getDef('module_checkout_confirmation_delivery_address_heading_delivery'); ?></h3></div>
  <?php
  if ($_SESSION['sendto'] !== false) {
    ?>
    <span class="col-md-6 float-start" style="padding-right:0.5rem;">
        <div class="card moduleCheckoutConfirmationDeliveryAddressCard">
          <div class="card-header moduleCheckoutConfirmationDeliveryAddressHeader">
            <strong><?php echo CLICSHOPPING::getDef('module_checkout_confirmation_delivery_address_text_delivery_title'); ?></strong><?php echo $delivery_address_link; ?>
          </div>
          <div class="card-block moduleCheckoutConfirmationDeliveryAddressCardBlock">
            <div class="mt-1"></div>
            <div class="mt-1"></div>
            <?php echo $delivery_address; ?>
          </div>
        </div>
        <div class="mt-1"></div>
      </span>

    <?php
    if ($shipping_method) {
      ?>
      <span class="col-md-6 float-end" style="padding-right:0.5rem;">
          <div class="card moduleCheckoutConfirmationDeliveryAddressCard">
            <div class="card-header moduleCheckoutConfirmationDeliveryAddressHeader">
              <strong><?php echo CLICSHOPPING::getDef('module_checkout_confirmation_delivery_address_text_shipping_title'); ?></strong><?php echo $shipping_link; ?>
            </div>
            <div class="card-block moduleCheckoutConfirmationDeliveryAddressCardBlock">
             <div class="mt-1"></div>
             <div class="mt-1"></div>
              <?php echo $shipping_method; ?>
            </div>
          </div>
          <div class="mt-1"></div>
        </span>
      <?php
    }
  }
  ?>
</div>
<div class="clearfix"></div>

