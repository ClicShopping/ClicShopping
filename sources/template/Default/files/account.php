<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

require_once($CLICSHOPPING_Template->getTemplateFiles('breadcrumb'));

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}
?>
<section class="account" id="account">
  <div class="hr"></div>
  <div class="contentContainer">
    <div class="contentText">
      <div class="d-flex flex-wrap">
        <?php echo $CLICSHOPPING_Template->getBlocks('modules_account_customers'); ?>
      </div>
    </div>
  </div>
</section>