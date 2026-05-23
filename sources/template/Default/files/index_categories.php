<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

require_once($CLICSHOPPING_Template->getTemplateHeaderFooter('header'));
require_once($CLICSHOPPING_Template->getTemplateFiles('breadcrumb'));
?>
  <section class="index_categories" id="index_categories">
    <div class="contentContainer">
      <div class="contentText">
        <?php echo $CLICSHOPPING_Template->getBlocks('modules_index_categories'); ?>
      </div>
    </div>
  </section>
<?php
require_once($CLICSHOPPING_Template->getTemplateHeaderFooter('footer'));
