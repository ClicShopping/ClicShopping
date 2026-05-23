<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Administrators = Registry::get('Administrators');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$Qadmin = $CLICSHOPPING_Administrators->db->get('administrators', ['id',
  'user_name',
  'name',
  'first_name',
  'access'
],
  ['id' => $_GET['aID']]
);
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/administrators.gif', $CLICSHOPPING_Administrators->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Administrators->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 mainTitle"><strong><?php echo $Qadmin->value('user_name'); ?></strong></div>
  <?php echo HTML::form('administrator', $CLICSHOPPING_Administrators->link('Administrators&DeleteConfirm&aID=' . $Qadmin->valueInt('id'))); ?>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo $CLICSHOPPING_Administrators->getDef('text_info_delete_intro'); ?><br/><br/>
      </div>
      <div class="mt-1"></div>
      <div class="col-md-12">
        <span class="col-md-2"><?php echo $CLICSHOPPING_Administrators->getDef('text_info_name'); ?></span>
        <span class="col-md-2"><?php echo HTML::outputProtected($Qadmin->value('name')); ?></span>
      </div>
      <div class="mt-1"></div>
      <div class="col-md-12 text-center">
        <span
          class="text-center"><?php echo HTML::button($CLICSHOPPING_Administrators->getDef('button_delete'), null, null, 'danger', null, 'sm') . ' </span><span>' . HTML::button($CLICSHOPPING_Administrators->getDef('button_cancel'), null, $CLICSHOPPING_Administrators->link('Administrators&aID=' . $Qadmin->valueInt('id')), 'warning', null, 'sm'); ?></span>
      </div>
    </div>
  </div>
</div>