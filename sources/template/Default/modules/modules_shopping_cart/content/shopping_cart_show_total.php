<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

?>
<div class="col-md-<?php echo $content_width; ?> <?php echo $position; ?>">
  <div class="mt-1"></div>
  <div
    class="shoppingCartSubTotal text-end"><?php echo CLICSHOPPING::getDef('module_shopping_cart_show_total_sub_total') . ' ' . $sub_total; ?></div>
</div>