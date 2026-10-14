<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

echo $form;
?>
  <div class="col-md-<?php echo $content_width; ?>">
    <div class="mt-1"></div>
    <div>
      <h3><?php echo CLICSHOPPING::getDef('my_newsletter_title'); ?></h3>
      <div class="contentText">
        <div class="mt-1"></div>
        <div class="checkbox">
          <label>
            <?php echo $newsletter_checkbox; ?>
            <strong><?php echo CLICSHOPPING::getDef('module_account_customers_newsletter'); ?></strong><br/><?php echo CLICSHOPPING::getDef('module_account_customers_newsletters_description'); ?>
          </label>
        </div>
      </div>
    </div>

    <div class="col-md-12">
      <div class="mt-1"></div>
      <div class="control-group">
        <div>
          <div class="buttonSet">
            <span class="col-md-2"><?php echo $button_back; ?></span>
            <span class="col-md-2 float-end text-end"><?php echo $button_process; ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php
echo $endform;