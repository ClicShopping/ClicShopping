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
  <div class="text-center shoppingCartInformationSaveText">
    <?php echo CLICSHOPPING::getDef('module_shopping_cart_delay_save_cart_message_information'); ?>
  </div>
  <div class="mt-1"></div>
</div>