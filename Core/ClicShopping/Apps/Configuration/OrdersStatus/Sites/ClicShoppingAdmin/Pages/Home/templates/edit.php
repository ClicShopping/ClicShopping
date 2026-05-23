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

use ClicShopping\Apps\Configuration\OrdersStatus\Classes\ClicShoppingAdmin\OrderStatusAdmin;

$CLICSHOPPING_OrdersStatus = Registry::get('OrdersStatus');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$orders_status_inputs_string = '';
$languages = $CLICSHOPPING_Language->getLanguages();

$Qstatus = $CLICSHOPPING_OrdersStatus->db->prepare('select *
                                                    from :table_orders_status
                                                    where language_id = :language_id
                                                    and orders_status_id = :orders_status_id
                                                    ');

$Qstatus->bindInt(':language_id', $CLICSHOPPING_Language->getId());
$Qstatus->bindInt(':orders_status_id', $_GET['oID']);
$Qstatus->execute();

$oInfo = new ObjectInfo($Qstatus->toArray())
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
            class="col-md-7 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_OrdersStatus->getDef('heading_title'); ?></span>
          <span class="col-md-4 text-end">
          <?php
            echo HTML::form('status_orders_status', $CLICSHOPPING_OrdersStatus->link('OrdersStatus&Update&page=' . (int)$_GET['page'] . '&oID=' . $oInfo->orders_status_id));
            echo HTML::button($CLICSHOPPING_OrdersStatus->getDef('button_update'), null, null, 'success') . ' ';
            echo HTML::button($CLICSHOPPING_OrdersStatus->getDef('button_cancel'), null, $CLICSHOPPING_OrdersStatus->link('OrdersStatus'), 'warning');
          ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_OrdersStatus->getDef('text_info_heading_edit_orders_status'); ?></strong></div>
  <div class="adminformTitle">

    <div class="row">
      <div class="col-md-12">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_OrdersStatus->getDef('text_info_edit_intro'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_OrdersStatus->getDef('text_info_edit_intro'); ?></label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-md-12">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_OrdersStatus->getDef('text_info_orders_status_name'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_OrdersStatus->getDef('text_info_orders_status_name'); ?></label>
        </div>
      </div>
    </div>
    <?php
    for ($i = 0, $n = \count($languages); $i < $n; $i++) {
      ?>
      <div class="row">
        <div class="col-md-5">
          <div class="form-group row">
            <label for="code"
                   class="col-2 col-form-label"><?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?></label>
            <div class="col-md-5">
              <?php echo HTML::inputField('orders_status_name[' . $languages[$i]['id'] . ']', OrderStatusAdmin::getOrdersStatusName($oInfo->orders_status_id, $languages[$i]['id'])); ?>
            </div>
          </div>
        </div>
      </div>
      <?php
    }
    ?>
    <div class="mt-1"></div>
    <div class="col-md-12" id="public_flag">
      <span class="col-md-3"></span>
      <ul class="list-group-slider list-group-flush">
        <li class="list-group-item-slider">
          <label class="switch">
            <?php echo HTML::checkboxField('public_flag', '1', $oInfo->public_flag, 'class="success"'); ?>
            <span class="slider"></span>
          </label>
        </li>
        <span class="text-slider"><?php echo $CLICSHOPPING_OrdersStatus->getDef('text_set_public_status'); ?></span>
      </ul>
    </div>

    <div class="col-md-12" id="downloads_flag">
      <span class="col-md-3"></span>
      <ul class="list-group-slider list-group-flush">
        <li class="list-group-item-slider">
          <label class="switch">
            <?php echo HTML::checkboxField('downloads_flag', '1', $oInfo->downloads_flag, 'class="success"'); ?>
            <span class="slider"></span>
          </label>
        </li>
        <span class="text-slider"><?php echo $CLICSHOPPING_OrdersStatus->getDef('text_set_downloads_status'); ?></span>
      </ul>
    </div>

    <div class="col-md-12" id="support_orders_flag">
      <span class="col-md-3"></span>
      <ul class="list-group-slider list-group-flush">
        <li class="list-group-item-slider">
          <label class="switch">
            <?php echo HTML::checkboxField('support_orders_flag', '1', $oInfo->support_orders_flag, 'class="success"'); ?>
            <span class="slider"></span>
          </label>
        </li>
        <span
          class="text-slider"><?php echo $CLICSHOPPING_OrdersStatus->getDef('text_set_support_orders_flag'); ?></span>
      </ul>
    </div>

    <div class="col-md-12" id="authorize_to_delete_order">
      <span class="col-md-3"></span>
      <ul class="list-group-slider list-group-flush">
        <li class="list-group-item-slider">
          <label class="switch">
            <?php echo HTML::checkboxField('authorize_to_delete_order', '1', $oInfo->authorize_to_delete_order, 'class="success"'); ?>
            <span class="slider"></span>
          </label>
        </li>
        <span
          class="text-slider"><?php echo $CLICSHOPPING_OrdersStatus->getDef('text_set_authorize_to_delete_status'); ?></span>
      </ul>
    </div>

    <?php
    if (DEFAULT_ORDERS_STATUS_ID != $oInfo->orders_status_id) {
      ?>
      <div class="col-md-12" id="default">
        <span class="col-md-3"></span>
        <ul class="list-group-slider list-group-flush">
          <li class="list-group-item-slider">
            <label class="switch">
              <?php echo HTML::checkboxField('default', null, null, 'class="success"'); ?>
              <span class="slider"></span>
            </label>
          </li>
          <span class="text-slider"><?php echo $CLICSHOPPING_OrdersStatus->getDef('text_set_default'); ?></span>
        </ul>
      </div>
      <?php
    }
    ?>
  </div>
  </form>
</div>