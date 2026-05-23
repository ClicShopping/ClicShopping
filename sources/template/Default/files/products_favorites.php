<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */
require_once($CLICSHOPPING_Template->getTemplateFiles('breadcrumb'));
?>
<section class="favorites" id="favorites">
  <div class="contentContainer">
    <div class="contentText">
      <?php echo $CLICSHOPPING_Template->getBlocks('modules_products_favorites'); ?>
    </div>
  </div>
</section>
