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
<div class="col-md-<?php echo $content_width . ' ' . MODULE_LOGIN_MODE_B2B_POSITION; ?>">
  <div class="card">
    <div class="card-header">
      <h2><span><?php echo CLICSHOPPING::getDef('heading_title_b2b'); ?></span></h2>
    </div>
    <div class="card-block">
      <div class="mt-1"></div>
      <div class="card-text">
        <div class="mainLogin"><?php echo CLICSHOPPING::getDef('text_intro_b2b'); ?></div>
        <div class="text-rmd-ight">
          <div class="control-group">
            <div class="mt-1"></div>
            <div>
              <div
                class="buttonSet text-end"><?php echo HTML::button(CLICSHOPPING::getDef('button_retailers'), null, CLICSHOPPING::link(null, 'Account&CreatePro'), 'primary'); ?></div>
            </div>
            <div class="mt-1"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
