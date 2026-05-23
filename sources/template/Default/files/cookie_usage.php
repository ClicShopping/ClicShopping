<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;

require_once($CLICSHOPPING_Template->getTemplateFiles('breadcrumb'));
?>
<div class="mt-1"></div>
<section class="cookies" id="cookies">
  <div class="contentContainer">
    <div class="contentText">
      <div class="page-title cookieUsageHeader"><h1><?php echo CLICSHOPPING::getDef('box_information_heading'); ?></h1>
      </div>
      <div class="mt-1"></div>
      <div><?php echo CLICSHOPPING::getDef('box_information'); ?></div>
      <div class="mt-1"></div>
      <div class="card card-danger">
        <div class="card-block">
          <div class="mt-1"></div>
          <?php echo CLICSHOPPING::getDef('text_information'); ?>
        </div>
      </div>
      <div class="mt-1"></div>
      <div class="control-group">
        <div>
          <div class="buttonSet">
            <span class="float-end"><label
                for="buttonContinue"><?php echo HTML::button(CLICSHOPPING::getDef('button_continue'), null, CLICSHOPPING::link(), 'success'); ?></label></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>