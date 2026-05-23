<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}

require_once($CLICSHOPPING_Template->getTemplateFiles('breadcrumb'));
?>
<section class="password_reset" id="password_reset">
  <div class="contentContainer">
    <div class="contentText">
      <div class="page-title modulesAccountCustomersPasswordResetPageHeader">
        <h1><?php echo CLICSHOPPING::getDef('heading_title'); ?></h1></div>
      <?php echo $CLICSHOPPING_Template->getBlocks('modules_login'); ?>
      <div class="mt-1"></div>
    </div>
  </div>
</section>
