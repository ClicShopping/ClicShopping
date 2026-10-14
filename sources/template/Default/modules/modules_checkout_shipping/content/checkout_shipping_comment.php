<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

?>
<div class="col-md-<?php echo $content_width; ?>">
  <div class="mt-1"></div>
  <div>
    <label for="inputComments" class="moduleCheckoutShippingCommentLabel">
      <h3><?php echo CLICSHOPPING::getDef('module_checkout_shipping_comment_table_heading_comments'); ?></h3>
    </label>
    <div class="col-md-12"><?php echo $comment_fields; ?></div>
  </div>
</div>
