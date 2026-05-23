<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;

echo $form;
?>
  <div class="col-md-<?php echo $content_width; ?>">
    <div class="mt-1"></div>
    <?php echo CLICSHOPPING::getDef('my_notifications_description'); ?>
    <div class="mt-1"></div>
    <div class="mt-1"></div>
    <div class="mt-1"></div>
    <div class="hr"></div>
    <div class="mt-1"></div>
    <div class="mt-1"></div>
    <div class="mt-1"></div>
    <h3><?php echo CLICSHOPPING::getDef('global_notifications_title'); ?></h3>
    <div class="mt-1"></div>
    <div class="mt-1"></div>
    <div class="mt-1"></div>
    <div>
        <span class="checkbox col-md-1 text-end">
          <label><?php echo $checkbox_notifications; ?> </label>
        </span>
      <span class="col-md-6"><strong><?php echo CLICSHOPPING::getDef('global_notifications_title'); ?></strong></span>
    </div>
    <?php
    // ----------------------
    // --- Notification   -----
    // ----------------------
    if ($global_notification != 1) {
      ?>
      <div class="mt-1"></div>
      <div class="mt-1"></div>
      <div class="mt-1"></div>
      <div class="hr"></div>
      <div class="mt-1"></div>
      <div class="mt-1"></div>
      <div class="mt-1"></div>
      <h3><?php echo CLICSHOPPING::getDef('notifications_title'); ?></h3>
      <?php
      if ($row_count > 0 && !\is_null($Qproducts)) {
        ?>
        <div><?php echo CLICSHOPPING::getDef('notifications_description'); ?></div>
        <div class="clearfix"></div>
        <?php
        while ($Qproducts->fetch()) {
          ?>
          <div class="row">
            <div class="col-md-7">
              <div class="form-group row">
                <label for="Products"
                       class="col-4 col-form-label"><?php echo HTML::checkboxField('products[' . $counter . ']', $Qproducts->valueInt('products_id'), true); ?></label>
                <div class="col-md-5">
                  <strong><?php echo $Qproducts->value('products_name') ?></strong>
                </div>
              </div>
            </div>
          </div>
          <?php
          $counter++;
        }
      } else {
        ?>
        <div class="mt-1"></div>
        <div class="mt-1"></div>
        <div class="mt-1"></div>
        <div class="alert alert-warning" role="alert">
          <?php echo CLICSHOPPING::getDef('notifications_non_existing'); ?>
        </div>
        <?php
      }
    }
    // ----------------------
    // --- button   -----
    // ----------------------
    ?>
    <div class="mt-1"></div>
    <div class="col-md-12">
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