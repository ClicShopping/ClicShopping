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
    <div class="col-md-11">
      <div class="form-group row">
        <label for="inputSearch"
               class="col-2 col-form-label"><?php echo CLICSHOPPING::getDef('module_advanced_search_criteria_text'); ?></label>
        <div class="col-md-9">
          <?php echo HTML::inputField('keywords', null, 'required aria-required="true" id="inputSearch" aria-describedby="' . CLICSHOPPING::getDef('module_advanced_search_criteria_text') . '" placeholder="' . CLICSHOPPING::getDef('module_advanced_search_criteria_text') . '"'); ?>
        </div>
      </div>
    </div>
  </div>
</div>
