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

$CLICSHOPPING_ProductsQuantityUnit = Registry::get('ProductsQuantityUnit');
$CLICSHOPPING_ProductsAdmin = Registry::get('ProductsAdmin');
$CLICSHOPPING_Language = Registry::get('Language');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$QproductsQquantityUnit = $CLICSHOPPING_ProductsQuantityUnit->db->prepare('select  *
                                                                            from :table_products_quantity_unit
                                                                            where language_id = :language_id
                                                                            and products_quantity_unit_id = :products_quantity_unit_id
                                                                            order by products_quantity_unit_id
                                                                        ');

$QproductsQquantityUnit->bindInt(':language_id', $CLICSHOPPING_Language->getId());
$QproductsQquantityUnit->bindInt(':products_quantity_unit_id', $_GET['oID']);

$QproductsQquantityUnit->execute();

$oInfo = new ObjectInfo($QproductsQquantityUnit->toArray());

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/products_unit.png', $CLICSHOPPING_ProductsQuantityUnit->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ProductsQuantityUnit->getDef('heading_title'); ?></span>
          <span class="col-md-9 text-end">
<?php
echo HTML::form('status_products_quantity_unit', $CLICSHOPPING_ProductsQuantityUnit->link('ProductsQuantityUnit&Update&page=' . $page . '&oID=' . $oInfo->products_quantity_unit_id));
echo HTML::button($CLICSHOPPING_ProductsQuantityUnit->getDef('button_update'), null, null, 'success') . ' ';
echo HTML::button($CLICSHOPPING_ProductsQuantityUnit->getDef('button_cancel'), null, $CLICSHOPPING_ProductsQuantityUnit->link('ProductsQuantityUnit'), 'warning');
?>
          </span>


        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_ProductsQuantityUnit->getDef('text_info_heading_products_quantity_unit'); ?></strong>
  </div>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo $CLICSHOPPING_ProductsQuantityUnit->getDef('text_info_edit_intro'); ?><br/><br/>
      </div>
      <div class="mt-1"></div>
      <div class="col-md-12">

        <?php
        $products_quantity_unit_inputs_string = '';
        $languages = $CLICSHOPPING_Language->getLanguages();

        for ($i = 0, $n = \count($languages); $i < $n; $i++) {
          ?>
          <div class="row">
            <div class="col-md-5">
              <div class="form-group row">
                <label for="code"
                       class="col-2 col-form-label"><?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?></label>
                <div class="col-md-5">
                  <?php echo HTML::inputField('products_quantity_unit_title[' . $languages[$i]['id'] . ']', $CLICSHOPPING_ProductsAdmin->getProductsQuantityUnitTitle($oInfo->products_quantity_unit_id, $languages[$i]['id'])); ?>
                </div>
              </div>
            </div>
          </div>
          <?php
        }
        ?>
      </div>
      <div class="mt-1"></div>

      <?php
      if (DEFAULT_PRODUCTS_QUANTITY_UNIT_STATUS_ID != $oInfo->products_quantity_unit_id) {
        ?>
        <div class="mt-1"></div>
        <div class="col-md-12">
          <span class="col-md-3"></span>
          <ul class="list-group-slider list-group-flush">
            <li class="list-group-item-slider">
              <label class="switch">
                <?php echo HTML::checkboxField('default', null, null, 'class="success"'); ?>
                <span class="slider"></span>
              </label>
            </li>
            <span
              class="text-slider"><?php echo $CLICSHOPPING_ProductsQuantityUnit->getDef('text_set_default'); ?></span>
          </ul>
        </div>
        <?php
      }
      ?>
    </div>
  </div>
</div>