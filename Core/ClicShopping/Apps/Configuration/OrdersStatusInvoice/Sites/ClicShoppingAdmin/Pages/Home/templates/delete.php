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

$CLICSHOPPING_OrdersStatusInvoice = Registry::get('OrdersStatusInvoice');
$CLICSHOPPING_Language = Registry::get('Language');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$oID = HTML::sanitize($_GET['oID']);
$Qstatus = $CLICSHOPPING_OrdersStatusInvoice->db->get('configuration', 'configuration_value', ['configuration_key' => 'DEFAULT_ORDERS_STATUS_INVOICE_ID']);

if ($Qstatus->value('configuration_value') == $oID) {
  $CLICSHOPPING_OrdersStatusInvoice->db->save('configuration', [
    'configuration_value' => ''
  ], [
      'configuration_key' => 'DEFAULT_ORDERS_STATUS_INVOICE_ID'
    ]
  );
}

$QstatusInvoice = $CLICSHOPPING_OrdersStatusInvoice->db->get('orders', 'orders_status', ['orders_status_invoice' => (int)$oID], null, 1);

$remove_status = true;

if ($oID == DEFAULT_ORDERS_STATUS_INVOICE_ID) {
  $remove_status = false;
  $CLICSHOPPING_MessageStack->add($CLICSHOPPING_OrdersStatusInvoice->getDef('error_remove_default_order_status'), 'error');
} elseif ($Qstatus->fetch() !== false) {
  $remove_status = false;
  $CLICSHOPPING_MessageStack->add($CLICSHOPPING_OrdersStatusInvoice->getDef('error_status_used_in_orders'), 'error');
} else {

  $Qhistory = $CLICSHOPPING_OrdersStatusInvoice->db->get('orders_status_history', 'orders_status_invoice_id', ['orders_status_invoice_id' => (int)$oID], null, 1);

  if ($Qhistory->fetch() !== false) {
    $remove_status = false;
    $CLICSHOPPING_MessageStack->add($CLICSHOPPING_OrdersStatusInvoice->getDef('error_status_used_in_hsitory'), 'error');
  }
}

$QordersStatusInvoice = $CLICSHOPPING_OrdersStatusInvoice->db->prepare('select  *
                                                                  from :table_orders_status_invoice
                                                                  where language_id = :language_id
                                                                  and orders_status_invoice_id = :orders_status_invoice_id
                                                                  ');

$QordersStatusInvoice->bindInt(':language_id', $CLICSHOPPING_Language->getId());
$QordersStatusInvoice->bindInt(':orders_status_invoice_id', $_GET['oID']);

$QordersStatusInvoice->execute();

$oInfo = new ObjectInfo($QordersStatusInvoice->toArray());


?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/configuration_26.gif', $CLICSHOPPING_OrdersStatusInvoice->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_OrdersStatusInvoice->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <?php echo HTML::form('status_invoice', $CLICSHOPPING_OrdersStatusInvoice->link('OrdersStatusInvoice&DeleteConfirm&page=' . (int)$_GET['page'] . '&oID=' . $oInfo->orders_status_invoice_id)); ?>
  <div class="mt-1"></div>
  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_OrdersStatusInvoice->getDef('text_info_heading_products_unit_quantity_delete'); ?></strong>
  </div>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo $CLICSHOPPING_OrdersStatusInvoice->getDef('text_info_delete_info'); ?><br/><br/>
      </div>
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo '<strong>' . $oInfo->orders_status_invoice_name . '</strong>'; ?><br/><br/>
      </div>
      <div class="col-md-12 text-center">
        <?php
        if ($remove_status) {
          ?>
          <span><br/><?php echo HTML::button($CLICSHOPPING_OrdersStatusInvoice->getDef('button_delete'), null, null, 'danger', null, 'sm') . ' </span><span>' . HTML::button($CLICSHOPPING_OrdersStatusInvoice->getDef('button_cancel'), null, $CLICSHOPPING_OrdersStatusInvoice->link('OrdersStatusInvoice&page=' . (int)$_GET['page']), 'warning', null, 'sm'); ?></span>
          <?php
        } else {
          ?>
          <span><br/><?php echo HTML::button($CLICSHOPPING_OrdersStatusInvoice->getDef('button_cancel'), null, $CLICSHOPPING_OrdersStatusInvoice->link('OrdersStatusInvoice&page=' . (int)$_GET['page'] . '&oID=' . $oInfo->orders_status_invoice_id), 'warning', null, 'sm'); ?></span>
          <?php
        }
        ?>
      </div>
    </div>
  </div>
  </form>
</div>