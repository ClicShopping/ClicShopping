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
<section class="account_history_info" id="account_history_info">
  <div class="contentContainer">
    <div class="contentText">
      <div class="page-title"><h1><?php echo CLICSHOPPING::getDef('heading_title_history_information'); ?></h1></div>
      <?php echo $CLICSHOPPING_Template->getBlocks('modules_account_customers'); ?>
    </div>
    <div class="mt-1"></div>
  </div>
</section>