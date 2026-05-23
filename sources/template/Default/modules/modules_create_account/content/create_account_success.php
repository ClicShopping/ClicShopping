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
<div class="col-md-<?php echo $content_width; ?>" id="AccountSuccess">
  <div class="page-title modulesCreateAccountSuccess">
    <h3><?php echo CLICSHOPPING::getDef('module_create_account_success_text_account_created'); ?></h3></div>
  <div class="mt-1"></div>
  <div>
    <div class="control-group">
      <div>
        <div class="buttonSet">
          <span
            class="float-end"><?php echo HTML::button(CLICSHOPPING::getDef('button_continue'), null, $origin_href, 'success'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
</div>
