<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}

require_once($CLICSHOPPING_Template->getTemplateFiles('breadcrumb'));
?>
<section class="contact_success" id="contact_success">
  <div class="contentContainer">
    <div class="contentText">
      <?php echo $CLICSHOPPING_Template->getBlocks('modules_contact_us'); ?>
      <div class="mt-1"></div>
    </div>
  </div>
</section>
