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

use ClicShopping\Apps\Tools\ActionsRecorder\Classes\ClicShoppingAdmin\ActionsRecorder;

$CLICSHOPPING_ActionsRecorder = Registry::get('ActionsRecorder');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$CLICSHOPPING_Hooks = Registry::get('Hooks');

$Qcategories = $CLICSHOPPING_ActionsRecorder->db->prepare('select a.id,
                                                                    a.link,
                                                                    a.parent_id,
                                                                    a.access,
                                                                    a.sort_order,
                                                                    a.b2b_menu,
                                                                    a.app_code,
                                                                    amd.label
                                                              from :table_actions_recorder a,
                                                                    :table_actions_recorder_description amd
                                                              where a.id = amd.id
                                                              and amd.language_id = :language_id
                                                              and a.id = :id
                                                              order by a.parent_id,
                                                                       a.sort_order
                                                              ');
$Qcategories->bindInt(':id', $_GET['cID']);
$Qcategories->bindInt(':language_id', $CLICSHOPPING_Language->getId());
$Qcategories->execute();

$category_childs = ['childs_count' => ActionsRecorder::getChildsInMenuCount($Qcategories->valueInt('id'))];

$cInfo_array = array_merge($Qcategories->toArray(), $category_childs);
$cInfo = new ObjectInfo($cInfo_array);
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/modules_action_recorder.gif', $CLICSHOPPING_ActionsRecorder->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ActionsRecorder->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_ActionsRecorder->getDef('text_info_heading_delete_category'); ?></strong></div>
  <?php echo HTML::form('categories', $CLICSHOPPING_ActionsRecorder->link('ActionsRecorder&DeleteCategoryConfirm&cPath=' . $cPath . '&id=' . $cInfo->id)); ?>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo $CLICSHOPPING_ActionsRecorder->getDef('text_delete_category_intro'); ?>
        <br/><br/></div>
      <div class="mt-1"></div>
      <div class="col-md-12">
        <span class="col-md-3"><?php echo $cInfo->label; ?></span>
      </div>
      <?php
      if ($cInfo->childs_count > 0) {
        ?>
        <div class="mt-1"></div>
        <div class="col-md-12">
            <span
              class="col-md-12"><?php echo $CLICSHOPPING_ActionsRecorder->getDef('text_delete_warning_childs', ['delete_child' => $cInfo->childs_count]); ?></span>
        </div>
        <?php
      }

      if ($cInfo->products_count > 0) {
        ?>
        <div class="mt-1"></div>
        <div class="col-md-12">
            <span
              class="col-md-12"><?php echo $CLICSHOPPING_ActionsRecorder->getDef('text_delete_warning_products', ['delete_warning' => $cInfo->products_count]); ?></span>
        </div>
        <?php
      }
      ?>
      <div class="mt-1"></div>
      <div class="col-md-12 text-center">
        <span><br/><?php echo HTML::button($CLICSHOPPING_ActionsRecorder->getDef('button_delete'), null, null, 'danger', null, 'sm') . ' </span><span>' . HTML::button($CLICSHOPPING_ActionsRecorder->getDef('button_cancel'), null, $CLICSHOPPING_ActionsRecorder->link('ActionsRecorder&cPath=' . $cPath . '&cID=' . $cInfo->id), 'warning', null, 'sm'); ?></span>
      </div>
    </div>
  </div>
  </form>
</div>