<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

require_once($CLICSHOPPING_Template->getTemplateFiles('breadcrumb'));
?>
<section class="create_account_success" id="create_account_success">
  <div class="contentContainer">
    <div class="contentText">
      <div class="page-title"><h1><?php echo CLICSHOPPING::getDef('heading_title'); ?></h1></div>
      <div class="mt-1"></div>
      <div><?php echo $CLICSHOPPING_Template->getBlocks('modules_create_account'); ?></div>
    </div>
  </div>
</section>
