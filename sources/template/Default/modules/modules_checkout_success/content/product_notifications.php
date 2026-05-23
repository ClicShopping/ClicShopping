<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\html;

echo $form;
?>
  <div class="col-md-<?php echo $content_width; ?>">
    <div class="mt-1"></div>
    <div class="card card-success">
      <div
        class="card-header"><?php echo CLICSHOPPING::getDef('module_checkout_success_product_notifications_text_notify_products'); ?></div>
      <div class="card-block">
        <div class="mt-1"></div>
        <div><p class="checkoutSuccessProductsNotifications"><?php echo $products_notifications; ?></p></div>
      </div>
      <div class="mt-1"></div>
      <div class="control-group">
        <div>
          <div class="buttonSet">
            <span class="float-end"><label
                for="buttonUpdate"><?php echo HTML::button(CLICSHOPPING::getDef('button_update'), null, null, 'info'); ?></label></span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
<?php
echo $endform;

