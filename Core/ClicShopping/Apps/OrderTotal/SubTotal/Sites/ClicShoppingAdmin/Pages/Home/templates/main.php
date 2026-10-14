<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_SubTotal = Registry::get('SubTotal');

if ($CLICSHOPPING_MessageStack->exists('SubTotal')) {
  echo $CLICSHOPPING_MessageStack->get('SubTotal');
}
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/modules_modules_checkout_payment.gif', $CLICSHOPPING_SubTotal->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_SubTotal->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 mainTitle"><strong><?php echo $CLICSHOPPING_SubTotal->getDef('text_subtotal'); ?></strong></div>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>

      <div class="col-md-12">
        <div>
          <div class="col-md-12">
            <?php echo $CLICSHOPPING_SubTotal->getDef('text_intro'); ?>
          </div>
        </div>
        <div class="mt-1"></div>

        <div class="col-md-12 text-center">
          <div>
            <div class="col-md-12">
              <?php
              echo HTML::form('configure', CLICSHOPPING::link(null, 'A&OrderTotal\SubTotal&Configure'));
              echo HTML::button($CLICSHOPPING_SubTotal->getDef('button_configure'), null, null, 'primary');
              echo '</form>';
              ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
