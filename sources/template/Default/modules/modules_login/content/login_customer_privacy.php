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
  <div class="ModuleLoginCustomerPrivacy">
    <div><?php echo CLICSHOPPING::getDef('text_customer_privacy', ['store_name' => STORE_NAME]); ?></div>
  </div>
</div>
