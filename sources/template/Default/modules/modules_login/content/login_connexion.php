<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;

?>
<div class="col-md-<?php echo $content_width . ' ' . MODULE_LOGIN_CONNEXION_POSITION; ?>">
  <div class="card">
    <div class="card-header">
      <h2><?php echo CLICSHOPPING::getDef('module_login_connexion_heading_returning_customer'); ?></h2>
    </div>
    <div class="card-block">
      <div class="mt-1"></div>
      <div class="card-text">
        <?php
        echo $form;
        ?>
        <div class="row">
          <div class="col-md-12">
            <div class="form-group row">
              <label for="inputEmail"
                     class="col-6 col-form-label"><?php echo CLICSHOPPING::getDef('module_login_connexion_entry_email_address'); ?></label>
              <div class="col-md-6">
                <?php echo HTML::inputField('email_address', null, 'required aria-required="true" id="inputEmail" aria-describedby="' . CLICSHOPPING::getDef('module_login_connexion_entry_email_address') . '" placeholder="' . CLICSHOPPING::getDef('module_login_connexion_entry_email_address') . '"', 'email'); ?>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-12">
            <div class="form-group row">
              <label for="inputPassword"
                     class="col-6 col-form-label"><?php echo CLICSHOPPING::getDef('module_login_connexion_entry_password'); ?></label>
              <div class="col-md-6">
                <?php echo HTML::inputField('password', null, 'required aria-required="true" id="inputPassword" aria-describedby="' . CLICSHOPPING::getDef('module_login_connexion_entry_password') . '" placeholder="' . CLICSHOPPING::getDef('module_login_connexion_entry_password') . '"', 'password'); ?>
              </div>
            </div>
          </div>
        </div>
        <div
          class="col-md-4 passwordForgotten"><?php echo CLICSHOPPING::getDef('module_login_connexion_entry_password_text'); ?></div>
        <div
          class="col-md-4 passwordForgotten"><?php echo HTML::link(CLICSHOPPING::link(null, 'Account&PasswordForgotten'), CLICSHOPPING::getDef('module_login_connexion_text_password_forgotten')); ?></div>

        <div class="control-group">
          <div class="mt-1"></div>
          <div>
            <div
              class="buttonSet text-end"><?php echo HTML::button(CLICSHOPPING::getDef('button_login'), null, null, 'success'); ?></div>
          </div>
        </div>
        <?php
        echo $endform;
        ?>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
</div>

