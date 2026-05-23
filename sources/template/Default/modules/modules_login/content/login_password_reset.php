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
          <label for="inputPassword"
                 class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_password'); ?></label>
          <div class="col-md-6">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <span><?php echo HTML::inputField('password', null, 'required aria-required="true" autocomplete="off" id="input-password" aria-describedby="' . CLICSHOPPING::getDef('module_login_connexion_entry_password') . '" placeholder="' . CLICSHOPPING::getDef('module_login_connexion_entry_password') . '"'); ?></span>
              <span><button type="button" id="button-generate" class="btn btn-primary btn-sm"><i
                    class="bi bi-arrow-clockwise"></i></button></span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-7">
        <div class="form-group row">
          <label for="inputConfirmation"
                 class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_password_confirmation'); ?></label>
          <div class="col-md-6">
            <?php echo HTML::inputField('confirmation', null, 'required aria-required="true" id="inputConfirmation"  placeholder="' . CLICSHOPPING::getDef('entry_password_confirmation') . '"', 'password'); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-1"></div>
    <div class="control-group">
      <div>
        <div class="buttonSet">
          <span
            class="float-end"><?php echo HTML::button(CLICSHOPPING::getDef('button_continue'), null, null, 'success'); ?></span>
        </div>
      </div>
    </div>
  </div>
<?php
echo $endform;