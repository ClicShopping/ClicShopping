<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_ProductsQuantityUnit = Registry::get('ProductsQuantityUnit');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$oID = HTML::sanitize($_GET['oID']);

$Qstatus = $CLICSHOPPING_ProductsQuantityUnit->db->prepare('select count(*) as count
                                                             from :table_products
                                                             where products_quantity_unit_id = :products_quantity_unit_id
                                                            ');
$Qstatus->bindValue(':products_quantity_unit_id', (int)$oID);
$Qstatus->execute();

$status = $Qstatus->fetch();

$remove_status = true;

if ($oID == DEFAULT_PRODUCTS_QUANTITY_UNIT_STATUS_ID) {
  $remove_status = false;
  $CLICSHOPPING_MessageStack->add($CLICSHOPPING_ProductsQuantityUnit->getDef('error_remove_default_products_unit_quantity_status'), 'error');
} elseif ($status['count'] > 0) {
  $remove_status = false;
  $CLICSHOPPING_MessageStack->add($CLICSHOPPING_ProductsQuantityUnit->getDef('error_status_used_in_products_unit_quantity'), 'error');
}

$QproductsQquantityUnit = $CLICSHOPPING_ProductsQuantityUnit->db->prepare('select  *
                                                                            from :table_products_quantity_unit
                                                                            where language_id = :language_id
                                                                            and products_quantity_unit_id = :products_quantity_unit_id
                                                                            order by products_quantity_unit_id
                                                                          ');

$QproductsQquantityUnit->bindInt(':language_id', $CLICSHOPPING_Language->getId());
$QproductsQquantityUnit->bindInt(':products_quantity_unit_id', $_GET['oID']);

$QproductsQquantityUnit->execute();

$oInfo = new ObjectInfo($QproductsQquantityUnit->toArray());

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/products_unit.png', $CLICSHOPPING_ProductsQuantityUnit->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ProductsQuantityUnit->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <?php echo HTML::form('status_products_quantity_unit', $CLICSHOPPING_ProductsQuantityUnit->link('ProductsQuantityUnit&DeleteConfirm&page=' . $page . '&oID=' . $oInfo->products_quantity_unit_id)); ?>

  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_ProductsQuantityUnit->getDef('text_info_heading_products_unit_quantity_delete'); ?></strong>
  </div>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo $CLICSHOPPING_ProductsQuantityUnit->getDef('text_info_delete_intro'); ?>
        <br/><br/></div>
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo '<strong>' . $oInfo->products_quantity_unit_title . '</strong>'; ?><br/><br/>
      </div>
      <div class="col-md-12 text-center">
        <?php
        if ($remove_status) {
          ?>
          <span><br/><?php echo HTML::button($CLICSHOPPING_ProductsQuantityUnit->getDef('button_delete'), null, null, 'danger', null, 'sm') . ' </span><span>' . HTML::button(CLICSHOPPING::getDef('button_cancel'), null, $CLICSHOPPING_ProductsQuantityUnit->link('ProductsQuantityUnit&page=' . (int)$_GET['page'] . '&oID=' . $oInfo->products_quantity_unit_id), 'warning', null, 'sm'); ?></span>
          <?php
        } else {
          ?>
          <span><br/><?php echo HTML::button($CLICSHOPPING_ProductsQuantityUnit->getDef('button_cancel'), null, CLICSHOPPING::link('ProductsQuantityUnit&page=' . (int)$_GET['page'] . '&oID=' . $oInfo->products_quantity_unit_id), 'warning', null, 'sm'); ?></span>
          <?php
        }
        ?>
      </div>
    </div>
  </div>
</div>
</div>