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

$CLICSHOPPING_Template = Registry::get('Template');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}

require_once($CLICSHOPPING_Template->getTemplateFiles('breadcrumb'));

echo HTML::form('checkout_shipping', CLICSHOPPING::link(null, 'Checkout&Shipping&Process'), 'post', 'role="form" id="checkout_shipping"', ['tokenize' => true, 'action' => 'process']);
?>
<section class="checkout_shipping" id="checkout_shipping">
  <div class="contentContainer">
    <div class="contentText">
      <div class="mt-1"></div>
      <div class="page-title"><h1><?php echo CLICSHOPPING::getDef('heading_title_checkout_shipping'); ?></h1></div>
      <div>
        <?php echo $CLICSHOPPING_Template->getBlocks('modules_checkout_shipping'); ?>
      </div>
      <div class="mt-1"></div>
    </div>
  </div>
</section>
</form>