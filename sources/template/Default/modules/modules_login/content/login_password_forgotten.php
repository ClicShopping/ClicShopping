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
    <div><?php echo CLICSHOPPING::getDef('text_main'); ?></div>
    <div class="mt-1"></div>

    <div class="row">
      <div class="col-md-7">
        <div class="form-group row">
          <label for="inputEmail"
                 class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_email_address'); ?></label>
          <div class="col-md-6">
            <?php echo HTML::inputField('email_address', null, 'required aria-required="true" id="inputEmail" aria-describedby="' . CLICSHOPPING::getDef('module_login_connexion_entry_email_address') . '" placeholder="' . CLICSHOPPING::getDef('module_login_connexion_entry_email_address') . '"', 'email'); ?>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-1"></div>
    <div class="col-md-12">
      <div class="control-group">
        <div>
          <div class="buttonSet">
            <span
              class="col-md-2"><?php echo HTML::button(CLICSHOPPING::getDef('button_back'), null, CLICSHOPPING::link(null, 'Account&LogIn'), 'primary'); ?></span>
            <span
              class="col-md-2 float-end text-end"><?php echo HTML::button(CLICSHOPPING::getDef('button_continue'), null, null, 'success'); ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php
echo $endform;

