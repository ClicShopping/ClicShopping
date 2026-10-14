<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

require_once($CLICSHOPPING_Template->getTemplateFiles('breadcrumb'));
?>
<section class="index" id="index">
  <div class="contentContainer">
    <div class="contentText">
      <div class="d-flex flex-wrap">
        <?php echo $CLICSHOPPING_Template->getBlocks('modules_sitemap'); ?>
      </div>
      <div class="mt-1"></div>
    </div>
  </div>
</section>
