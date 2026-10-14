<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

$CLICSHOPPING_Settings = Registry::get('Settings');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$Qadmin = $CLICSHOPPING_Settings->db->get('settings', ['id',
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
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/settings.gif', $CLICSHOPPING_Settings->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-7 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Settings->getDef('heading_title'); ?></span>
          <span class="col-md-4 text-end">
<?php
echo HTML::form('administrator', $CLICSHOPPING_Settings->link('Settings&Update&aID=' . $Qadmin->valueInt('id')), 'post', 'autocomplete="off"');
echo HTML::button($CLICSHOPPING_Settings->getDef('button_update'), null, null, 'success') . ' ';
echo HTML::button($CLICSHOPPING_Settings->getDef('button_cancel'), null, $CLICSHOPPING_Settings->link('Settings'), 'warning');
?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 mainTitle"><strong><?php echo $Qadmin->value('user_name'); ?></strong></div>
  <div class="adminformTitle">

    <div class="row">
      <div class="col-md-12">
        <div class="form-group row">
          <label for="code"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Settings->getDef('text_info_insert_intro'); ?></label>
        </div>
      </div>
    </div>


    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="code"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Settings->getDef('text_info_name'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('name', $Qadmin->value('name'), 'required aria-required="true" placeholder="' . $CLICSHOPPING_Settings->getDef('text_info_name') . '"'); ?>
          </div>
        </div>
      </div>
    </div>


    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="code"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Settings->getDef('text_info_firstname'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('first_name', $Qadmin->value('first_name'), 'required aria-required="true" placeholder="' . $CLICSHOPPING_Settings->getDef('text_info_first_name') . '"'); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="code"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Settings->getDef('text_info_username'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('username', $Qadmin->value('user_name'), 'required aria-required="true" placeholder="' . $CLICSHOPPING_Settings->getDef('text_info_username') . '"', 'email'); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="code"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Settings->getDef('text_info_password'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('password', null, 'placeholder="' . $CLICSHOPPING_Settings->getDef('text_info_password') . '"', 'password'); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="code"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Settings->getDef('text_info_access'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::selectMenu('access_administrator', AdministratorAdmin::getAdministratorRight($CLICSHOPPING_Settings->getDef('text_selected')), $Qadmin->value('access')); ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  </form>
</div>