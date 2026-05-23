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

    <div class="page-title AccountCustomersGdrp">
      <h3><?php echo CLICSHOPPING::getDef('module_account_customers_gdpr_title'); ?></h3></div>

    <div class="mt-1"></div>
    <div><?php echo CLICSHOPPING::getDef('module_account_customers_gdpr_account_intro'); ?></div>
    <div class="mt-1"></div>
    <div>
      <blockquote>
        <div class="mt-1"></div>
        <div id="gdpr">
          <?php
          if (is_dir($source_folder)) {
            if (\is_array($files_get)) {
              foreach ($files_get as $value) {
                if (!empty($value['name'])) {
                  echo $CLICSHOPPING_Hooks->output('Account', $value['name'], null, 'display');
                }
              }
            }
          }
          ?>
        </div>
        <div>
          <?php echo CLICSHOPPING::getDef('module_account_customers_more_info') . ' ' . HTML::link(CLICSHOPPING::link(null, 'Info&Contact'), CLICSHOPPING::getDef('module_account_customers_contact_us')); ?>
        </div>
      </blockquote>
    </div>

    <div class="col-md-12">
      <div class="control-group">
        <div>
          <div class="buttonSet">
            <span
              class="col-md-2"><?php echo HTML::button(CLICSHOPPING::getDef('button_back'), null, CLICSHOPPING::link(null, 'Account&Main'), 'primary'); ?></span>
            <span
              class="col-md-2 float-end text-end"><?php echo HTML::button(CLICSHOPPING::getDef('button_continue'), null, null, 'success'); ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php
echo $endform;