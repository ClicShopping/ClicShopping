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
  <div class="col-md-<?php echo $content_width; ?>" id="buttonProcess1">
    <div class="mt-1"></div>
    <div class="control-group">
      <div class="buttonSet">
        <div class="text-end">
          <?php echo HTML::button(CLICSHOPPING::getDef('button_continue'), null, null, 'primary', null, null, null, '"submit"'); ?>
        </div>
      </div>
    </div>
  </div>
<?php echo $endform; ?>