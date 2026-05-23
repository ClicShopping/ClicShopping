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
    <strong><?php echo $CLICSHOPPING_Products->getDef('text_info_heading_archive'); ?></strong></div>
  <?php echo HTML::form('archive', $CLICSHOPPING_Products->link('Products&ArchiveConfirm&&cPath=' . $cPath)) . HTML::hiddenField('products_id', $pInfo->products_id); ?>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo $CLICSHOPPING_Products->getDef('text_info_archive_intro'); ?><br/><br/></div>
      <div class="mt-1"></div>
      <div class="col-md-12">
        <span class="col-sm-2"><?php echo '<strong>' . $pInfo->products_name . '</strong>'; ?></span>
      </div>
      <div class="mt-1"></div>
      <div class="col-md-12">
        <span class="col-sm-2"><?php echo $CLICSHOPPING_Products->getDef('text_info_current_categories'); ?></span>
        <span
          class="col-sm-4"><?php echo '<strong>' . $CLICSHOPPING_CategoriesAdmin->getOutputGeneratedCategoryPath($pInfo->products_id, 'product') . '</strong>'; ?></span>
      </div>
      <div class="mt-1"></div>
      <div class="col-md-12 text-center">
        <span><br/><?php echo HTML::button($CLICSHOPPING_Products->getDef('button_archive'), null, null, 'primary', null, 'sm') . ' </span><span>' . HTML::button($CLICSHOPPING_Products->getDef('button_cancel'), null, $CLICSHOPPING_Products->link('Products&cPath=' . $cPath . '&pID=' . $pInfo->products_id), 'warning', null, 'sm'); ?></span>
      </div>
    </div>
  </div>

  </form>
</div>

