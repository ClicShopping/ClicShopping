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
  <div class="col-md-12">
    <div class="mt-1"></div>
    <div class="card moduleCheckoutConfirmationPaymentInformationCard">
      <div class="card-header moduleCheckoutConfirmationPaymentInformationCardHeader">
        <strong><?php echo CLICSHOPPING::getDef('module_checkout_confirmation_payment_information_heading_payment_information'); ?></strong>
      </div>
      <div class="card-block moduleCheckoutConfirmationPaymentInformationCardBlock">
        <div class="mt-1"></div>
        <table class="table moduleCheckoutConfirmationPaymentInformationCardTable">
          <?php echo $data; ?>
        </table>
      </div>
    </div>
  </div>
</div>
