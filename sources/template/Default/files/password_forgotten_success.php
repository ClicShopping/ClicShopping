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

if (isset($_GET['reset']) === true && isset($_GET['PasswordForgotten'])) {
  ?>
  <section class="password_forgotten" id="password_forgotten">
    <div class="contentContainer">
      <div class="mt-1"></div>
      <div class="contentText">
        <?php echo CLICSHOPPING::getDef('text_password_reset_initiated'); ?>
        <div class="mt-1"></div>
        <div class="control-group">
          <div>
            <div class="buttonSet">
              <div class="buttonSet">
                <span class="text-end"><label
                    for="buttonContinue"><?php echo HTML::button(CLICSHOPPING::getDef('button_continue'), null, CLICSHOPPING::redirect(null, 'Account&LogIn'), 'success'); ?></label></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-1"></div>
  </section>
  <?php
} else {
  CLICSHOPPING::redirect();
}