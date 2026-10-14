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

$CLICSHOPPING_Categories = Registry::get('Categories');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_CategoriesAdmin = Registry::get('CategoriesAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Wysiwyg = Registry::get('Wysiwyg');

$Qcategories = $CLICSHOPPING_Categories->db->prepare('select c.categories_id,
                                                               cd.categories_name,
                                                               c.parent_id
                                                        from :table_categories c,
                                                             :table_categories_description cd
                                                        where c.categories_id = cd.categories_id
                                                        and cd.language_id = :language_id
                                                        and c.categories_id = :categories_id
                                                        ');
$Qcategories->bindInt(':language_id', $CLICSHOPPING_Language->getId());
$Qcategories->bindInt(':categories_id', $_GET['cID']);

$Qcategories->execute();

$category_childs = ['childs_count' => $CLICSHOPPING_CategoriesAdmin->getChildsInCategoryCount($Qcategories->valueInt('categories_id'))];
$category_products = ['products_count' => $CLICSHOPPING_CategoriesAdmin->getCatalogInCategoryCount($Qcategories->valueInt('categories_id'))];


$cInfo_array = array_merge($Qcategories->toArray(), $category_childs, $category_products);
$cInfo = new ObjectInfo($cInfo_array);

$cPath = 0;

if (isset($_GET['cPath'])) $cPath = HTML::sanitize($_GET['cPath']);

echo $CLICSHOPPING_Wysiwyg::getWysiwyg();
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/categorie.gif', $CLICSHOPPING_Categories->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Categories->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_Categories->getDef('text_info_heading_move_category'); ?></strong></div>
  <?php echo HTML::form('categories', $CLICSHOPPING_Categories->link('Categories&MoveConfirm&cPath=' . $cPath . '&categories_id=' . $cInfo->categories_id)); ?>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div
        class="col-md-12"><?php echo $CLICSHOPPING_Categories->getDef('text_move_categories_intro', ['categorie_name' => $cInfo->categories_name]); ?>
        <br/><br/></div>
      <div class="mt-1"></div>
      <div class="col-md-12">
        <span
          class="col-md-3"><?php echo $CLICSHOPPING_Categories->getDef('text_move', ['categorie_name' => $cInfo->categories_name]) . '<br />' . HTML::selectMenu('move_to_category_id', $CLICSHOPPING_CategoriesAdmin->getCategoryTree(), $cInfo->parent_id); ?></span>
      </div>
      <div class="mt-1"></div>
      <div class="col-md-12 text-center">
        <span><br/><?php echo HTML::button($CLICSHOPPING_Categories->getDef('button_move'), null, null, 'primary', null, 'sm') . ' </span><span>' . HTML::button($CLICSHOPPING_Categories->getDef('button_cancel'), null, $CLICSHOPPING_Categories->link('Categories&cPath=' . $cPath . '&cID=' . $cInfo->categories_id), 'warning', null, 'sm'); ?></span>
      </div>
    </div>
  </div>
  </form>
</div>

