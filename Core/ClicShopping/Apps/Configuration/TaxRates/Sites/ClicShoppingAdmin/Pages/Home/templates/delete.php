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

$CLICSHOPPING_TaxRates = Registry::get('TaxRates');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$Qrates = $CLICSHOPPING_TaxRates->db->prepare('select tax_description,
                                                        tax_rates_id
                                                 from :table_tax_rates
                                                 where tax_rates_id = :tax_rates_id
                                                ');
$Qrates->bindInt(':tax_rates_id', $_GET['tID']);
$Qrates->execute();

$trInfo = new ObjectInfo($Qrates->toArray());

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/tax_rates.gif', $CLICSHOPPING_TaxRates->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_TaxRates->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_TaxRates->getDef('text_info_heading_delete_tax_rate'); ?></strong></div>
  <?php echo HTML::form('rates', $CLICSHOPPING_TaxRates->link('TaxRates&DeleteConfirm&page=' . $page . '&tID=' . $trInfo->tax_rates_id)); ?>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo $CLICSHOPPING_TaxRates->getDef('text_info_delete_info'); ?><br/><br/></div>
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo '<strong>' . $trInfo->tax_description . '</strong>'; ?><br/><br/></div>
      <div class="col-md-12 text-center">
        <span><br/><?php echo HTML::button($CLICSHOPPING_TaxRates->getDef('button_delete'), null, null, 'danger', null, 'sm') . ' </span><span>' . HTML::button($CLICSHOPPING_TaxRates->getDef('button_cancel'), null, $CLICSHOPPING_TaxRates->link('TaxRates&page=' . (int)$_GET['page'] . '&tID=' . $trInfo->tax_rates_id), 'warning', null, 'sm'); ?></span>
      </div>
    </div>
  </div>
  </form>
</div>