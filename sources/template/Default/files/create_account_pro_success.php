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
<section class="create_account_pro_success" id="create_account_pro_success">
  <div class="contentContainer">
    <div class="contentText">
      <div class="page-title">
        <h1>
          <?php
          if (MEMBER == 'false') {
            echo CLICSHOPPING::getDef('heading_title_account_created');
          } else {
            echo CLICSHOPPING::getDef('heading_title_account_wait');
          }
          ?>
        </h1></div>
      <div class="mt-1"></div>
      <div><?php echo $CLICSHOPPING_Template->getBlocks('modules_create_account_pro'); ?></div>
    </div>
  </div>
</section>