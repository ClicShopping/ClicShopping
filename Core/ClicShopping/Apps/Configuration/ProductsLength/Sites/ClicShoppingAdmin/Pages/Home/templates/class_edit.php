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
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');

$Qproducts_length = $CLICSHOPPING_ProductsLength->db->prepare('select wc.products_length_class_id,
                                                                           wc.products_length_class_key,
                                                                           wc.language_id,
                                                                           wc.products_length_class_title,
                                                                           tc.products_length_class_from_id,
                                                                           tc.products_length_class_to_id,
                                                                           tc.products_length_class_rule
                                                                  from :table_products_length_classes wc,
                                                                       :table_products_length_classes_rules tc 
                                                                  where wc.products_length_class_id = :products_length_class_id
                                                                  and tc.products_length_class_to_id = :products_length_class_to_id
                                                                  ');
$Qproducts_length->bindInt(':products_length_class_id', $_GET['wID']);
$Qproducts_length->bindInt(':products_length_class_to_id', $_GET['tID']);
$Qproducts_length->execute();

$wInfo = new ObjectInfo($Qproducts_length->toArray());

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

if ($CLICSHOPPING_MessageStack->exists('class_edit')) {
  echo $CLICSHOPPING_MessageStack->get('class_edit');
}
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
echo HTML::form('form_product_length', $CLICSHOPPING_ProductsLength->link('ProductsLength&ClassUpdate&page=' . $page . '&wID=' . $_GET['wID'] . '&tID=' . $_GET['tID']));
echo HTML::button($CLICSHOPPING_ProductsLength->getDef('button_update'), null, null, 'success') . ' ';
echo HTML::button($CLICSHOPPING_ProductsLength->getDef('button_cancel'), null, $CLICSHOPPING_ProductsLength->link('ProductsLength'), 'warning');
?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_ProductsLength->getDef('text_info_heading_edit_products_length'); ?></strong></div>
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
          <label for="<?php echo $CLICSHOPPING_ProductsLength->getDef('text_info_class_title'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_ProductsLength->getDef('text_info_class_title'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::selectField('products_length_class_id', ProductsLengthAdmin::getClassesPullDown(), $wInfo->products_length_class_id); ?>
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
            <?php echo HTML::selectField('products_length_class_to_id', ProductsLengthAdmin::getClassesPullDown(), $wInfo->products_length_class_to_id); ?>
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
            <?php echo HTML::inputField('products_length_class_rule', $wInfo->products_length_class_rule); ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  </form>
</div>