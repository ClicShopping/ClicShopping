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
Registry::set('ProductsLengthAdmin', new ProductsLengthAdmin());
$CLICSHOPPING_ProductsLengthAdmin = Registry::get('ProductsLengthAdmin');


$Qproducts_length = $CLICSHOPPING_ProductsLength->db->prepare('select language_id,
                                                                         products_length_class_title,
                                                                         products_length_class_key
                                                                from :table_products_length_classes
                                                                where products_length_class_id = :products_length_class_id
                                                                ');
$Qproducts_length->bindInt(':products_length_class_id', $_GET['wID']);
$Qproducts_length->execute();

$wInfo = new ObjectInfo($Qproducts_length->toArray());

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
echo HTML::form('products_length_edit', $CLICSHOPPING_ProductsLength->link('ProductsLength&ProductsLengthUpdate&page=' . $page));
echo HTML::button($CLICSHOPPING_ProductsLength->getDef('button_update'), null, null, 'success') . ' ';
echo HTML::button($CLICSHOPPING_ProductsLength->getDef('button_cancel'), null, $CLICSHOPPING_ProductsLength->link('ProductsLength'), 'warning');

echo HTML::hiddenField('products_length_class_id', HTML::sanitize($_GET['wID']));
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
            <?php
            for ($i = 0, $n = \count($languages); $i < $n; $i++) {
              ?>
              <div class="row">
                <div class="col-md-12">
                  <div class="form-group row">
                    <label for="code"
                           class="col-2 col-form-label"><?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?></label>
                    <div class="col-md-10">
                      <?php echo HTML::inputField('products_length_class_title[' . $languages[$i]['id'] . ']', (isset($products_length_class_title[$languages[$i]['id']]) ? $products_length_class_title[$languages[$i]['id']] : $CLICSHOPPING_ProductsLengthAdmin->getTitle($_GET['wID'], $languages[$i]['id'])), 'class="form-control" required aria-required="true" Authentificator id="products_length_class_title" placeholder="' . $CLICSHOPPING_ProductsLength->getDef('text_products_length_class_title') . '"', true) . '&nbsp;'; ?>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            }
            ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-12">
        <div class="form-group row">
          <label for="products_length_class_title"
                 class="col-2 col-form-label"><?php echo $CLICSHOPPING_ProductsLength->getDef('text_products_length_class_key'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('products_length_class_key', $wInfo->products_length_class_key, 'class="form-control" required aria-required="true" Authentificator id="products_length_class_title" placeholder="' . $CLICSHOPPING_ProductsLength->getDef('text_products_length_class_key') . '"'); ?>
          </div>
        </div>
      </div>
    </div>
    </form>
  </div>