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
<div class="col-md-<?php echo $content_width; ?>">
  <div class="mt-1"></div>
  <div class="row">
    <div class="col-md-7">
      <div class="form-group row">
        <label for="InputCategory"
               class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('modules_advanced_search_category_entry_categories'); ?></label>
        <div class="col-md-8">
          <?php echo HTML::selectMenu('categories_id', $CLICSHOPPING_Category->getCategories(array(array('id' => '', 'text' => CLICSHOPPING::getDef('modules_advanced_search_category_entry_categories')))), NULL, 'id="InputCategory"'); ?>
        </div>
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col-md-7">
      <div class="form-group row">
        <label for="InputCategory"
               class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('modules_advanced_search_category_entry_include_subcategories'); ?></label>
        <div class="col-md-2">
          <ul class="list-group list-group-flush">
            <li class="list-group-item-slider">
              <div class="mt-1"></div>
              <label class="switch">
                <?php echo HTML::checkboxField('inc_subcat', 1, true, ' class="success" id="inc_subcat" aria-label="' . CLICSHOPPING::getDef('modules_advanced_search_category_entry_include_subcategories') . '"'); ?>
                <span class="slider"></span>
              </label>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>