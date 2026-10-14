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

$CLICSHOPPING_ProductsLength = Registry::get('ProductsLength');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$Qproducts_length = $CLICSHOPPING_ProductsLength->db->prepare('select products_length_class_from_id,
                                                                        products_length_class_to_id,
                                                                        products_length_class_rule
                                                                from :table_products_length_classes_rules
                                                                where products_length_class_from_id = :products_length_class_from_id
                                                                and products_length_class_to_id = :products_length_class_to_id
                                                                ');
$Qproducts_length->bindInt(':products_length_class_from_id', HTML::sanitize($_GET['wID']));
$Qproducts_length->bindInt(':products_length_class_to_id', HTML::sanitize($_GET['tID']));
$Qproducts_length->execute();

$wInfo = new ObjectInfo($Qproducts_length->toArray());

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/products_length.png', $CLICSHOPPING_ProductsLength->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ProductsLength->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_ProductsLength->getDef('text_info_heading_delete_products_length'); ?></strong>
  </div>
  <?php echo HTML::form('delete', $CLICSHOPPING_ProductsLength->link('ProductsLength&ClassDeleteConfirm&page=' . $page . '&wID=' . $wInfo->products_length_class_from_id . '&tID=' . $wInfo->products_length_class_to_id)); ?>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo $CLICSHOPPING_ProductsLength->getDef('text_info_delete_info'); ?><br/><br/>
      </div>
      <div class="mt-1"></div>
      <div
        class="col-md-12"><?php echo '<strong>' . $CLICSHOPPING_ProductsLength->getDef('text_products_length_rules') . ' ' . $wInfo->products_length_class_rule . '</strong>'; ?>
        <br/><br/></div>
      <div class="col-md-12 text-center">
        <span><br/><?php echo HTML::button($CLICSHOPPING_ProductsLength->getDef('button_delete'), null, null, 'danger', null, 'sm') . ' </span><span>' . HTML::button($CLICSHOPPING_ProductsLength->getDef('button_cancel'), null, $CLICSHOPPING_ProductsLength->link('ProductsLength&page=' . (int)$_GET['page'] . '&tID=' . $wInfo->products_length_id), 'warning', null, 'sm'); ?></span>
      </div>
    </div>
  </div>

  </form>
</div>