<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
?>
<div class="col-md-<?php echo $content_width; ?>">
  <div class="mt-1"></div>
  <div class="row">
    <div class="col-md-7">
      <div class="form-group row">
        <label for="entryManufacturers"
               class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('modules_advanced_search_manufacturers_entry_manufacturers'); ?></label>
        <div class="col-md-8">
          <?php echo HTML::selectMenu('manufacturersId', $CLICSHOPPING_ProductsCommon->getManufacturersDropDown(array(array('id' => '', 'text' => CLICSHOPPING::getDef('modules_advanced_search_manufacturers_text_all_manufacturers')))), NULL, 'id="entryManufacturers"'); ?>
        </div>
      </div>
    </div>
  </div>
</div>