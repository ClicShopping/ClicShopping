<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

$CLICSHOPPING_OrdersStatus = Registry::get('OrdersStatus');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$Qstatus = $CLICSHOPPING_OrdersStatus->db->prepare('select *
                                                    from :table_orders_status
                                                    where language_id = :language_id
                                                    and orders_status_id = :orders_status_id
                                                    ');

$Qstatus->bindInt(':language_id', $CLICSHOPPING_Language->getId());
$Qstatus->bindInt(':orders_status_id', $_GET['oID']);
$Qstatus->execute();

$oInfo = new ObjectInfo($Qstatus->toArray());

$oID = HTML::sanitize($_GET['oID']);

$Qstatus = $CLICSHOPPING_OrdersStatus->db->get('orders', 'orders_status', ['orders_status' => (int)$oID], null, 1);

$remove_status = true;
if ($oID == DEFAULT_ORDERS_STATUS_ID) {
  $remove_status = false;
  $CLICSHOPPING_MessageStack->add($CLICSHOPPING_OrdersStatus->getDef('error_remove_default_order_status'), 'error');
} elseif ($Qstatus->fetch() !== false) {
  $remove_status = false;
  $CLICSHOPPING_MessageStack->add($CLICSHOPPING_OrdersStatus->getDef('error_status_used_in_orders'), 'error');
} else {
  $Qhistory = $CLICSHOPPING_OrdersStatus->db->get('orders_status_history', 'orders_status_id', ['orders_status_id' => (int)$oID], null, 1);

  if ($Qhistory->fetch() !== false) {
    $remove_status = false;
    $CLICSHOPPING_MessageStack->add($CLICSHOPPING_OrdersStatus->getDef('error_status_used_in_history'), 'error');
  }
}
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/order_status.gif', $CLICSHOPPING_OrdersStatus->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_OrdersStatus->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_OrdersStatus->getDef('text_info_heading_delete_orders_status'); ?></strong></div>
  <?php echo HTML::form('status', $CLICSHOPPING_OrdersStatus->link('OrdersStatus&DeleteConfirm&page=' . (int)$_GET['page'] . '&oID=' . $oInfo->orders_status_id)); ?>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo $CLICSHOPPING_OrdersStatus->getDef('text_info_delete_info'); ?><br/><br/></div>
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo '<strong>' . $oInfo->orders_status_name . '</strong>'; ?><br/><br/></div>
      <div class="col-md-12 text-center">
        <?php
        if ($remove_status) {
          ?>
          <span><br/><?php echo HTML::button($CLICSHOPPING_OrdersStatus->getDef('button_delete'), null, null, 'danger', null, 'sm') . ' </span><span>' . HTML::button($CLICSHOPPING_OrdersStatus->getDef('button_cancel'), null, $CLICSHOPPING_OrdersStatus->link('OrdersStatus&page=' . (int)$_GET['page'] . '&oID=' . $oInfo->orders_status_id), 'warning', null, 'sm'); ?></span>
          <?php
        } else {
          ?>
          <span><br/><?php echo HTML::button($CLICSHOPPING_OrdersStatus->getDef('button_cancel'), null, $CLICSHOPPING_OrdersStatus->link('OrdersStatus&page=' . (int)$_GET['page'] . '&oID=' . $oInfo->orders_status_id), 'warning', null, 'sm'); ?></span>
          <?php
        }
        ?>
      </div>
    </div>
  </div>
</div>