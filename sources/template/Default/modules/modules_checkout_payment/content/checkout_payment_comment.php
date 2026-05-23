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
  <div class="card">
    <div class="card-header moduleCheckoutPaymentCommentPageHeader">
      <span><h3><?php echo CLICSHOPPING::getDef('module_checkout_payment_comment_table_heading_comments'); ?></h3></span>
    </div>
    <div class="card-block">
      <div class="mt-1"></div>
      <div class="card-text moduleCheckoutPaymentAgreementText">
        <?php echo $comment_fields; ?>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
</div>