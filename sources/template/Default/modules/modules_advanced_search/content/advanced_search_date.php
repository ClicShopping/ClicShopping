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
        <label for="dfrom"
               class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('modules_advanced_search_date_entry_date_from'); ?></label>
        <div class="col-md-8">
          <?php echo HTML::inputField('dfrom', null, 'aria-describedby="' . CLICSHOPPING::getDef('modules_advanced_search_date_entry_date_from') . '" placeholder="' . CLICSHOPPING::getDef('modules_advanced_search_date_entry_date_from') . '"', 'date'); ?>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-7">
      <div class="form-group row">
        <label for="dto"
               class="col-4 col-form-label"><?php echo CLICSHOPPING::getDef('modules_advanced_search_date_entry_date_to'); ?></label>
        <div class="col-md-8">
          <?php echo HTML::inputField('dto', null, 'aria-describedby="' . CLICSHOPPING::getDef('modules_advanced_search_date_entry_date_to') . '" placeholder="' . CLICSHOPPING::getDef('modules_advanced_search_date_entry_date_to') . '"', 'date'); ?>
        </div>
      </div>
    </div>
  </div>
</div>
