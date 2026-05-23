<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

?>
<div class="col-md-<?php echo $content_width; ?>" id="buttonSuccess1">
  <div class="modulesContactUsSuccess">
    <?php echo CLICSHOPPING::getDef('modules_contact_us_success_text_success', ['store_name' => STORE_NAME]); ?>
  </div>
  <div class="mt-1"></div>
  <div class="control-group">
    <div>
      <div class="buttonSet">
        <span class="float-end"><?php echo $button_process; ?></span>
      </div>
    </div>
  </div>
</div>