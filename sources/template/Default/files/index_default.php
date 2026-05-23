<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

require_once($CLICSHOPPING_Template->getTemplateHeaderFooter('header'));
?>
  <section class="index" id="index">
    <div class="contentContainer">
      <div class="contentText">
        <?php echo $CLICSHOPPING_Template->getBlocks('modules_front_page'); ?>
      </div>
    </div>
  </section>
<?php
require_once($CLICSHOPPING_Template->getTemplateHeaderFooter('footer'));