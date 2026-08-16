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

use ClicShopping\Apps\Configuration\ProductsLength\Classes\ClicShoppingAdmin\ProductsLengthAdmin;

$CLICSHOPPING_ProductsLength = Registry::get('ProductsLength');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$CLICSHOPPING_Language = Registry::get('Language');

$wInfo = new ObjectInfo(array());

$languages = $CLICSHOPPING_Language->getLanguages();

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
            class="col-md-7 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ProductsLength->getDef('heading_title'); ?></span>
          <span class="col-md-4 text-end">
<?php
echo HTML::form('ClassInsert', $CLICSHOPPING_ProductsLength->link('ProductsLength&ClassInsert&page=' . $page));
echo HTML::button($CLICSHOPPING_ProductsLength->getDef('button_insert'), null, null, 'primary') . ' ';
echo HTML::button($CLICSHOPPING_ProductsLength->getDef('button_cancel'), null, $CLICSHOPPING_ProductsLength->link('ProductsLength'), 'warning');
?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_ProductsLength->getDef('text_info_heading_insert_products_length'); ?></strong>
  </div>
  <div class="adminformTitle">

    <div class="row">
      <div class="col-md-12">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_ProductsLength->getDef('text_info_edit_intro'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_ProductsLength->getDef('text_info_edit_intro'); ?></label>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_ProductsLength->getDef('text_info_class_title_to_id'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_ProductsLength->getDef('text_info_class_title_to_id'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::selectField('products_length_class_id', ProductsLengthAdmin::getClassesPullDown()); ?>
          </div>
        </div>
      </div>
    </div>


    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_ProductsLength->getDef('text_info_class_title_to_id'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_ProductsLength->getDef('text_info_class_title_to_id'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::selectField('products_length_class_to_id', ProductsLengthAdmin::getClassesPullDown()); ?>
          </div>
        </div>
      </div>
    </div>


    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_ProductsLength->getDef('text_info_class_conversaion_value'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_ProductsLength->getDef('text_info_class_conversaion_value'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('products_length_class_rule', null); ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  </form>
</div>