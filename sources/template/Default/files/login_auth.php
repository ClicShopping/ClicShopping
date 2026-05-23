<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}

require_once($CLICSHOPPING_Template->getTemplateFiles('breadcrumb'));
?>
<section class="LogInAuth" id="LogInAuth">
  <div class="contentText">
    <h4><?php echo CLICSHOPPING::getDef('text_heading_title_email_verification'); ?></h4>
    <div class="py-4"></div>
    <div class="col-md-12">
      <div class="row">
        <div class="col-md-2"></div>
        <div class="col-md-6 center-block">
          <?php echo HTML::form('login_auth', CLICSHOPPING::link(null, 'Account&LogInAuth&Process'), 'post', 'id="login_auth"', ['tokenize' => true]); ?>
          <div class="input-group">
            <?php
              echo HTML::inputField('email_code', null, 'required aria-required="true" autocomplete="off" autofocus placeholder="' . CLICSHOPPING::getDef('text_email_verification_code') . '"', 'password');
              echo HTML::button(CLICSHOPPING::getDef('button_verify'), null, null, 'primary');
            ?>
        </div>
        </form>
        <div class="mt-3"></div>
        <div class="col-md-12">
          <div class="row">
            <span class="col-md-6">
               <?php
                 echo HTML::form('resend', CLICSHOPPING::link(null, 'Account&LogInAuth&Resend'), 'post', 'id="resend"', ['tokenize' => true]);
                 echo HTML::button(CLICSHOPPING::getDef('button_resend_code'), null, null, 'success');
               ?>
              </form>
            </span>
            <span class="col-md-6 text-end">
             <?php echo HTML::button(CLICSHOPPING::getDef('button_back'), null, CLICSHOPPING::link(null, 'Account&LogInAuth&action=logoff'), 'warning'); ?>

            </span>
          </div>
        </div>
        <div class="py-4"></div>

        <div class="col-md-2"></div>
      </div>
      <div class="row">
        <div class="col-md-12">
          <div class="row">
            <div class="col-md-2"></div>
            <div class="col-md-8 center-block">
              <div class="row">
                <div class="alert alert-info" role="alert">
                  <?php echo CLICSHOPPING::getDef('text_Login_auth_introduction'); ?>
                </div>
              </div>
            </div>
            <div class="col-md-2"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>