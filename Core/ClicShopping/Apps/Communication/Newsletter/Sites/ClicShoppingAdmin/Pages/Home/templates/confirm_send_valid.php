<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Newsletter = Registry::get('Newsletter');
?>

<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/newsletters.gif', $CLICSHOPPING_Newsletter->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Newsletter->getDef('heading_title'); ?></span>
          <span
            class="col-md-6 text-end"><?php echo HTML::button($CLICSHOPPING_Newsletter->getDef('button_back'), null, $CLICSHOPPING_Newsletter->link('Newsletter'), 'primary'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <span class="text-center"><p
      style="color:#ff0000"><strong><?php echo $CLICSHOPPING_Newsletter->getDef('text_finished_sending_email'); ?></strong></p></span>
