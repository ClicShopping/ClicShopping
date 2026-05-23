<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;

echo $form
?>
<div class="col-md-<?php echo $content_width; ?>">
  <?php
  if ($CLICSHOPPING_MessageStack->exists('main')) {
    echo $CLICSHOPPING_MessageStack->get('main');
  }
  ?>
  <div class="mt-1"></div>
  <div class="card">
    <div class="card-header">
      <span class="alert-warning float-end" role="alert"><?php echo CLICSHOPPING::getDef('form_required'); ?></span>
      <span
        class="modulesCreateAccountRegistrationPageHeader"><h3><?php echo CLICSHOPPING::getDef('category_personal'); ?></h3></span>
    </div>
    <div class="card-block">
      <div class="mt-1"></div>
      <div class="card-text">
        <div class="row" id="RowContentAccountRegistration1">
          <div class="col-md-12">
            <div class="form-group row">
              <label for="InputFirstName"
                     class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_first_name'); ?></label>
              <div class="col-sm-6 col-md-6">
                <?php echo HTML::inputField('firstname', null, 'required aria-required="true" id="InputFirstName" autocomplete="name" aria-describedby="' . CLICSHOPPING::getDef('entry_first_name') . '" placeholder="' . CLICSHOPPING::getDef('entry_first_name') . '" minlength="' . ENTRY_FIRST_NAME_MIN_LENGTH . '"'); ?>
              </div>
            </div>
          </div>
        </div>

        <div class="row" id="RowContentAccountRegistration2">
          <div class="col-md-12">
            <div class="form-group row">
              <label for="InputLastName"
                     class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_last_name'); ?></label>
              <div class="col-sm-6 col-md-6">
                <?php echo HTML::inputField('lastname', null, 'required aria-required="true" id="InputLastName" autocomplete="name" aria-describedby="' . CLICSHOPPING::getDef('entry_last_name') . '" placeholder="' . CLICSHOPPING::getDef('entry_last_name') . '" minlength="' . ENTRY_LAST_NAME_MIN_LENGTH . '"'); ?>
              </div>
            </div>
          </div>
        </div>
        <div class="mt-1"></div>
        <?php
        if (ACCOUNT_DOB == 'true') {
          ?>
          <div class="mt-1"></div>

          <div class="row" id="RowContentAccountRegistration3">
            <div class="col-md-12">
              <div class="form-group row">
                <label for="dob"
                       class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_date_of_birth'); ?></label>
                <div class="col-sm-6 col-md-6">
                  <?php echo HTML::inputField('dob', null, 'rel="txtTooltipDob" title="' . CLICSHOPPING::getDef('text_create_account_dob_dgrp') . '" data-bs-toggle="tooltip" data-placement="right" required aria-required="true" aria-describedby="' . CLICSHOPPING::getDef('entry_date_of_birth') . '" placeholder="' . CLICSHOPPING::getDef('entry_date_of_birth') . '" minlength="' . ENTRY_DOB_MIN_LENGTH . '"', 'date'); ?>
                </div>
              </div>
            </div>
          </div>
          <?php
        }
        ?>
        <div class="row" id="RowContentAccountRegistration4">
          <div class="col-md-12">
            <div class="form-group row">
              <label for="InputEmail"
                     class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_email_address'); ?></label>
              <div class="col-sm-6 col-md-6">
                <?php echo HTML::inputField('email_address', null, 'rel="txtTooltipEmailAddress" autocomplete="email" title="' . CLICSHOPPING::getDef('entry_email_address') . '" data-bs-toggle="tooltip" data-placement="right" required aria-required="true" id="InputEmail" aria-describedby="' . CLICSHOPPING::getDef('entry_email_address') . '" placeholder="' . CLICSHOPPING::getDef('entry_email_address') . '"', 'email'); ?>
              </div>
            </div>
          </div>
        </div>

        <div class="row" id="RowContentAccountRegistration5">
          <div class="col-md-12">
            <div class="form-group row">
              <label for="InputEmailConfirm"
                     class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_email_address_confirmation'); ?></label>
              <div class="col-sm-6 col-md-6">
                <?php echo HTML::inputField('email_address_confirm', null, 'required aria-required="true" id="InputEmailConfirm" autocomplete="email" aria-describedby="' . CLICSHOPPING::getDef('entry_email_address_confirmation') . '" placeholder="' . CLICSHOPPING::getDef('entry_email_address_confirmation') . '"', 'email'); ?>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <?php
  // ----------------------
  // Newsletter Information
  // ----------------------
  ?>
  <div class="card">
    <div class="card-header">
      <span
        class="modulesCreateAccountRegistrationCategoryOptionsPageHeader"><h3><?php echo CLICSHOPPING::getDef('entry_newsletter'); ?></h3></span>
    </div>
    <div class="card-block">
      <div class="mt-1"></div>
      <div class="card-text">
        <div class="row" id="RowContentAccountRegistration6">
          <div class="col-md-12">
            <div class="form-group row">
              <label for="Inputnewsletter"
                     class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_newsletter'); ?></label>
              <div class="col-sm-6 col-md-6">
                <?php echo HTML::checkboxField('newsletter', 1, false, 'id="Inputnewsletter" aria-label="' . CLICSHOPPING::getDef('entry_newsletter') . '"'); ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <?php
  // ----------------------
  // Password
  // ----------------------
  ?>
  <div class="card">
    <div class="card-header">
      <span class="alert-warning float-end" role="alert"><?php echo CLICSHOPPING::getDef('form_required'); ?></span>
      <span
        class="modulesCreateAccountRegistrationPasswordPageHeader"><h3><?php echo CLICSHOPPING::getDef('category_password'); ?></h3></span>
    </div>
    <div class="card-block">
      <div class="mt-1"></div>
      <div class="card-text">
        <div class="row" id="RowContentAccountRegistration7">
          <div class="col-md-12">
            <div class="form-group row">
              <label for="inputPassword"
                     class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_password'); ?></label>
              <div class="col-sm-6 col-md-6">
                <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
                  <span><?php echo HTML::inputField('password', null, 'required aria-required="true" autocomplete="off" id="input-password" aria-describedby="' . CLICSHOPPING::getDef('entry_password') . '" placeholder="' . CLICSHOPPING::getDef('entry_password') . '" minlength="' . (int)ENTRY_PASSWORD_MIN_LENGTH . '"'); ?></span>
                  <span><button type="button" id="button-generate" class="btn btn-primary btn-sm"><i
                        class="bi bi-arrow-clockwise"></i></button></span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row" id="RowContentAccountRegistration8">
          <div class="col-md-12">
            <div class="form-group row">
              <label for="inputPasswordConfirmation"
                     class="col-sm-6 col-md-4 col-form-label"><?php echo CLICSHOPPING::getDef('entry_password_confirmation'); ?></label>
              <div class="col-sm-6 col-md-6">
                <?php echo HTML::inputField('confirmation', null, 'required aria-required="true" id="inputPasswordConfirmation" aria-describedby="' . CLICSHOPPING::getDef('entry_password_confirmation') . '" placeholder="' . CLICSHOPPING::getDef('entry_password_confirmation') . '" minlength="' . (int)ENTRY_PASSWORD_MIN_LENGTH . '"', 'password'); ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
</div>


