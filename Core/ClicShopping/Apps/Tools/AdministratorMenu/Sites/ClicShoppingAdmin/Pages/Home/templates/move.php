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

use ClicShopping\Apps\Tools\AdministratorMenu\Classes\ClicShoppingAdmin\AdministratorMenu;

$CLICSHOPPING_AdministratorMenu = Registry::get('AdministratorMenu');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Hooks = Registry::get('Hooks');

$Qcategories = $CLICSHOPPING_AdministratorMenu->db->prepare('select a.id,
                                                                a.link,
                                                                a.parent_id,
                                                                a.access,
                                                                a.sort_order,
                                                                a.b2b_menu,
                                                                a.app_code,
                                                                amd.label
                                                          from :table_administrator_menu a,
                                                                :table_administrator_menu_description amd
                                                          where a.id = amd.id
                                                          and amd.language_id = :language_id
                                                          and a.id = :id
                                                          order by a.parent_id,
                                                                   a.sort_order
                                                          ');
$Qcategories->bindInt(':id', $_GET['cID']);
$Qcategories->bindInt(':language_id', $CLICSHOPPING_Language->getId());
$Qcategories->execute();

$category_childs = ['childs_count' => AdministratorMenu::getChildsInMenuCount($Qcategories->valueInt('id'))];

$cInfo_array = array_merge($Qcategories->toArray(), $category_childs);
$cInfo = new ObjectInfo($cInfo_array);

if (isset($_GET['cPath'])) {
  $cPath = HTML::sanitize($_GET['cPath']);
} else {
  $cPath = '';
}

if (isset($_GET['cID'])) {
  $current_category_id = HTML::sanitize($_GET['cID']);
} else {
  $current_category_id = 0;
}
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/menu.png', $CLICSHOPPING_AdministratorMenu->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_AdministratorMenu->getDef('heading_title'); ?></span>
          <span class="col-md-9 text-end">

          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_AdministratorMenu->getDef('text_info_heading_move_category'); ?></strong></div>
  <?php echo HTML::form('move', $CLICSHOPPING_AdministratorMenu->link('AdministratorMenu&MoveCategoryConfirm&cPath=' . $cPath . '&id=' . $cInfo->id)); ?>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div
        class="col-md-12"><?php echo $CLICSHOPPING_AdministratorMenu->getDef('text_move_categories_intro', ['move_category' => $cInfo->label]); ?>
        <br/><br/></div>
      <div class="mt-1"></div>
      <div class="col-md-12">
        <span
          class="col-md-3"><?php echo $CLICSHOPPING_AdministratorMenu->getDef('text_move', ['move_label' => $cInfo->label]) . '<br />' . HTML::selectMenu('move_to_category_id', AdministratorMenu::getLabelTree(), $current_category_id); ?></span>
      </div>
      <div class="mt-1"></div>
      <div class="col-md-12 text-center">
        <span><br/><?php echo HTML::button($CLICSHOPPING_AdministratorMenu->getDef('button_move'), null, null, 'primary', null, 'sm') . ' </span><span>' . HTML::button($CLICSHOPPING_AdministratorMenu->getDef('button_cancel'), null, $CLICSHOPPING_AdministratorMenu->link('AdministratorMenu&cPath=' . $cPath . '&cID=' . $cInfo->id), 'warning', null, 'sm'); ?></span>
      </div>
    </div>
  </div>
  </form>
</div>