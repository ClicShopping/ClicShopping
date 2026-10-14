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

$CLICSHOPPING_Products = Registry::get('Products');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_CategoriesAdmin = Registry::get('CategoriesAdmin');
$CLICSHOPPING_Language = Registry::get('Language');

$Qproducts = $CLICSHOPPING_Products->db->prepare('select p.products_id,
                                                      pd.products_name
                                                 from :table_products p,
                                                      :table_products_description pd,
                                                      :table_products_to_categories p2c
                                                 where p.products_id = pd.products_id
                                                 and pd.language_id = :language_id
                                                 and p.products_id = p2c.products_id
                                                 and p.products_id = :products_id
                                              ');

$Qproducts->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
$Qproducts->bindInt(':products_id', (int)$_GET['pID']);

$Qproducts->execute();

$pInfo = new ObjectInfo($Qproducts->toArray());

if (isset($_GET['cPath'])) {
  $cPath = HTML::sanitize($_GET['cPath']);
} else {
  $cPath = 0;
}
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/produit.gif', $CLICSHOPPING_Products->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Products->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_Products->getDef('text_info_heading_delete_product'); ?></strong></div>
  <?php echo HTML::form('products', $CLICSHOPPING_Products->link('Products&DeleteConfirm&&cPath=' . $cPath)) . HTML::hiddenField('products_id', $pInfo->products_id); ?>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo $CLICSHOPPING_Products->getDef('text_delete_products_intro'); ?><br/><br/></div>
      <div class="col-md-12"><?php echo $CLICSHOPPING_Products->getDef('text_delete_products_info'); ?><br/><br/></div>
      <div class="mt-1"></div>
      <div class="col-md-12">
        <span class="col-sm-2"><?php echo '<strong>' . $pInfo->products_name . '</strong>'; ?></span>
      </div>
      <?php
      $product_categories_string = '';
      $product_categories = $CLICSHOPPING_CategoriesAdmin->getGenerateCategoryPath($pInfo->products_id, 'product');

      for ($i = 0, $n = \count($product_categories); $i < $n; $i++) {
        $category_path = '';

        for ($j = 0, $k = \count($product_categories[$i]); $j < $k; $j++) {
          $category_path .= $product_categories[$i][$j]['text'] . '&nbsp;&gt;&nbsp;';
        }

        $category_path = substr($category_path, 0, -16);
        $product_categories_string .= HTML::checkboxField('product_categories[]', $product_categories[$i][count($product_categories[$i]) - 1]['id'], true) . '&nbsp;' . $category_path . '<br />';
      }

      $product_categories_string = substr($product_categories_string, 0, -4);

      ?>
      <div class="mt-1"></div>
      <div class="col-md-12">
        <span class="col-sm-4"><br/><?php echo $product_categories_string; ?></span>
      </div>
      <div class="mt-1"></div>
      <div class="col-md-12 text-center">
        <span><br/><?php echo HTML::button($CLICSHOPPING_Products->getDef('button_delete'), null, null, 'danger', null, 'sm') . ' </span><span>' . HTML::button($CLICSHOPPING_Products->getDef('button_cancel'), null, $CLICSHOPPING_Products->link('Products&cPath=' . $cPath . '&pID=' . $pInfo->products_id), 'warning', null, 'sm'); ?></span>
      </div>
    </div>
  </div>

  </form>
</div>

