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

$CLICSHOPPING_Currency = Registry::get('Currency');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$Qcurrency = $CLICSHOPPING_Currency->db->prepare('select *
                                                  from :table_currencies
                                                  where currencies_id = :currencies_id
                                                ');
$Qcurrency->bindInt(':currencies_id', $_GET['cID']);
$Qcurrency->execute();

$cInfo = new ObjectInfo($Qcurrency->toArray());

$currencies_id = HTML::sanitize($_GET['cID']);

$Qcurrency = $CLICSHOPPING_Currency->db->get('currencies', 'code', ['currencies_id' => (int)$currencies_id]);

$remove_currency = true;
if ($Qcurrency->value('code') == DEFAULT_CURRENCY) {
  $remove_currency = false;
  $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Currency->getDef('error_remove_default_currency'), 'error');
}

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/currencies.gif', $CLICSHOPPING_Currency->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Currency->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_Currency->getDef('text_info_heading_delete_currency'); ?></strong></div>
  <?php echo HTML::form('currency', $CLICSHOPPING_Currency->link('Currency&DeleteConfirm&page=' . $page . '&cID=' . $cInfo->currencies_id)); ?>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo $CLICSHOPPING_Currency->getDef('text_info_delete_info'); ?><br/><br/></div>
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo '<strong>' . $cInfo->title . '</strong>'; ?><br/><br/></div>
      <div class="col-md-12 text-center">
        <span><br/><?php echo (($remove_currency) ? HTML::button($CLICSHOPPING_Currency->getDef('button_delete'), null, null, 'primary', null, 'sm') : '') . ' </span><span>' . HTML::button($CLICSHOPPING_Currency->getDef('button_cancel'), null, $CLICSHOPPING_Currency->link('Currency&page=' . (int)$_GET['page'] . '&cID=' . $cInfo->currencies_id), 'warning', null, 'sm'); ?></span>
      </div>
    </div>
  </div>
  </form>
</div>