<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

?>
<div class="<?php echo $text_position; ?> col-md-<?php echo $content_width; ?>">
  <div class="mt-1"></div>
  <div class="text-center"><?php echo $reviews_image; ?></div>
  <div class="text-center">
    <div><?php echo $products_name; ?></div>
    <div><?php echo CLICSHOPPING::getDef('text_price') . ' ' . $products_price; ?></div>
  </div>
</div>