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
    <div class="page-title"><h1><?php echo CLICSHOPPING::getDef('heading_title_password'); ?></h1></div>
    <div class="mt-1"></div>

    <div class="row">
      <div class="col-md-7">
        <div class="form-group row">
          <label for="CurrentPassword"
                 class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_password_current'); ?></label>
          <div class="col-md-8">
            <?php
            echo HTML::inputField('password_current', null, 'required aria-required="true" autofocus="autofocus" id="CurrentPassword" aria-describedby="' . CLICSHOPPING::getDef('entry_password_current') . '" placeholder="' . CLICSHOPPING::getDef('entry_password_current') . '"', 'password');
            if (!\is_null(CLICSHOPPING::getDef('entry_password_current_text'))) echo '<span class="form-text">' . CLICSHOPPING::getDef('entry_password_current_text') . '</span>';
            ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-7">
        <div class="form-group row">
          <label for="inputPasswordNew"
                 class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_password_new'); ?></label>
          <div class="col-md-8">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <span><?php echo HTML::inputField('password_new', null, 'required aria-required="true" autocomplete="off" id="input-password" aria-describedby="' . CLICSHOPPING::getDef('entry_password_new') . '" placeholder="' . CLICSHOPPING::getDef('entry_password_new') . '"  minlength="' . (int)ENTRY_PASSWORD_MIN_LENGTH . '"'); ?></span>
              <span><button type="button" id="button-generate" class="btn btn-primary btn-sm"><i
                    class="bi bi-arrow-clockwise"></i></button></span>
              <?php
              if (!\is_null(CLICSHOPPING::getDef('entry_password_current_text'))) echo '<span class="form-text">' . CLICSHOPPING::getDef('entry_password_current_text') . '</span>';
              ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-1"></div>

    <div class="row">
      <div class="col-md-7">
        <div class="form-group row">
          <label for="inputPasswordConfirmation"
                 class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_password_confirmation'); ?></label>
          <div class="col-md-8">
            <?php
            echo HTML::inputField('password_confirmation', null, 'required aria-required="true" autocomplete="off" id="inputPasswordConfirmation" aria-describedby="' . CLICSHOPPING::getDef('entry_password_confirmation') . '" placeholder="' . CLICSHOPPING::getDef('entry_password_confirmation') . '"  minlength="' . (int)ENTRY_PASSWORD_MIN_LENGTH . '"', 'password');
            if (!\is_null(CLICSHOPPING::getDef('entry_password_current_text'))) echo '<span class="form-text">' . CLICSHOPPING::getDef('entry_password_current_text') . '</span>';
            ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-12">
      <div class="control-group">
        <div>
          <div class="buttonSet">
            <span class="col-md-2"><?php echo $back_button; ?></span>
            <span class="col-md-2 float-end text-end"><?php echo $process_button; ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

<?php
echo $endform;