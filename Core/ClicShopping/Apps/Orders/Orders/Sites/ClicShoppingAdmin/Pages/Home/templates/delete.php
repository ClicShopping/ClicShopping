<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Orders = Registry::get('Orders');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}

$order_id = HTML::sanitize($_GET['oID']);
?>

<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/orders.gif', $CLICSHOPPING_Orders->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Orders->getDef('heading_title') . ' #' . (int)$order_id; ?></span>
          <span
            class="col-md-9 text-end"><?php echo HTML::button($CLICSHOPPING_Orders->getDef('button_back'), null, $CLICSHOPPING_Orders->link('Orders'), 'primary'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_Orders->getDef('text_info_heading_delete_order'); ?></strong></div>
  <?php echo HTML::form('orders', $CLICSHOPPING_Orders->link('Orders&DeleteConfirm&oID=' . (int)$order_id)); ?>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo $CLICSHOPPING_Orders->getDef('text_info_delete_info'); ?>
        <div class="mt-1"></div>
        <div class="col-md-12">
          <br/><?php echo HTML::checkboxField('restock') . ' ' . $CLICSHOPPING_Orders->getDef('text_info_restock_product_quantity'); ?>
          <br/><br/></div>
        <div class="mt-1"></div>
        <div class="col-md-12 text-center">
          <span><br/><?php echo HTML::button($CLICSHOPPING_Orders->getDef('button_delete'), null, null, 'danger', null, 'sm') . '&nbsp;</span><span>' . HTML::button($CLICSHOPPING_Orders->getDef('button_cancel'), null, $CLICSHOPPING_Orders->link('Edit&oID=' . $order_id), 'warning', null, 'sm'); ?></span>
        </div>
      </div>
    </div>
    </form>
  </div>
</div>