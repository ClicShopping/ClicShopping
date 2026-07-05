<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\FileSystem;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin\ProductStock;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\Apps\Marketing\SEO\Classes\ClicShoppingAdmin\SeoAdmin;
use ClicShopping\Sites\ClicShoppingAdmin\Tax;

$CLICSHOPPING_Products = Registry::get('Products');
$CLICSHOPPING_ProductsAdmin = Registry::get('ProductsAdmin');
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_CategoriesAdmin = Registry::get('CategoriesAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Wysiwyg = Registry::get('Wysiwyg');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$CLICSHOPPING_Hooks->call('Products', 'PreAction');

$languages = $CLICSHOPPING_Language->getLanguages();

$tax = new tax;

// check if the catalog image directory exists
if (is_dir($CLICSHOPPING_Template->getDirectoryPathTemplateShopImages())) {
  if (!FileSystem::isWritable($CLICSHOPPING_Template->getDirectoryPathTemplateShopImages())) $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Products->getDef('error_catalog_image_directory_not_writeable'), 'warning');
}

$parameters = [
  'products_name' => '',
  'products_description' => '',
  'products_url' => '',
  'products_id' => '',
  'products_quantity' => '',
  'products_model' => '',
  'products_ean' => '',
  'products_sku' => '',
  'products_mpn' => '',
  'products_jan' => '',
  'products_isbn' => '',
  'products_upc' => '',
  'products_image' => '',
  'products_image_zoom' => '',
  'products_larger_images' => [],
  'products_price' => '',
  'products_weight' => '',
  'products_price_kilo' => '',
  'products_date_added' => '',
  'products_last_modified' => '',
  'products_date_available' => '',
  'products_status' => '',
  'products_percentage' => '',
  'products_view' => '',
  'orders_view' => '',
  'products_tax_class_id' => '',
  'products_min_qty_order' => '',
  'products_only_online' => '',
  'products_image_medium' => '',
  'products_cost' => '',
  'products_handling' => '',
  'products_packaging' => '',
  'products_sort_order' => '',
  'products_shipping_delay' => '',
  'products_quantity_alert' => '',
  'products_only_shop' => '',
  'products_download_filename' => '',
  'products_download_public' => '',
  'products_description_summary' => '',
  'products_shipping_delay_out_of_stock' => '',
  'products_type' => '',
];

$pInfo = new ObjectInfo($parameters);

if (isset($_GET['pID']) && isset($_POST)) {
  $data_products = $CLICSHOPPING_ProductsAdmin->get($_GET['pID']);
  $pInfo->ObjectInfo($data_products);

  $Qimages = $CLICSHOPPING_Products->db->get(
    'products_images',
    ['id', 'image', 'htmlcontent', 'sort_order'],
    ['products_id' => (int)$pInfo->products_id],
    'sort_order'
  );

  $larger_images = (array)$pInfo->products_larger_images;

  while ($Qimages->fetch()) {
    $larger_images[] = [
      'id' => $Qimages->valueInt('id'),
      'image' => $Qimages->value('image'),
      'htmlcontent' => $Qimages->value('htmlcontent'),
      'sort_order' => $Qimages->valueInt('sort_order')
    ];
  }

  $pInfo->products_larger_images = $larger_images;
}

$cPath = 0;

if (isset($_GET['cPath'])) {
  $cPath = HTML::sanitize($_GET['cPath']);
}

$form_action = (isset($_GET['pID'])) ? 'Update' : 'Insert';

echo HTML::form('new_product', $CLICSHOPPING_Products->link('Products&' . $form_action . '&cPath=' . $cPath . (isset($_GET['pID']) ? '&pID=' . $_GET['pID'] : '')), 'post', 'enctype="multipart/form-data" id="form"');

echo $CLICSHOPPING_Wysiwyg::getWysiwyg();
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
          <span class="col-md-6 text-end">
<?php
echo HTML::hiddenField('products_date_added', (($pInfo->products_date_added) ? $pInfo->products_date_added : date('Y-m-d')));
echo HTML::hiddenField('parent_id', $cPath);
echo HTML::hiddenField('cPath', HTML::sanitize($cPath));

if ($form_action == 'Update') {
  echo HTML::button($CLICSHOPPING_Products->getDef('button_update'), null, null, 'success') . ' ';
} else {
  echo HTML::button($CLICSHOPPING_Products->getDef('button_insert'), null, null, 'success') . ' ';
}

echo HTML::button($CLICSHOPPING_Products->getDef('button_cancel'), null, $CLICSHOPPING_Products->link('Products&cPath=' . $cPath), 'warning');
?>
            </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div id="productsTabs" style="overflow: auto;">
    <div class="d-flex">
      <ul class="nav nav-tabs flex-column me-3" role="tablist" id="myTab" style="min-width: 160px; border-bottom: none; border-right: 1px solid #dee2e6;">
        <li
          class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active rounded-0 text-nowrap">' . $CLICSHOPPING_Products->getDef('tab_general') . '</a>'; ?></li>
        <li
          class="nav-item"><?php echo '<a href="#tab2" role="tab" data-bs-toggle="tab" class="nav-link rounded-0 text-nowrap">' . $CLICSHOPPING_Products->getDef('tab_shipping'); ?></a></li>
        <li
          class="nav-item"><?php echo '<a href="#tab3" role="tab" data-bs-toggle="tab" class="nav-link rounded-0 text-nowrap">' . $CLICSHOPPING_Products->getDef('tab_stock'); ?></a></li>
        <li
          class="nav-item"><?php echo '<a href="#tab4" role="tab" data-bs-toggle="tab" class="nav-link rounded-0 text-nowrap">' . $CLICSHOPPING_Products->getDef('tab_price'); ?></a></li>
        <li
          class="nav-item"><?php echo '<a href="#tab5" role="tab" data-bs-toggle="tab" class="nav-link rounded-0 text-nowrap">' . $CLICSHOPPING_Products->getDef('tab_description'); ?></a></li>
        <li
          class="nav-item"><?php echo '<a href="#tab6" role="tab" data-bs-toggle="tab" class="nav-link rounded-0 text-nowrap">' . $CLICSHOPPING_Products->getDef('tab_img'); ?></a></li>
        <li
          class="nav-item"><?php echo '<a href="#tab7" role="tab" data-bs-toggle="tab" class="nav-link rounded-0 text-nowrap">' . $CLICSHOPPING_Products->getDef('tab_ref'); ?></a></li>
        <li
          class="nav-item"><?php echo '<a href="#tab8" role="tab" data-bs-toggle="tab" class="nav-link rounded-0 text-nowrap">' . $CLICSHOPPING_Products->getDef('tab_other_options'); ?></a></li>
      </ul>
    <div class="tabsClicShopping flex-grow-1">
      <div class="tab-content">
        <?php
        // packaging
        $products_packaging_array = [
          array('id' => '0', 'text' => $CLICSHOPPING_Products->getDef('text_choose')),
          array('id' => '1', 'text' => $CLICSHOPPING_Products->getDef('text_products_packaging_new')),
          array('id' => '2', 'text' => $CLICSHOPPING_Products->getDef('text_products_packaging_repackaged')),
          array('id' => '3', 'text' => $CLICSHOPPING_Products->getDef('text_products_packaging_used'))
        ];


        // ******************************************
        // Tab 1 General
        //*******************************************
        ?>
        <script
          src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/modal_popup.js'); ?>"></script>
        <style>
          .modal-dialog {
            width: 900px !important;
          }
        </style>

        <div class="tab-pane active" id="tab1">
          <div class="col-md-12 mainTitle">
            <div class="float-start"><?php echo $CLICSHOPPING_Products->getDef('text_products_name'); ?></div>
            <div
              class="float-end"><?php echo $CLICSHOPPING_Products->getDef('text_user_name') . ' ' . AdministratorAdmin::getUserAdmin(); ?></div>
          </div>
          <div class="adminformTitle" id="tab1ContentRow1">
            <?php
            for ($i = 0, $n = \count($languages); $i < $n; $i++) {
              ?>
              <div class="form-group row">
                <label for="code"
                       class="col-2 col-form-label"><?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?>
                  <bold>*</bold>
                </label>
                <div
                  class="col-md-5"><?php echo HTML::inputField('products_name[' . $languages[$i]['id'] . ']', ($products_name[$languages[$i]['id']] ?? $CLICSHOPPING_ProductsAdmin->getProductsName($pInfo->products_id, $languages[$i]['id'])), 'required aria-required="true" id="' . 'products_name[' . $languages[$i]['id'] . ']' . '" id="product_name_' . $i . '" placeholder="' . $CLICSHOPPING_Products->getDef('text_products_name') . '"', true) . '&nbsp;'; ?></div>
              </div>
              <?php
            }
            ?>
          </div>
          <div class="mt-1"></div>
          <div
            class="col-md-12 mainTitle"><?php echo $CLICSHOPPING_Products->getDef('text_products_other_information'); ?></div>
          <div class="adminformTitle">
            <div class="row" id="tab1ContentRow2"></div>
            <div class="mt-1"></div>
            <div class="row" id="tab1ContentRow3">
              <div class="col-md-5" id="tab1ContentRow3Model">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_model'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_model'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('products_model', $pInfo->products_model, 'id="products_model" placeholder="' . CONFIGURATION_PREFIX_MODEL . '"'); ?>
                    <a
                      href="<?php echo $CLICSHOPPING_Products->link('ConfigurationPopUpFields&cKey=CONFIGURATION_PREFIX_MODEL'); ?>"
                      data-bs-toggle="modal" data-refresh="true"
                      data-bs-target="#myModal"><?php echo '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Products->getDef('icon_edit') . '"></i></h4>'; ?></a>
                    <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
                         aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <div class="modal-body">
                            <div class="te"></div>
                          </div>
                        </div> <!-- /.modal-content -->
                      </div><!-- /.modal-dialog -->
                    </div><!-- /.modal -->
                  </div>
                </div>
              </div>
              <div class="col-md-5" id="tab1ContentRow3Sku">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_sku'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_sku'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('products_sku', $pInfo->products_sku, 'id="products_sku" placeholder="' . $CLICSHOPPING_Products->getDef('text_products_sku_info') . '"'); ?>
                  </div>
                </div>
              </div>
              <div class="col-md-5" id="tab1ContentRow3Upc">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_upc'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_upc'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('products_upc', $pInfo->products_upc, 'id="products_upc" placeholder="' . $CLICSHOPPING_Products->getDef('text_products_upc_info') . '"'); ?>
                  </div>
                </div>
              </div>

              <div class="col-md-5" id="tab1ContentRow3Jan">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_jan'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_jan'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('products_jan', $pInfo->products_jan, 'id="products_jan" placeholder="' . $CLICSHOPPING_Products->getDef('text_products_jan_info') . '"'); ?>
                  </div>
                </div>
              </div>
              <div class="mt-1"></div>
              <div class="col-md-5" id="tab1ContentRow3Isbn">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_isbn'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_isbn'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('products_isbn', $pInfo->products_isbn, 'id="products_isbn" placeholder="' . $CLICSHOPPING_Products->getDef('text_products_isbn_info') . '"'); ?>
                  </div>
                </div>
              </div>

              <div class="col-md-5" id="tab1ContentRow3Mnp">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_mpn'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_mpn'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('products_mpn', $pInfo->products_mpn, 'id="products_mpn" placeholder="' . $CLICSHOPPING_Products->getDef('text_products_mpn_info') . '"'); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-1"></div>
            <div class="row" id="tab1ContentRow4">
              <div class="col-md-5" id="tab1ContentRow4Ean">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_ean'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_ean'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('products_ean', $pInfo->products_ean, 'id="products_ean" maxlength="15"'); ?>
                  </div>
                </div>
              </div>
              <div class="col-md-5" id="tab1ContentRow4Option"></div>
            </div>
          </div>
          <div class="mt-1"></div>
          <div
            class="col-md-12 mainTitle"><?php echo $CLICSHOPPING_Products->getDef('text_products_other_information'); ?></div>
          <div class="adminformTitle">
            <div class="col-md-12" style="padding-top:10px; padding-bottom:10px;" id="tab1ContentRow10">
              <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_url'); ?>"
                     class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_url') . ' <small>' . $CLICSHOPPING_Products->getDef('text_products_url_without_http') . '</small>'; ?></label>
              <?php
              for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                ?>
                <div class="form-group row">
                  <label for="code"
                         class="col-2 col-form-label"><?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?></label>
                  <div
                    class="col-md-5"><?php echo HTML::inputField('products_url[' . $languages[$i]['id'] . ']', (isset($products_url[$languages[$i]['id']]) ? $products_url[$languages[$i]['id']] : $CLICSHOPPING_ProductsAdmin->getProductsUrl($pInfo->products_id, $languages[$i]['id'], 'id="products_url[' . $languages[$i]['id'] . ']"'))); ?></div>
                </div>
                <?php
              }
              ?>
            </div>

            <div class="row" id="tab1ContentRow13">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_sort_order'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_sort_order'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('products_sort_order', $pInfo->products_sort_order, 'size="5"'); ?>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-1"></div>
          <div class="alert alert-info" role="alert">
            <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_Products->getDef('title_help_general') . '"></i></h4> ' . $CLICSHOPPING_Products->getDef('title_help_general') ?></div>
            <div class="mt-1"></div>
            <div><?php echo $CLICSHOPPING_Products->getDef('help_general'); ?></div>
          </div>
          <?php echo $CLICSHOPPING_Hooks->output('Products', 'ProductsContentTab1', null, 'display'); ?>
        </div>


        <?php
        // ******************************************
        // Tab 2 Shipping
        //*******************************************
        ?>
        <div class="tab-pane" id="tab2">
          <div class="col-md-12 mainTitle">
            <span><?php echo $CLICSHOPPING_Products->getDef('text_products_shipping'); ?></span>
          </div>
          <div class="adminformTitle">
            <div class="mt-1"></div>
            <div class="row" id="productsWeight">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_weight'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_weight'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('products_weight', $pInfo->products_weight, 'id="products_weight" size="12"'); ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-1"></div>
            <div class="row" id="tab2Shipping"></div>
          </div>

          <div class="mt-1"></div>
          <div class="col-md-12 mainTitle"><span</span></div>
          <div class="adminformTitle">
            <div class="mt-1"></div>
            <div class="col-md-12" style="padding-top:10px; padding-bottom:10px;" id="tabShippingDelay">
              <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
                <span
                  class="col-sm-12"><?php echo $CLICSHOPPING_Products->getDef('text_products_shipping_delay'); ?></span>
                <span>
                <a
                  href="<?php echo $CLICSHOPPING_Products->link('ConfigurationPopUpFields&cKey=DISPLAY_SHIPPING_DELAY'); ?>"
                  data-bs-toggle="modal" data-refresh="true"
                  data-bs-target="#myModal1"><?php echo '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Products->getDef('icon_edit') . '"></i></h4>'; ?></a>
                <div class="modal fade" id="myModal1" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
                     aria-hidden="true">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <div class="modal-body"><div class="te"></div></div>
                    </div> <!-- /.modal-content -->
                  </div><!-- /.modal-dialog -->
                </div><!-- /.modal -->
              </span>
              </div>
            </div>
            <?php
            for ($i = 0, $n = \count($languages); $i < $n; $i++) {
              ?>
              <div class="form-group row">
                <label for="code"
                       class="col-2 col-form-label"><?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?></label>
                <div
                  class="col-md-5"><?php echo HTML::inputField('products_shipping_delay[' . $languages[$i]['id'] . ']', (isset($products_shipping_delay[$languages[$i]['id']]) ? $products_shipping_delay[$languages[$i]['id']] : $CLICSHOPPING_ProductsAdmin->getProductsShippingDelay($pInfo->products_id, $languages[$i]['id'])), 'size="90"'); ?></div>
              </div>
              <?php
            }
            ?>
            <div class="mt-1"></div>
            <div class="col-md-12" style="padding-top:10px; padding-bottom:10px;" id="tabShippingDelayOutOfStock">
              <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
                <span
                  class="col-sm-12"><?php echo $CLICSHOPPING_Products->getDef('text_products_shipping_delay_out_of_stock'); ?></span>
                <span>
                  <a
                    href="<?php echo $CLICSHOPPING_Products->link('ConfigurationPopUpFields&cKey=DISPLAY_SHIPPING_DELAY_OUT_OF_STOCK'); ?>"
                    data-bs-toggle="modal" data-refresh="true"
                    data-bs-target="#myModal1"><?php echo '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Products->getDef('icon_edit') . '"></i></h4>'; ?></a>
                  <div class="modal fade" id="myModal1" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
                       aria-hidden="true">
                    <div class="modal-dialog">
                      <div class="modal-content">
                        <div class="modal-body"><div class="te"></div></div>
                      </div> <!-- /.modal-content -->
                    </div><!-- /.modal-dialog -->
                  </div><!-- /.modal -->
                </span>
              </div>
            </div>

            <?php
            for ($i = 0, $n = \count($languages); $i < $n; $i++) {
              ?>
              <div class="form-group row">
                <label for="code"
                       class="col-2 col-form-label"><?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?></label>
                <div
                  class="col-md-5"><?php echo HTML::inputField('products_shipping_delay_out_of_stock[' . $languages[$i]['id'] . ']', (isset($products_shipping_delay[$languages[$i]['id']]) ? $products_shipping_delay[$languages[$i]['id']] : $CLICSHOPPING_ProductsAdmin->getProductsShippingDelay($pInfo->products_id, $languages[$i]['id'])), 'size="90"'); ?></div>
              </div>
              <?php
            }
            ?>
          </div>
          <div class="mt-1"></div>

          <div class="row" id="tab2ShippingAdd"></div>
          <div class="mt-1"></div>
        </div>

        <?php
        // ******************************************
        // Tab 3 Stock
        //*******************************************

        $stockable_dopdown = [
          ['id' => 'product', 'text' => $CLICSHOPPING_Products->getDef('text_stockable_product')],
          ['id' => 'consu', 'text' => $CLICSHOPPING_Products->getDef('text_stockable_consumable')],
          ['id' => 'service', 'text' => $CLICSHOPPING_Products->getDef('text_stockable_service')],
        ];
        ?>
        <div class="tab-pane" id="tab3">
          <div class="col-md-12 mainTitle">
            <span><?php echo $CLICSHOPPING_Products->getDef('text_products_stock'); ?></span>
          </div>
          <div class="adminformTitle">
            <div class="row" id="productsStatus">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_status'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_status'); ?></label>
                  <div class="col-md-5">
                    <ul class="list-group-slider list-group-flush">
                      <li class="list-group-item-slider">
                        <label class="switch">
                          <?php echo HTML::checkboxField('products_status', '1', $pInfo->products_status, 'class="success"'); ?>
                          <span class="slider"></span>
                        </label>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
              <?php
              $historical = ProductStock::getHistoricalCustomerDemandByProducts($pInfo->products_id, null);

              if ($historical > 0) {
                ?>
                <div class="col-md-5">
                  <div class="alert alert-danger" role="alert">
                    <?php echo $CLICSHOPPING_Products->getDef('text_products_safety_stock') . ' ' . $historical; ?>
                  </div>
                </div>
                <?php
              }
              ?>
            </div>
            <div class="mt-1"></div>
            <div class="row" id="productsStock">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_stock'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_stock'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('products_quantity', $pInfo->products_quantity, 'id="products_quantity"'); ?>
                  </div>
                </div>
              </div>

              <div class="col-md-5" id="productsAlert">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_alert'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_alert'); ?></label>
                  <div class="col-md-5 input-group">
                    <?php echo HTML::inputField('products_quantity_alert', $pInfo->products_quantity_alert, 'id="products_quantity_alert"'); ?>
                    <a
                      href="<?php echo $CLICSHOPPING_Products->link('ConfigurationPopUpFields&cKey=STOCK_REORDER_LEVEL'); ?>"
                      data-bs-toggle="modal" data-refresh="true"
                      data-bs-target="#myModal2"><?php echo '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Products->getDef('icon_edit') . '"></i></h4>'; ?></a>
                    <div class="modal fade" id="myModal2" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
                         aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <div class="modal-body">
                            <div class="te"></div>
                          </div>
                        </div> <!-- /.modal-content -->
                      </div><!-- /.modal-dialog -->
                    </div><!-- /.modal -->
                  </div>
                </div>
              </div>
            </div>


            <div class="row" id="productsMinOrderQuantity">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_min_order_quantity'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_min_order_quantity'); ?></label>
                  <div class="col-md-5">
                    <?php
                    if ($pInfo->products_min_qty_order == '') {
                      $products_min_qty_order = 1;
                    } else {
                      $products_min_qty_order = $pInfo->products_min_qty_order;
                    }
                    echo HTML::inputField('products_min_qty_order', $products_min_qty_order, 'id="products_min_qty_order"');
                    ?>
                    <a
                      href="<?php echo $CLICSHOPPING_Products->link('ConfigurationPopUpFields&cKey=MAX_MIN_IN_CART'); ?>"
                      data-bs-toggle="modal" data-refresh="true"
                      data-bs-target="#myModal2"><?php echo '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Products->getDef('text_edit_default_configuration') . '"></i></h4>'; ?></a>
                    <div class="modal fade" id="myModal2" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
                         aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <div class="modal-body">
                            <div class="te"></div>
                          </div>
                        </div> <!-- /.modal-content -->
                      </div><!-- /.modal-dialog -->
                    </div><!-- /.modal -->
                  </div>
                </div>
              </div>
            </div>

            <div class="row" id="tab2ContentRow6">
              <div class="col-md-5">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_type'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_type'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::selectMenu('products_type', $stockable_dopdown, $pInfo->products_type); ?>
                  </div>
                </div>
              </div>
              <div class="mt-1"></div>
              <?php
              if ($pInfo->products_date_available != '') {
                $products_date_available = DateTime::toShortWithoutFormat($pInfo->products_date_available);
              } else {
                $products_date_available = $pInfo->products_date_available;
              }
              ?>
              <div class="col-md-5" id="productsDatAvailable">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_date_available'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_date_available'); ?></label>
                  <div class="col-md-5">
                    <?php echo HTML::inputField('products_date_available', $products_date_available, null, 'date'); ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-1"></div>
            <div class="col-md-5" id="productsPackaging">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_packaging'); ?>"
                       class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_packaging'); ?></label>
                <div class="col-md-5">
                  <?php echo HTML::selectField('products_packaging', $products_packaging_array, $pInfo->products_packaging); ?>
                </div>
              </div>
            </div>

            <div class="mt-1"></div>
            <div class="row" id="tab2ContentRow7"></div>
          </div>
          <div class="mt-1"></div>
          <div class="row" id="tab2ContentRow8"></div>
          <div class="mt-1"></div>
          <div class="alert alert-info" role="alert" id="titleHelpGeneral">
            <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_Products->getDef('title_help_general') . '"></i></h4> ' . $CLICSHOPPING_Products->getDef('title_help_general') ?></div>
            <div class="mt-1"></div>
            <div><?php echo $CLICSHOPPING_Products->getDef('help_stock'); ?></div>
          </div>
          <?php echo $CLICSHOPPING_Hooks->output('Products', 'ProductsContentTab2', null, 'display'); ?>
        </div>
        <?php
        // ******************************************
        // Tab 3 Price
        //*******************************************

        if (!isset($pInfo->products_percentage)) $pInfo->products_percentage = '1';

        switch ($pInfo->products_percentage) {
          case '0':
            $in_percent = false;
            $out_percent = true;
            break;
          case '1':
          default:
            $in_percent = true;
            $out_percent = false;
            break;
        }

        $tax_class_drop_down = Tax::taxClassDropDown();
        ?>
        <script>
          var tax_rates = new Array();
          <?php
          for ($i = 0, $n = \count($tax_class_drop_down); $i < $n; $i++) {
            if ($tax_class_drop_down[$i]['id'] > 0) {
              echo 'tax_rates["' . $tax_class_drop_down[$i]['id'] . '"] = ' . $tax->getTaxRateValue($tax_class_drop_down[$i]['id']) . ';' . "\n";
            }
          }
          ?>

          function doRound(x, places) {
            return Math.round(x * Math.pow(10, places)) / Math.pow(10, places);
          }

          function getTaxRate() {
            var selected_value = document.forms["new_product"].products_tax_class_id.selectedIndex;
            var parameterVal = document.forms["new_product"].products_tax_class_id[selected_value].value;

            if ((parameterVal > 0) && (tax_rates[parameterVal] > 0)) {
              return tax_rates[parameterVal];
            } else {
              return 0;
            }
          }

          function updateGross() {
            var taxRate = getTaxRate();
            var grossValue = document.forms["new_product"].products_price.value;

            if (taxRate > 0) {
              grossValue = grossValue * ((taxRate / 100) + 1);
            }

            <?php
            // Desactivation du module B2B
            if (MODE_B2B_B2C == 'True') {
            $QcustomersGroup = $CLICSHOPPING_Products->db->prepare('select distinct customers_group_id,
                                                                                         customers_group_name,
                                                                                         customers_group_discount
                                                                         from :table_customers_groups
                                                                         where customers_group_id != 0
                                                                         order by customers_group_id
                                                                        ');

            $QcustomersGroup->execute();

            while ($QcustomersGroup->fetch()) {
            ?>
            var grossValue<?php echo $QcustomersGroup->valueInt('customers_group_id'); ?> = document.forms["new_product"].price<?php echo $QcustomersGroup->valueInt('customers_group_id'); ?>.value;

            if (taxRate > 0) {
              grossValue<?php echo $QcustomersGroup->valueInt('customers_group_id'); ?> = grossValue<?php echo $QcustomersGroup->valueInt('customers_group_id'); ?> * ((taxRate / 100) + 1);
            }

            document.forms["new_product"].price_gross<?php echo $QcustomersGroup->valueInt('customers_group_id'); ?>.value = doRound(grossValue<?php echo $QcustomersGroup->valueInt('customers_group_id'); ?>, 4);
            <?php
            }
            }
            ?>
            document.forms["new_product"].products_price_gross.value = doRound(grossValue, 4);
          }

          /********************************/
          /*        Margin report   */
          /********************************/
          function updateMargin() {
            var grossValue = document.forms["new_product"].products_price.value; // valeur net du prix
            var costValue = document.forms["new_product"].products_cost.value; // cout d'achat
            var handlingValue = document.forms["new_product"].products_handling.value; // manutention ou autres frais

            if (isNaN(costValue)) costValue = 0;
            if (isNaN(handlingValue)) handlingValue = 0;

            marginValue = 100 - (((parseInt(costValue) + parseInt(handlingValue)) / parseInt(grossValue)) * 100);
            marginValue = Math.round(marginValue, 2);
            document.getElementById('products_price_margins').innerHTML = marginValue + "%";
          }

          function updateNet() {
            var taxRate = getTaxRate();
            var netValue = document.forms["new_product"].products_price_gross.value;

            if (taxRate > 0) {
              netValue = netValue / ((taxRate / 100) + 1);
            }

            <?php
            // Desactivation du module B2B
            if (MODE_B2B_B2C == 'True') {
            $QcustomersGroup = $CLICSHOPPING_Products->db->prepare('select distinct customers_group_id,
                                                                                        customers_group_name,
                                                                                        customers_group_discount
                                                                         from :table_customers_groups
                                                                         where customers_group_id != 0
                                                                         order by customers_group_id
                                                                        ');
            $QcustomersGroup->execute();

            while ($QcustomersGroup->fetch()) {
            ?>
            var netValue<?php echo $QcustomersGroup->valueInt('customers_group_id'); ?> = document.forms["new_product"].price_gross<?php echo $QcustomersGroup->valueInt('customers_group_id'); ?>.value;

            if (taxRate > 0) {
              netValue<?php echo $QcustomersGroup->valueInt('customers_group_id'); ?> = netValue<?php echo $QcustomersGroup->valueInt('customers_group_id'); ?> / ((taxRate / 100) + 1);
            }

            document.forms["new_product"].price<?php echo $QcustomersGroup->valueInt('customers_group_id'); ?>.value = doRound(netValue<?php echo $QcustomersGroup->valueInt('customers_group_id'); ?>, 4);
            <?php
            }
            }
            ?>

            document.forms["new_product"].products_price.value = doRound(netValue, 4);
          }
        </script>

        <div class="tab-pane" id="tab4">
          <div class="col-md-12 mainTitle">
            <span><?php echo $CLICSHOPPING_Products->getDef('text_products_price_public'); ?></span>
          </div>
          <div class="adminformTitle" style="padding-top: 0rem; padding-left: 0rem; padding-bottom: 0rem;">
            <div style="background-color:#ebebff; height:100%;">
              <div class="mt-1"></div>
              <div class="row" id="productsTaxtClass">
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_tax_class'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_tax_class'); ?></label>
                    <div class="col-md-5">
                      <?php echo HTML::selectMenu('products_tax_class_id', $tax_class_drop_down, $pInfo->products_tax_class_id, 'onchange="updateGross()"'); ?>
                    </div>
                  </div>
                </div>
              </div>
              <div class="mt-1"></div>
              <div class="row" id="productsPrice">
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_price'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_price'); ?></label>
                    <div class="col-md-5">
                      <?php
                      echo HTML::inputField('products_price', $pInfo->products_price, 'id="products_price" onkeyup="updateGross()"') . '<strong>' . $CLICSHOPPING_Products->getDef('text_products_price_net') . '</strong>';
                      ?>
                    </div>
                  </div>
                </div>

                <div class="col-md-5" id="productsPriceGross">
                  <div class="form-group row">
                    <div class="col-md-5">
                      <?php
                      if (DISPLAY_DOUBLE_TAXE == 'false') {
                        echo HTML::inputField('products_price_gross', $pInfo->products_price, 'id="products_price_gross" onkeyup="updateNet()"') . '<strong>' . $CLICSHOPPING_Products->getDef('text_products_price_gross') . '</strong>';
                      }
                      ?>
                    </div>
                  </div>
                </div>
                <div id="ProductPriceOption"></div>
              </div>

              <div class="mt-1"></div>
              <div class="row" id="productsCost">
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_cost'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_cost'); ?></label>
                    <div class="col-md-5">
                      <?php echo HTML::inputField('products_cost', $pInfo->products_cost, 'id="products_cost" onkeyUp="updateMargin()"') . '<strong>' . $CLICSHOPPING_Products->getDef('text_products_price_net') . '</strong>'; ?>
                    </div>
                  </div>
                </div>
              </div>

              <div class="mt-1"></div>
              <div class="row" id="productsHandling">
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_handling'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_handling'); ?></label>
                    <div class="col-md-5">
                      <?php echo HTML::inputField('products_handling', $pInfo->products_handling, 'id="products_handling" onkeyUp="updateMargin()"') . '<strong>' . $CLICSHOPPING_Products->getDef('text_products_price_net') . '</strong>'; ?>
                    </div>
                  </div>
                </div>
              </div>

              <div class="mt-1"></div>
              <div class="row" id="productsPriceMargins">
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_price_margins'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_price_margins'); ?></label>
                    <div class="col-md-5">
                      <span id='products_price_margins'></span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="mt-1"></div>
              <div class="row" id="productsView">
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Products->getDef('products_view'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('products_view'); ?></label>
                    <div class="col-md-7">
                      <?php
                      if (isset($_GET['pID'])) {
                        ?>
                        <ul class="list-group-slider list-group-flush">
                          <li class="list-group-item-slider">
                            <label class="switch">
                              <?php echo HTML::checkboxField('products_view', '1', $pInfo->products_view, 'class="success"'); ?>
                              <span class="slider"></span>
                            </label>
                          </li>
                          <span
                            class="text-slider"><?php echo $CLICSHOPPING_Products->getDef('text_products_view'); ?></span>
                        </ul>
                        <ul class="list-group-slider list-group-flush">
                          <li class="list-group-item-slider">
                            <label class="switch">
                              <?php echo HTML::checkboxField('orders_view', '1', $pInfo->orders_view, 'class="success"'); ?>
                              <span class="slider"></span>
                            </label>
                          </li>
                          <span
                            class="text-slider"><?php echo $CLICSHOPPING_Products->getDef('tab_orders_view'); ?></span>
                        </ul>
                        <?php
                      } else {
                        ?>
                        <ul class="list-group-slider list-group-flush">
                          <li class="list-group-item-slider">
                            <label class="switch">
                              <?php echo HTML::checkboxField('products_view', '1', true, 'class="success"'); ?>
                              <span class="slider"></span>
                            </label>
                          </li>
                          <span
                            class="text-slider"><?php echo $CLICSHOPPING_Products->getDef('text_products_view'); ?></span>
                        </ul>
                        <ul class="list-group-slider list-group-flush">
                          <li class="list-group-item-slider">
                            <label class="switch">
                              <?php echo HTML::checkboxField('orders_view', '1', true, 'class="success"'); ?>
                              <span class="slider"></span>
                            </label>
                          </li>
                          <span
                            class="text-slider"><?php echo $CLICSHOPPING_Products->getDef('tab_orders_view'); ?></span>
                        </ul>
                        <?php
                      }
                      ?>
                    </div>
                  </div>
                </div>
              </div>

              <div class="mt-1"></div>
              <div class="row" id="productsPriceKilo">
                <div class="col-md-5">
                  <div class="form-group row">
                    <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_price_kilo'); ?>"
                           class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_price_kilo'); ?></label>
                    <div class="col-md-5">
                      <ul class="list-group-slider list-group-flush">
                        <li class="list-group-item-slider">
                          <label class="switch">
                            <?php echo HTML::checkboxField('products_price_kilo', '1', $pInfo->products_price_kilo, 'class="success"'); ?>
                            <span class="slider"></span>
                          </label>
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div id="PriceB2B">
              <?php echo $CLICSHOPPING_Hooks->output('Products', 'CustomerGroupTab3', null, 'display'); ?>
            </div>
            <script>updateGross();</script>
          </div>
          <?php echo $CLICSHOPPING_Hooks->output('Products', 'ProductsContentTab3', null, 'display'); ?>
        </div>

        <?php
        // ******************************************
        // Tab 4 Description
        //*******************************************
        ?>
        <div class="tab-pane" id="tab5">
          <div class="col-md-12 mainTitle">
            <span><?php echo $CLICSHOPPING_Products->getDef('text_products_description'); ?></span>
          </div>
          <div class="adminformTitle">
            <div class="mt-1"></div>
            <div class="accordion" id="accordionExample">
              <?php
              for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                $languages_id = $languages[$i]['id'];
                ?>
                <div class="accordion-item">
                  <h2 class="accordion-header" id="heading<?php echo $i; ?>">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                      <?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?>
                    </button>
                  </h2>
                  <?php
                  if ($i == 0) {
                    $show = ' show';
                  } else {
                    $show = '';
                  }
                  ?>
                  <div id="collapseOne" class="accordion-collapse collapse <?php echo $show; ?>"
                       aria-labelledby="heading<?php $i; ?>" data-bs-parent="#accordionExample">
                    <div class="accordion-body">
                      <?php
                      $name = 'products_description[' . $languages_id . ']';
                      $ckeditor_id = $CLICSHOPPING_Wysiwyg::getWysiwygId($name);

                      echo $CLICSHOPPING_Wysiwyg::textAreaCkeditor($name, 'soft', '750', '300', (isset($products_description[$languages_id]) ? str_replace('& ', '&amp; ', trim($products_description[$languages_id])) : $CLICSHOPPING_ProductsAdmin->getProductsDescription($pInfo->products_id, $languages_id)), 'id="' . $ckeditor_id . '"');
                      ?>
                      <div class="mt-1"></div>
                      <div class="row" id="DescriptionSummaryTitle<?php echo $languages_id; ?>">
                        <div class="col-md-6">
                          <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                            <label
                              for="<?php echo $CLICSHOPPING_Products->getDef('text_products_description_summary'); ?>"
                              class="col-12 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_description_summary'); ?></label>
                            <div class="col-md-8 input-group" id="SummaryDescription<?php echo $languages_id; ?>">
                              <?php echo HTML::textAreaField('products_description_summary[' . $languages_id . ']', (isset($products_description_summary[$languages_id]) ? str_replace('& ', '&amp; ', trim($products_description_summary[$languages_id])) : $CLICSHOPPING_ProductsAdmin->getProductsDescriptionSummary($pInfo->products_id, $languages_id)), '120', '5', 'id="SummaryDescription_' . $languages_id . '"'); ?>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <?php
              }
              ?>
            </div>
          </div>
          <div class="mt-1"></div>
          <?php echo $CLICSHOPPING_Hooks->output('Products', 'ProductsContentTab4', null, 'display'); ?>
        </div>

        <?php
        // ******************************************
        // Tab 5 Image
        //*******************************************
        ?>
        <div class="tab-pane" id="tab6">
          <div class="mainTitle"><?php echo $CLICSHOPPING_Products->getDef('text_products_image'); ?></div>
          <div class="adminformTitle">
            <div class="row" id="tab5ContentRow1">
              <div class="col-md-6 float-start">
                <div class="col-md-12">
                  <span
                    class="col-sm-1"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'images_product.gif', $CLICSHOPPING_Products->getDef('text_products_image_vignette'), '40', '40'); ?></span>
                  <span
                    class="col-sm-6"><?php echo $CLICSHOPPING_Products->getDef('text_products_insert_big_image_vignette'); ?></span>
                </div>
                <div class="mt-1"></div>
                <div class="col-md-12">
                  <span
                    class="col-sm-6"><?php echo $CLICSHOPPING_Products->getDef('text_products_image_directory'); ?></span>
                  <span
                    class="col-sm-6"><?php echo HTML::selectMenu('directory_products_image', $CLICSHOPPING_ProductsAdmin->getDirectoryProducts()); ?><span>
                </div>
                <div class="col-md-12">
                  <span
                    class="col-sm-6"><?php echo $CLICSHOPPING_Products->getDef('text_products_image_new_folder'); ?></span>
                  <span
                    class="col-sm-6"><?php echo HTML::inputField('new_directory_products_image', '', 'class="form-control"'); ?><span>
                </div>
                <div class="col-md-12">
                  <span
                    class="col-sm-6"><?php echo $CLICSHOPPING_Products->getDef('text_products_main_image'); ?></span>
                  <span class="col-sm-6"><?php echo HTML::fileField('products_image_resize', 'id="file"'); ?></span>
                </div>
              </div>
              <div class="col-md-5 float-end">
                <div class="col-md-12">
                  <span
                    class="col-sm-1"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'images_product_images.gif', $CLICSHOPPING_Products->getDef('text_products_image_visuel'), '40', '40'); ?></span>
                  <span
                    class="col-sm-6"><?php echo $CLICSHOPPING_Products->getDef('text_products_image_visuel'); ?></span>
                </div>
                <div class="col-md-11">
                  <div
                    class="col-sm-12 text-center"><?php echo $CLICSHOPPING_ProductsAdmin->getInfoImage($pInfo->products_image, $CLICSHOPPING_Products->getDef('text_products_image_vignette'), '150', '150') . HTML::hiddenField('products_image', $pInfo->products_image); ?></div>
                  <div
                    class="col-sm-12 text-center"><?php echo 'URL : ' . HTTP::getShopUrlDomain() . 'sources/images/' . $pInfo->products_image; ?></div>
                  <div class="mt-1"></div>
                  <div class="col-sm-12 text-center">
                    <?php echo $CLICSHOPPING_Products->getDef('text_products_delete_image') . ' ' . HTML::checkboxField('delete_image', 'yes', false); ?>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-1"></div>
          <?php
          // -----------------------------------
          // Gallery
          // -----------------------------------
          ?>
          <div class="mt-1"></div>
          <div class="mainTitle"><?php echo $CLICSHOPPING_Products->getDef('text_products_gallery_image'); ?></div>
          <div class="adminformTitle">
            <div id="tab5ContentRow2">
              <div class="col-md-12">
                <div class="row">
                  <ul id="piList"></ul>
                  <a class="linkHandle" data-action="addNewPiForm"><i
                      class="bi bi-plus-circle-fill"></i>&nbsp;<?php echo $CLICSHOPPING_Products->getDef('text_products_add_large_image'); ?>
                  </a>
                </div>
              </div>
              <div class="mt-1"></div>

              <script id="templateLargeImage" type="x-tmpl-mustache">
                <li id="piId{{counter}}">
                  <div class="piActions float-end">
                    <a class="linkHandle" data-piid="{{counter}}" data-action="showPiDelConfirm" data-state="active"><i class="bi bi-trash-fill" title="<?php echo $CLICSHOPPING_Products->getDef('image_delete'); ?>"></i></a>
                    <a class="sortHandle" data-state="active"><i class="bi bi-arrows-move" title="<?php echo $CLICSHOPPING_Products->getDef('image_move'); ?>"></i></a>
                    <a class="linkHandle" data-piid="{{counter}}" data-action="undoDelete" data-state="inactive"><i class="bi bi-skip-backward-fill" title="<?php echo $CLICSHOPPING_Products->getDef('image_undo'); ?>"></i></a>
                  </div>
                  <strong><?php echo $CLICSHOPPING_Products->getDef('text_products_large_image'); ?></strong><br />
                <?php echo HTML::fileField('{{input_file_name}}'); ?><br />
                  {{#image}}<a href="<?php echo $CLICSHOPPING_Template->getDirectoryShopTemplateImages(); ?>
                {{image}}" target="_blank">{{image}}</a><br /><br />{{/image}}
                <?php echo $CLICSHOPPING_Products->getDef('text_products_large_image_html_content'); ?><br />
                <?php echo HTML::textareaField('{{input_html_content_name}}', '{{html_content}}', '200', '3', null, false); ?>
                </li>
              </script>

              <div class="modal" tabindex="-1" role="dialog" id="piDelConfirm">
                <div class="modal-dialog" role="document">
                  <div class="modal-content">
                    <div class="modal-header">
                      <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span
                          aria-hidden="true">&times;</span></button>
                      <h4
                        class="modal-title"><?php echo $CLICSHOPPING_Products->getDef('text_products_large_image_delete_title'); ?></h4>
                    </div>

                    <div class="modal-body">
                      <p><?php echo $CLICSHOPPING_Products->getDef('text_products_large_image_confirm_delete'); ?></p>
                    </div>

                    <div class="modal-footer">
                      <button type="button" class="btn btn-danger"
                              id="piDelConfirmButtonDelete"><?= $CLICSHOPPING_Products->getDef('button_delete'); ?></button>
                      <button type="button" class="btn btn-warning"
                              data-bs-dismiss="modal"><?= $CLICSHOPPING_Products->getDef('button_cancel'); ?></button>
                    </div>
                  </div>
                </div>
              </div>

              <style type="text/css">
                #piList {
                  list-style-type: none;
                  margin: 0;
                  padding: 0;
                }

                #piList li {
                  margin: 15px 0;
                  padding: 10px;
                }
              </style>

              <script>
                $(function () {
                  const templateLargeImage = $('#templateLargeImage').html();
                  Mustache.parse(templateLargeImage);

                  <?php
                  $pi_array = [];

                  foreach ($pInfo->products_larger_images as $pi) {
                    $pi_array[] = [
                      'counter' => count($pi_array) + 1,
                      'input_file_name' => 'products_image_large_' . $pi['id'],
                      'input_html_content_name' => 'products_image_htmlcontent_' . $pi['id'],
                      'image' => $pi['image'],
                      'html_content' => $pi['htmlcontent']
                    ];
                  }

                  echo '  var piArray = ' . json_encode($pi_array) . ';';
                  ?>

                  $.each(piArray, function (k, v) {
                    $('#piList').append(Mustache.render(templateLargeImage, v));
                  });

                  $('#piList .piActions a[data-state="inactive"]').hide();

                  Sortable.create($('#piList')[0], {
                    handle: '.sortHandle'
                  });

                  $('#tab6 a[data-action="addNewPiForm"]').on('click', function () {
                    const piSize = $('#piList li').length + 1;

                    const data = {
                      counter: piSize,
                      input_file_name: 'products_image_large_new_' + piSize,
                      input_html_content_name: 'products_image_htmlcontent_new_' + piSize
                    };

                    $('#piList').append(Mustache.render(templateLargeImage, data));

                    $('#piId' + piSize + ' .piActions a[data-state="inactive"]').hide();
                  });

                  $('#tab6').on('click', '#piList li a[data-action="showPiDelConfirm"]', function () {
                    $('#piDelConfirm').data('piid', $(this).data('piid'));

                    $('#piDelConfirm').modal('show');
                  });

                  $('#tab6').on('click', '#piList li a[data-action="undoDelete"]', function () {
                    $('#piId' + $(this).data('piid') + ' .piActions a[data-state="inactive"]').hide();
                    $('#piId' + $(this).data('piid') + ' .piActions a[data-state="active"]').show();
                    $('#piId' + $(this).data('piid') + ' :input').prop('disabled', false);
                    $('#piId' + $(this).data('piid')).removeClass('bg-danger').addClass('bg-warning');
                  });

                  $('#piDelConfirmButtonDelete').on('click', function () {
                    $('#piId' + $('#piDelConfirm').data('piid')).removeClass('bg-warning').addClass('bg-danger');
                    $('#piId' + $('#piDelConfirm').data('piid') + ' :input').prop('disabled', true);
                    $('#piId' + $('#piDelConfirm').data('piid') + ' .piActions a[data-state="active"]').hide();
                    $('#piId' + $('#piDelConfirm').data('piid') + ' .piActions a[data-state="inactive"]').show();

                    $('#piDelConfirm').modal('hide');
                  });
                });
              </script>

              <?php
              // -----------------------------------
              // Manual image
              // -----------------------------------
              if (MANUAL_IMAGE_PRODUCTS_DESCRIPTION == 'true') {
                ?>
                <div
                  class="mainTitle"><?php echo $CLICSHOPPING_Products->getDef('text_products_image_customize'); ?></div>
                <div class="adminformTitle">
                  <div class="row" id="tab5ContentRow7">
                    <div class="col-md-6 float-start">
                      <div class="col-md-12">
                          <span
                            class="col-sm-1"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'images_product.gif', $CLICSHOPPING_Products->getDef('text_products_image_vignette'), '40', '40'); ?></span>
                        <span
                          class="col-sm-6"><?php echo $CLICSHOPPING_Products->getDef('text_products_insert_big_image_vignette'); ?></span>
                      </div>
                      <div class="mt-1"></div>
                      <div class="col-md-12 adminformAide">
                        <div class="col-md-12 text-center">
                            <span
                              class="col-sm-12 text-center"><?php echo $CLICSHOPPING_Wysiwyg::fileFieldImageCkEditor('products_image', null, '100', '100'); ?></span>
                        </div>
                        <div class="col-md-12">
                            <span
                              class="col-sm-1"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'images_product_zoom.gif', $CLICSHOPPING_Products->getDef('text_products_image_medium'), '40', '40'); ?></span>
                          <span
                            class="col-sm-6"><?php echo $CLICSHOPPING_Products->getDef('text_products_image_medium'); ?></span>
                        </div>
                        <div class="col-md-12">
                            <span
                              class="col-sm-6"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'images_product_zoom.gif', $CLICSHOPPING_Products->getDef('text_products_image_zoom'), '40', '40'); ?></span>
                          <span
                            class="col-sm-6"><?php echo $CLICSHOPPING_Products->getDef('text_products_image_zoom'); ?></span>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-6 float-start">
                      <div class="col-md-12">
                          <span
                            class="col-sm-12"><?php echo $CLICSHOPPING_ProductsAdmin->getInfoImage($pInfo->products_image, $CLICSHOPPING_Products->getDef('text_products_image_vignette')); ?></span>
                        <div class="col-md-12 text-center">
                            <span
                              class="col-sm-12"><?php echo $CLICSHOPPING_Products->getDef('text_products_no_image_visuel_zoom'); ?></span>
                        </div>
                        <div class="col-md-12 text-end">
                            <span
                              class="col-sm-12"><?php echo $CLICSHOPPING_Products->getDef('text_products_delete_image') . ' ' . HTML::checkboxField('delete_image', 'yes', false); ?></span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <?php
              }
              ?>
            </div>
          </div>
          <div class="mt-1"></div>
          <div>
            <?php echo $CLICSHOPPING_Hooks->output('Products', 'ProductsContentTab5', null, 'display'); ?>
          </div>
          <div class="mt-1"></div>
          <div class="alert alert-info" role="alert">
            <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_Products->getDef('title_help_image') . '"></i></h4> ' . $CLICSHOPPING_Products->getDef('title_help_image') ?></div>
            <div class="mt-1"></div>
            <div><?php echo $CLICSHOPPING_Products->getDef('title_help_products'); ?></div>
          </div>
        </div>
        <?php
        // ******************************************
        // Tab 6 Meta Datas
        //*******************************************
        ?>
        <div class="tab-pane" id="tab7">
          <div class="mainTitle"><?php echo $CLICSHOPPING_Products->getDef('text_products_page_seo'); ?></div>
          <div class="adminformTitle">
            <div class="col-md-12">
              <div class="row text-center" id="productsGoogleKeywords">
                <a href="https://www.google.fr/trends"
                   target="_blank"><?php echo $CLICSHOPPING_Products->getDef('keywords_google_trend'); ?></a>
              </div>
            </div>
            <div class="mt-1"></div>

            <div class="accordion" id="accordionExample">
              <?php
              for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                $languages_id = $languages[$i]['id'];
                ?>
                <div class="accordion-item">
                  <h2 class="accordion-header" id="heading<?php echo $i; ?>">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                      <?php echo $CLICSHOPPING_Language->getImage($languages[$i]['code']); ?>
                    </button>
                  </h2>
                  <?php
                  if ($i == 0) {
                    $show = ' show';
                  } else {
                    $show = '';
                  }
                  ?>
                  <div id="collapseOne" class="accordion-collapse collapse <?php echo $show; ?>"
                       aria-labelledby="heading<?php $i; ?>" data-bs-parent="#accordionExample">
                    <div class="accordion-body">
                      <div class="row" id="productsSeoUrl<?php echo $i ?>">
                        <div class="col-md-10">
                          <div class="form-group row">
                            <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_seo_url'); ?>"
                                   class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_seo_url'); ?></label>
                            <div class="col-md-7 input-group">
                              <?php echo '&nbsp;' . HTML::inputField('products_seo_url[' . $languages_id . ']', SeoAdmin::getProductsSeoUrl($pInfo->products_id, $languages_id), 'maxlength="70" size="77" id="seo_url_title_' . $i . '"', false); ?>
                            </div>
                          </div>
                        </div>
                      </div>

                      <div class="mt-1"></div>
                      <div class="row" id="productsSeoTitle<?php echo $languages_id; ?>">
                        <div class="col-md-10">
                          <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                            <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_page_title'); ?>"
                                   class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_page_title'); ?></label>
                            <div class="col-md-7 input-group" id="products_head_title_tag<?php echo $languages_id; ?>">
                              <?php echo '&nbsp;' . HTML::inputField('products_head_title_tag[' . $languages_id . ']', SeoAdmin::getProductsSeoTitle($pInfo->products_id, $languages_id), 'maxlength="70" size="77" id="products_head_title_tag_' . $languages_id . '"', false); ?>
                            </div>
                          </div>
                        </div>
                      </div>

                      <div class="mt-1"></div>
                      <div class="row" id="productsSeoDescription<?php echo $languages_id; ?>">
                        <div class="col-md-6">
                          <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                            <label
                              for="<?php echo $CLICSHOPPING_Products->getDef('text_products_header_description'); ?>"
                              class="col-1 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_header_description'); ?></label>
                            <div class="col-md-8 input-group" id="products_head_desc_tag<?php echo $languages_id; ?>">
                              <?php echo HTML::textAreaField('products_head_desc_tag[' . $languages_id . ']', SeoAdmin::getProductsSeoDescription($pInfo->products_id, $languages_id), '110', '5', 'id="products_head_desc_tag_' . $languages_id . '"'); ?>
                            </div>
                          </div>
                        </div>
                      </div>

                      <div class="mt-1"></div>
                      <div class="row" id="productsSeoKeywords<?php echo $languages_id; ?>">
                        <div class="col-md-10">
                          <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                            <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_keywords'); ?>"
                                   class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_keywords'); ?></label>
                            <div class="col-md-7 input-group"
                                 id="products_head_keywords_tag<?php echo $languages_id; ?>">
                              <?php echo HTML::inputField('products_head_keywords_tag[' . $languages_id . ']', SeoAdmin::getProductsSeoKeywords($pInfo->products_id, $languages_id), 'maxlength="70" size="77" id="products_head_keywords_tag_' . $languages_id . '"', false); ?>
                            </div>
                          </div>
                        </div>
                      </div>

                      <div class="mt-1"></div>
                      <div class="row" id="productsSeoTag<?php echo $languages_id; ?>">
                        <div class="col-md-10">
                          <div class="form-group row" data-index="<?php echo $languages_id; ?>">
                            <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_tag'); ?>"
                                   class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_tag'); ?></label>
                            <div class="col-md-7 input-group" id="products_head_tag<?php echo $languages_id; ?>">
                              <?php echo '&nbsp;' . HTML::inputField('products_head_tag[' . $languages_id . ']', SeoAdmin::getProductsSeoTag($pInfo->products_id, $languages_id), 'maxlength="70" size="77" id="products_head_tag_' . $languages_id . '"', false); ?>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <?php
              }
              ?>
            </div>
            <div>
              <div class="mt-1"></div>
              <?php echo $CLICSHOPPING_Hooks->output('Products', 'ProductsContentTab6', null, 'display'); ?>
            </div>
          </div>
        </div>
        <?php
        // ******************************************
        // Tab 9 Options
        //*******************************************
        if (!empty($_GET['pID'])) {
          $Qproducts = $CLICSHOPPING_Products->db->prepare('select p.products_id,
                                                                     pd.products_name,
                                                                     p.products_model
                                                              from :table_products p,
                                                                   :table_products_description pd
                                                              where pd.products_id = p.products_id
                                                              and pd.language_id = :language_id
                                                              and pd.products_id = :products_id
                                                              order by pd.products_name
                                                            ');
          $Qproducts->bindInt(':language_id', $CLICSHOPPING_Language->getId());
          $Qproducts->bindInt(':products_id', $_GET['pID']);
          $Qproducts->execute();


          $Qcategories = $CLICSHOPPING_Products->db->prepare('select c.categories_id,
                                                               cd.categories_name
                                                        from :table_categories c,
                                                             :table_categories_description cd
                                                        where cd.categories_id = c.categories_id
                                                        and cd.language_id = :language_id
                                                        and c.categories_id <> :categories_id
                                                       ');
          $Qcategories->bindInt(':language_id', $CLICSHOPPING_Language->getId());
          $Qcategories->bindInt(':categories_id', $cPath);
          $Qcategories->execute();
        }
        ?>
        <div class="tab-pane" id="tab8">
          <div class="mainTitle"><?php echo $CLICSHOPPING_Products->getDef('text_products_others_options'); ?></div>
          <div class="adminformTitle" id="tab9Content">

            <div class="row" id="productsFileDownloadPublic">
              <div class="col-md-9">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_file_download_public'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_file_download_public'); ?></label>
                  <div class="col-md-5">
                    <ul class="list-group-slider list-group-flush">
                      <li class="list-group-item-slider">
                        <label class="switch">
                          <?php echo HTML::checkboxField('products_download_public', '1', $pInfo->products_download_public, 'class="success"'); ?>
                          <span class="slider"></span>
                        </label>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>

            <div class="row" id="productsFileDownload">
              <div class="col-md-9">
                <div class="form-group row">
                  <label for="<?php echo $CLICSHOPPING_Products->getDef('text_products_file_download'); ?>"
                         class="col-5 col-form-label"><?php echo $CLICSHOPPING_Products->getDef('text_products_file_download'); ?></label>
                  <div class="col-md-5">
                    <?php
                    echo HTML::fileField('products_download_filename', 'id="download_file" accept=".zip, .pdf, .doc, .odf, .xlsx., xls, .mp3, .mp4, .avi, .png, .jpg, .gif"');

                    if ($pInfo->products_download_filename) {
                      echo HTML::inputField('products_download_filename', $pInfo->products_download_filename, 'id="products_download_filename" disabled="disabled"') . '&nbsp';
                    }
                    ?>
                  </div>
                </div>
              </div>
            </div>

            <div class="row" id="productsOnlyShop">
              <div class="col-md-9">
                <ul class="list-group-slider list-group-flush">
                  <span
                    class="text-slider col-5"><?php echo $CLICSHOPPING_Products->getDef('text_products_only_shop'); ?></span>
                  <li class="list-group-item-slider">
                    <label class="switch">
                      <?php echo HTML::checkboxField('products_only_shop', '1', $pInfo->products_only_shop, 'class="success"'); ?>
                      <span class="slider"></span>
                    </label>
                  </li>
                </ul>
              </div>
            </div>
            <div class="row" id="productsOnlyOnline">
              <div class="col-md-9">
                <ul class="list-group-slider list-group-flush">
                  <span
                    class="text-slider col-5"><?php echo $CLICSHOPPING_Products->getDef('text_products_only_online'); ?></span>
                  <li class="list-group-item-slider">
                    <label class="switch">
                      <?php echo HTML::checkboxField('products_only_online', '1', $pInfo->products_only_online, 'class="success"'); ?>
                      <span class="slider"></span>
                    </label>
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <div class="mt-1"></div>
          <div class="alert alert-info" role="alert">
            <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_Products->getDef('title_help_general') . '"></i></h4> ' . $CLICSHOPPING_Products->getDef('title_help_general') ?></div>
            <div class="mt-1"></div>
            <div><?php echo $CLICSHOPPING_Products->getDef('help_general_tab8') . ' ' . $CLICSHOPPING_Products->getDef('help_general_tab8_1') . ' ' . (int)(ini_get('upload_max_filesize')) . ' Mb'; ?></div>
          </div>
        </div>
      </div>
      <?php echo $CLICSHOPPING_Hooks->output('Products', 'ProductsContentTab7', null, 'display'); ?>
      <?php echo $CLICSHOPPING_Hooks->output('Products', 'ProductsContentTab8', null, 'display'); ?>

      </div>
    </div>
    <?php
    //***********************************
    // extension
    //***********************************
    echo $CLICSHOPPING_Hooks->output('Products', 'PageTab', null, 'display');
    ?>
  </div>
</div>
</form>
<div class="py-4">s</div>