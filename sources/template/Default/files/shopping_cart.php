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

$CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');

require_once($CLICSHOPPING_Template->getTemplateFiles('breadcrumb'));

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}
?>
<section class="cart" id="cart">
  <div class="contentContainer">
    <div class="contentText">
      <div class="page-title">
        <h1><?php echo CLICSHOPPING::getDef('heading_title'); ?></h1>
      </div>
      <div class="d-flex flex-wrap">
        <div class="col-md-12">

          <?php
          if ($CLICSHOPPING_ShoppingCart->getCountContents() > 0) {
            echo $CLICSHOPPING_Template->getBlocks('modules_shopping_cart');
          } else {
            ?>
            <div class="clearfix"></div>
            <div class="mt-1"></div>
            <div class="col-md-12">
              <div class="alert alert-warning text-center" role="alert">
                <h3><?php echo CLICSHOPPING::getDef('text_cart_empty'); ?></h3></div>
              <div class="buttonSet float-end"><label
                  for="buttonContinue"><?php echo HTML::button(CLICSHOPPING::getDef('button_continue'), '', CLICSHOPPING::link(), 'success'); ?></label>
              </div>
            </div>
            <?php
          }
          ?>
        </div>
      </div>
    </div>
  </div>
</section>