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

$CLICSHOPPING_TaxClass = Registry::get('TaxClass');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$Qclasse = $CLICSHOPPING_TaxClass->db->prepare('select *
                                                 from :table_tax_class
                                                 where tax_class_id = :tax_class_id
                                                ');
$Qclasse->bindInt(':tax_class_id', $_GET['tID']);
$Qclasse->execute();

$tcInfo = new ObjectInfo($Qclasse->toArray());

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/tax_classes.gif', $CLICSHOPPING_TaxClass->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_TaxClass->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_TaxClass->getDef('text_info_heading_delete_tax_class'); ?></strong></div>
  <?php echo HTML::form('classes', $CLICSHOPPING_TaxClass->link('TaxClass&DeleteConfirm&page=' . $page . '&tID=' . $tcInfo->tax_class_id)); ?>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo $CLICSHOPPING_TaxClass->getDef('text_info_delete_info'); ?><br/><br/></div>
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo '<strong>' . $tcInfo->tax_class_title . '</strong>'; ?><br/><br/></div>
      <div class="col-md-12 text-center">
        <span><br/><?php echo HTML::button($CLICSHOPPING_TaxClass->getDef('button_delete'), null, null, 'danger', null, 'sm') . ' </span><span>' . HTML::button($CLICSHOPPING_TaxClass->getDef('button_cancel'), null, $CLICSHOPPING_TaxClass->link('TaxClass&page=' . (int)$_GET['page'] . '&tID=' . $tcInfo->tax_class_id), 'warning', null, 'sm'); ?></span>
      </div>
    </div>
  </div>
  </form>
</div>