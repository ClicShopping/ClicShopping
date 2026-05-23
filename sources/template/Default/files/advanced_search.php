<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}

require_once($CLICSHOPPING_Template->getTemplateFiles('breadcrumb'));

echo HTML::form('advanced_search', CLICSHOPPING::link(null, 'Search&Q'), 'post', 'id="advanced_search" role="form"', ['session_id' => true]);
?>
<section class="advanced_search" id="advanced_search">
  <div class="contentContainer">
    <div class="contentText">
      <div class="page-title"><h1><?php echo CLICSHOPPING::getDef('heading_search_criteria'); ?></h1></div>
      <?php echo $CLICSHOPPING_Template->getBlocks('modules_advanced_search'); ?>
      <div class="mt-1"></div>
      <div class="control-group">
        <div>
          <div class="buttonSet">
            <span class="float-end"><label
                for="buttonSearch"><?php echo HTML::button(CLICSHOPPING::getDef('button_search'), null, null, 'success'); ?></label></span>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-1"></div>
  </div>
</section>
</form>
