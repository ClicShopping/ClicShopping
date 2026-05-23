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
  <div class="page-title moduleCheckoutConfirmationBillingAddressPageHeader">
    <h3><?php echo CLICSHOPPING::getDef('module_checkout_confirmation_billing_heading_billing_information'); ?></h3>
  </div>

  <span class="col-md-6 float-start" style="padding-right:0.5rem;">
    <div class="card moduleCheckoutConfirmationBillingAddressCard">
      <div class="card-header moduleCheckoutConfirmationBillingAddressHeader">
<?php
// Controle autorisation au client de modifier son adresse par defaut
if ($modify_address == 1) {
  ?>
  <div
    class="moduleCheckoutConfirmationBillingAddressDeliveryAddress"><strong><?php echo CLICSHOPPING::getDef('module_checkout_confirmation_billing_address_text_delivery_address'); ?></strong><?php echo $edit_payment_address; ?></div>
  <?php
} else {
  ?>
  <div
    class="moduleCheckoutConfirmationBillingAddressBillingTitle"><?php echo '<strong>' . CLICSHOPPING::getDef('module_checkout_confirmation_billing_address_text_billing_title') . '</strong>'; ?></div>
  <?php
}
?>
      </div>
      <div class="card-block moduleCheckoutConfirmationBillingAddressCardBlock">
        <div class="mt-1"></div>
        <div><?php echo $billing_address; ?></div>
      </div>
    </div>
    <div class="mt-1"></div>
  </span>

  <span class="col-md-6 float-end">
    <div class="card moduleCheckoutConfirmationBillingAddressCard">
      <div class="card-header moduleCheckoutConfirmationBillingAddressHeader">
        <div><strong><?php echo CLICSHOPPING::getDef('module_checkout_confirmation_billing_address_heading_payment_method'); ?></strong><?php echo $payment_method; ?></div>
      </div>
      <div class="card-block moduleCheckoutConfirmationBillingAddressCardBlock">
        <div class="mt-1"></div>
        <div><?php echo $type_payment; ?></div>
      </div>
    </div>
    <div class="mt-1"></div>
  </span>
</div>
<div class="clearfix"></div>