<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

require_once($CLICSHOPPING_Template->getTemplateFiles('breadcrumb'));

if ($CLICSHOPPING_MessageStack->exists('newsletter')) {
  echo $CLICSHOPPING_MessageStack->get('newsletter');
}
?>
<section class="account_newsletter" id="account_newsletter">
  <div class="contentContainer">
    <div class="contentText">
      <?php echo $CLICSHOPPING_Template->getBlocks('modules_account_customers'); ?>
      <div class="mt-1"></div>
    </div>
  </div>
</section>
