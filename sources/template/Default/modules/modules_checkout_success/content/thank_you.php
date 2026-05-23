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
  <div class=""><?php echo CLICSHOPPING::getDef('module_checkout_success_text_success'); ?></div>
  <div class="mt-1"></div>
  <div class="col-md-12">
    <div><?php echo CLICSHOPPING::getDef('module_checkout_success_text_thanks_for_shopping', ['store_name' => STORE_NAME]); ?></div>
    <div class="mt-1"></div>
    <div class="hr"></div>
    <div class="m-4 ClicShoppingCheckoutSuccessText">
      <span><?php echo $text_info; ?></span>
      <div class="hr"></div>
      <span><?php echo $contact; ?></span>
    </div>
    <div class="mt-1"></div>
  </div>

