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

use ClicShopping\Apps\Configuration\OrdersStatusInvoice\Classes\ClicShoppingAdmin\Status;

$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_OrdersStatusInvoice = Registry::get('OrdersStatusInvoice');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$QordersStatusInvoice = $CLICSHOPPING_OrdersStatusInvoice->db->prepare('select  *
                                                                          from :table_orders_status_invoice
                                                                          where language_id = :language_id
                                                                          and orders_status_invoice_id = :orders_status_invoice_id
                                                                          ');

$QordersStatusInvoice->bindInt(':language_id', $CLICSHOPPING_Language->getId());
$QordersStatusInvoice->bindInt(':orders_status_invoice_id', $_GET['oID']);

$QordersStatusInvoice->execute();

$oInfo = new ObjectInfo($QordersStatusInvoice->toArray());

$orders_status_invoice_inputs_string = '';

$languages = $CLICSHOPPING_Language->getLanguages();
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
            class="col-md-7 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_OrdersStatusInvoice->getDef('heading_title'); ?></span>
          <span class="col-md-4 text-end">
<?php
echo HTML::form('status_orders_status_invoice', $CLICSHOPPING_OrdersStatusInvoice->link('OrdersStatusInvoice&Update&page=' . (int)$_GET['page'] . '&oID=' . $oInfo->orders_status_invoice_id));
echo HTML::button($CLICSHOPPING_OrdersStatusInvoice->getDef('button_update'), null, null, 'success') . ' ';
echo HTML::button($CLICSHOPPING_OrdersStatusInvoice->getDef('button_cancel'), null, $CLICSHOPPING_OrdersStatusInvoice->link('OrdersStatusInvoice'), 'warning');
?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_OrdersStatusInvoice->getDef('text_info_heading_edit_orders_status'); ?></strong>
  </div>
  <div class="adminformTitle">
    <div class="row">
      <div class="col-md-12">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_OrdersStatusInvoice->getDef('text_info_edit_intro'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_OrdersStatusInvoice->getDef('text_info_edit_intro'); ?></label>
        </div>
      </div>
    </div>
    <?php
    for ($i = 0, $n = \count($languages); $i < $n; $i++) {
      ?>
      <div class="row">
        <div class="col-md-5">
          <div class="form-group row">
            <label for="<?php echo $CLICSHOPPING_OrdersStatusInvoice->getDef('lang'); ?>"
                   class="col-5 col-form-label"><?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?></label>
            <div class="col-md-5">
              <?php echo HTML::inputField('orders_status_invoice_name[' . $languages[$i]['id'] . ']', Status::getOrdersStatusInvoiceName($oInfo->orders_status_invoice_id, $languages[$i]['id'])); ?>
            </div>
          </div>
        </div>
      </div>
      <?php
    }
    if (DEFAULT_PRODUCTS_QUANTITY_UNIT_STATUS_ID != $oInfo->orders_status_invoice_id) {
      ?>
      <div class="row">
        <div class="col-md-5">
          <div class="form-group row">
            <ul class="list-group-slider list-group-flush">
              <li class="list-group-item-slider">
                <label class="switch">
                  <?php echo HTML::checkboxField('default', null, null, 'class="success"'); ?>
                  <span class="slider"></span>
                </label>
              </li>
              <span
                class="text-slider"><?php echo $CLICSHOPPING_OrdersStatusInvoice->getDef('text_set_default'); ?></span>
            </ul>
          </div>
          /div>
        </div>
      </div>
      <?php
    }
    ?>
  </div>
  </form>
</div>