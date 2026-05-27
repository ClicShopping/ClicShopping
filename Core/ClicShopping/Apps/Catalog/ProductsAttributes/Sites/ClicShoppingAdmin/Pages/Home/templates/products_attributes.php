<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\FileSystem;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\ProductsAttributes\Classes\ClicShoppingAdmin\ProductsAttributesAdmin;
use ClicShopping\Apps\Customers\Groups\Classes\ClicShoppingAdmin\GroupsB2BAdmin;

$CLICSHOPPING_ProductsAttributes = Registry::get('ProductsAttributes');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_ProductsAdmin = Registry::get('ProductsAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Wysiwyg = Registry::get('Wysiwyg');
$CLICSHOPPING_MessageStack  = Registry::get('MessageStack');

$CLICSHOPPING_ProductsAttributesAdmin = new ProductsAttributesAdmin;

$languages = $CLICSHOPPING_Language->getLanguages();

$action = $_GET['action'] ?? '';

$option_page = (isset($_GET['option_page']) && is_numeric($_GET['option_page'])) ? $_GET['option_page'] : 1;
$value_page = (isset($_GET['value_page']) && is_numeric($_GET['value_page'])) ? $_GET['value_page'] : 1;
$attribute_page = (isset($_GET['attribute_page']) && is_numeric($_GET['attribute_page'])) ? $_GET['attribute_page'] : 1;
$page_info = 'option_page=' . HTML::sanitize($option_page) . '&value_page=' . HTML::sanitize($value_page) . '&attribute_page=' . HTML::sanitize($attribute_page);

$upload_max_filesize = ini_get('upload_max_filesize');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

echo $CLICSHOPPING_Wysiwyg::getWysiwyg();
?>
<div class="contentBody">
  <?php
  // check if the catalog image directory exists
  if (DOWNLOAD_ENABLED == 'true') {
    if (is_dir($CLICSHOPPING_Template->getDirectoryPathTemplateShopImages())) {
      if (!FileSystem::isWritable($CLICSHOPPING_Template->getPathDownloadShopDirectory('Private'))) $CLICSHOPPING_MessageStack->add($CLICSHOPPING_ProductsAttributes->getDef('error_catalog_download_directory_not_writeable'), 'warning');
    }
  }

  if (isset($_GET['error']) && $_GET['error'] == 'fileNotSupported') {
    ?>
    <div class="alert alert-warning"
         role="alert"><?php echo $CLICSHOPPING_ProductsAttributes->getDef('error_file_not_supported'); ?></div>
    <?php
  }
  ?>
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/products_options.gif', CLICSHOPPING::getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ProductsAttributes->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div id="categoriesTabs" style="overflow: auto;" data-go-option-url="<?php echo $CLICSHOPPING_ProductsAttributes->link('productsAttributes&option_page=' . $option_page); ?>">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li
        class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_ProductsAttributes->getDef('tab_step1') . '</a>'; ?></li>
      <li
        class="nav-item"><?php echo '<a href="#tab2" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_ProductsAttributes->getDef('tab_step2') . '</a>'; ?></li>
      <li
        class="nav-item"><?php echo '<a href="#tab3" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_ProductsAttributes->getDef('tab_step3'); ?></a></li>
      <li
        class="nav-item"><?php echo '<a href="#tab4" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_ProductsAttributes->getDef('tab_step4'); ?></a></li>
    </ul>
    <div class="tabsClicShopping">
      <div class="tab-content">
        <!-- //########################################################################################## -->
        <!-- //                  Option des produit : etape 1                                            -->
        <!-- //######################################################################################### -->
        <div class="tab-pane active" id="tab1">
          <table class="table table-sm table-hover table-striped">
            <!-- options //-->
            <?php
            $products_options_type = $CLICSHOPPING_ProductsAttributesAdmin->setAttributeType();
            $products_options_type_labels = array_column($products_options_type, 'text', 'id');

            if (isset($_GET['DeleteProductOption'])) { // delete product option
              $QoptionValues = $CLICSHOPPING_ProductsAttributes->db->prepare('select products_options_id,
                                                                                    products_options_name
                                                                             from :table_products_options
                                                                             where products_options_id = :products_options_id
                                                                             and language_id = :language_id
                                                                            ');
              $QoptionValues->bindInt(':products_options_id', (int)$_GET['option_id']);
              $QoptionValues->bindInt(':language_id', $CLICSHOPPING_Language->getId());
              $QoptionValues->execute();
              ?>
              <tr>
                <td>
                  <table class="table table-sm">
                    <?php
                    $Qproducts = $CLICSHOPPING_ProductsAttributes->db->prepare('select p.products_id,
                                                                                       p.products_model,
                                                                                       pd.products_name,
                                                                                       pov.products_options_values_name,
                                                                                       pa.products_attributes_reference,
                                                                                       po.products_options_type
                                                                                from :table_products p,
                                                                                     :table_products_options_values pov,
                                                                                     :table_products_options po,
                                                                                     :table_products_attributes pa,
                                                                                     :table_products_description pd
                                                                                where pd.products_id = p.products_id
                                                                                and pov.language_id = :language_id
                                                                                and pd.language_id = :language_id
                                                                                and po.language_id = :language_id
                                                                                and pa.products_id = p.products_id
                                                                                and pa.options_id = :options_id
                                                                                and po.products_options_id = pa.options_id
                                                                                and pov.products_options_values_id = pa.options_values_id
                                                                                order by pd.products_name
                                                                              ');
                    $Qproducts->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
                    $Qproducts->bindInt(':options_id', (int)$_GET['option_id']);

                    $Qproducts->execute();

                    if ($Qproducts->fetch() !== false) {
                    ?>
                    <thead>
                    <tr class="dataTableHeadingRow">
                      <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_id'); ?></td>
                      <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_type'); ?></td>
                      <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_product'); ?></td>
                      <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_opt_value'); ?></td>
                    </tr>
                    </thead>
                    <tbody>

                    <?php
                    $rows = 0;

                    while ($Qproducts->fetch()) {
                      $rows++;
                      ?>
                      <tr>
                        <td><?php echo $Qproducts->valueInt('products_id'); ?></td>
                        <td><?php echo HTML::selectMenu('products_options_type', $products_options_type, $Qproducts->value('products_options_type')); ?></td>
                        <td><?php echo $Qproducts->value('products_name'); ?></td>
                        <td><?php echo $Qproducts->value('products_options_values_name'); ?></td>
                      </tr>
                      <?php
                    }
                    ?>
                    <tr>
                      <td colspan="3">
                        <br/><?php echo $CLICSHOPPING_ProductsAttributes->getDef('text_warning_of_delete'); ?></td>
                    </tr>
                    <tr>
                      <td colspan="3" class="text-end">
                        <br/><?php echo HTML::button($CLICSHOPPING_ProductsAttributes->getDef('button_cancel'), null, $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&' . $page_info), 'warning', null, 'sm'); ?>
                      </td>
                    </tr>
                    <?php
                    } else {
                      ?>
                      <tr>
                        <td><br/>
                          <span><?php echo $CLICSHOPPING_ProductsAttributes->getDef('heading_title_opt'); ?></span>
                          <span class="float-end">
                            <?php
                              echo HTML::button($CLICSHOPPING_ProductsAttributes->getDef('button_delete'), null, $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&DeleteOption&option_id=' . $_GET['option_id']), 'danger', null, 'sm') . ' ';
                              echo HTML::button($CLICSHOPPING_ProductsAttributes->getDef('button_cancel'), null, $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&' . (isset($_GET['order_by']) ? 'order_by=' . $_GET['order_by'] . '&' : '') . (isset($page) ? 'page=' . $page : '')), 'warning', null, 'sm');
                            ?>
                        </span>
                        </td>
                      </tr>
                      <?php
                    }
                    ?>
                    </tbody>
                  </table>
                </td>
              </tr>
              <?php
            } else {
            $QoptionValues = $CLICSHOPPING_ProductsAttributes->db->prepare('select SQL_CALC_FOUND_ROWS *
                                                                            from :table_products_options
                                                                            where language_id = :language_id
                                                                            order by products_options_id
                                                                            limit :page_set_offset,
                                                                                  :page_set_max_results
                                                                            ');
            $QoptionValues->bindInt(':language_id', $CLICSHOPPING_Language->getId());
            $QoptionValues->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
            $QoptionValues->execute();

            $listingTotalRow = $QoptionValues->getPageSetTotalRows();

            if ($listingTotalRow > 0) {
              ?>
              <div class="row">
                <div class="col-md-12">
                  <div
                    class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $QoptionValues->getPageSetLabel($CLICSHOPPING_ProductsAttributes->getDef('text_display_number_of_link')); ?></div>
                  <div class="float-end text-end"><?php echo $QoptionValues->getPageSetLinks(); ?></div>
                </div>
              </div>
              <?php
            }
            ?>
            <thead>
            <tr class="dataTableHeadingRow">
              <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_id'); ?></td>
              <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_type'); ?></td>
              <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_opt_name'); ?></td>
              <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_opt_order'); ?></td>
              <td></td>
              <td class="text-center"
                  colspan="2"><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_action'); ?></td>
            </tr>
            </thead>
            <tbody>
            <?php
            $next_id = 1;
            $rows = 0;

            if ($listingTotalRow > 0) {
              while ($QoptionValues->fetch()) {

                $rows++;
                ?>
                <tr>
                  <?php
                  if (isset($_GET['UpdateOption']) && ($_GET['option_id'] == $QoptionValues->valueInt('products_options_id'))) {

                    echo HTML::form('option', $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&UpdateOptionName&' . $page_info . '&DHTMLSuite_active_tab=0')) . HTML::hiddenField('DHTMLSuite_active_tab', '0');

                    $inputs = '';

                    for ($i = 0, $n = \count($languages); $i < $n; $i++) {

                      $QoptionsName = $CLICSHOPPING_ProductsAttributes->db->prepare('select products_options_name
                                                                                     from :table_products_options
                                                                                     where products_options_id = :products_options_id
                                                                                     and language_id = :language_id
                                                                                    ');
                      $QoptionsName->bindInt(':products_options_id', $QoptionValues->valueInt('products_options_id'));
                      $QoptionsName->bindInt(':language_id', $languages[$i]['id']);
                      $QoptionsName->execute();

                      $inputs .= '<div class="row">
                      <span class="col-md-1">' . $languages[$i]['code'] . ':</span>
                      <span class="col-md-11">&nbsp;' . HTML::inputField('option_name[' . $languages[$i]['id'] . ']', $QoptionsName->value('products_options_name'), 'required aria-required="true"') . '&nbsp;</span>
                      </div>
                     ';

                    }
                    ?>
                    <td><?php echo $QoptionValues->valueInt('products_options_id') . HTML::hiddenField('option_id', $QoptionValues->valueInt('products_options_id')); ?></td>
                    <td><?php echo HTML::selectMenu('products_options_type', $products_options_type, $QoptionValues->value('products_options_type')); ?></td>
                    <td><?php echo $inputs; ?></td>
                    <td><?php echo '<br />' . HTML::inputField('option_sort_order', $QoptionValues->valueInt('products_options_sort_order')); ?></td>
                    <td></td>
                    <td class="text-end">
                      <?php
                      echo HTML::button(CLICSHOPPING::getDef('button_update'), null, null, 'primary', null, 'sm') . ' ';
                      echo HTML::button($CLICSHOPPING_ProductsAttributes->getDef('button_cancel'), null, $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&' . $page_info), 'warning', null, 'sm');
                      ?>
                    </td>

                    <?php
                    echo '</form>' . "\n";
                  } else {
                    ?>
                    <td><?php echo $QoptionValues->valueInt('products_options_id'); ?></td>
                    <td><?php echo $products_options_type_labels[$QoptionValues->value('products_options_type')] ?? $QoptionValues->value('products_options_type'); ?></td>
                    <td><?php echo $QoptionValues->value('products_options_name'); ?></td>
                    <td><?php echo $QoptionValues->valueInt('products_options_sort_order'); ?></td>
                    <td></td>
                    <td class="text-end">
                      <?php
                      echo HTML::button($CLICSHOPPING_ProductsAttributes->getDef('button_edit'), null, $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&UpdateOption&option_id=' . $QoptionValues->valueInt('products_options_id') . '&' . $page_info), 'primary', null, 'sm') . ' ';
                      echo HTML::button($CLICSHOPPING_ProductsAttributes->getDef('button_delete'), null, $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&DeleteProductOption&option_id=' . $QoptionValues->valueInt('products_options_id') . '&' . $page_info), 'danger', null, 'sm');
                      ?>
                    </td>
                    <?php
                  }

                  ?>
                </tr>
                <?php
                $QmaxOptionsId = $CLICSHOPPING_ProductsAttributes->db->prepare('select max(products_options_id) + 1 as next_id
                                                                                  from :table_products_options
                                                                                 ');
                $QmaxOptionsId->execute();

                $next_id = $QmaxOptionsId->valueInt('next_id');
              }
            } // end $listingTotalRow

            if (!isset($_GET['UpdateOption'])) {
              ?>
              <tr>
                <?php
                echo HTML::form('options', $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&AddProductOptions&' . $page_info)) . HTML::hiddenField('products_options_id', $next_id);

                $inputs = '';
                for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                  $inputs .= '<div class="row">
                    <span class="col-md-1">' . $languages[$i]['code'] . ':</span>
                    <span class="col-md-11">&nbsp;' . HTML::inputField('option_name[' . $languages[$i]['id'] . ']', null, 'required aria-required="true"') . '&nbsp;</span>
                    </div>
                    ';
                }
                ?>
                <td><?php echo $next_id; ?></td>
                <td><?php echo HTML::selectMenu('products_options_type', $products_options_type, $QoptionValues->value('products_options_type')); ?></td>
                <td><?php echo $inputs; ?></td>
                <td><?php echo '<br />' . HTML::inputField('option_sort_order'); ?></td>
                <td></td>
                <td
                  class="text-end"><?php echo HTML::button($CLICSHOPPING_ProductsAttributes->getDef('button_insert'), null, null, 'primary', null, 'sm'); ?></td>

                <?php
                echo '</form>';
                ?>
              </tr>
              <?php
            }
            }
            ?>
            </tbody>
          </table>
        </div>
        <?php
        //***********************************
        // Tab2
        //***********************************
        ?>
        <div class="tab-pane" id="tab2">
          <table class="table table-sm table-hover table-striped">
            <?php
            // delete product option value
            if (isset($_GET['DeleteOptionValue'])) {
            $Qvalues = $CLICSHOPPING_ProductsAttributes->db->prepare('select products_options_values_id,
                                                                            products_options_values_name
                                                                     from :table_products_options_values
                                                                     where products_options_values_id = :products_options_values_id
                                                                     and language_id = :language_id
                                                                    ');

            $Qvalues->bindInt(':language_id', $CLICSHOPPING_Language->getId());
            $Qvalues->bindInt(':products_options_values_id', $_GET['value_id']);
            $Qvalues->execute();
            ?>
            <tbody>
            <tr>
              <td width="100%">
                <table class="table table-sm">
                  <?php
                  $products = $CLICSHOPPING_ProductsAttributes->db->prepare('select p.products_id,
                                                                                     pd.products_name,
                                                                                     po.products_options_name
                                                                              from :table_products p,
                                                                                   :table_products_attributes pa,
                                                                                   :table_products_options po,
                                                                                   :table_products_description pd
                                                                             where pd.products_id = p.products_id
                                                                             and pd.language_id = :language_id
                                                                             and po.language_id = :language_id
                                                                             and pa.products_id = p.products_id
                                                                             and pa.options_values_id = :options_values_id
                                                                             and po.products_options_id = pa.options_id
                                                                             order by pd.products_name
                                                                            ');
                  $products->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
                  $products->bindInt(':options_values_id', (int)$_GET['value_id']);
                  $products->execute();

                  if ($products->fetch() !== false) {
                    while ($products->fetch()) {
                      $rows++;
                      ?>
                      <tr>
                        <td><?php echo $products->valueInt('products_id'); ?></td>
                        <td><?php echo $products->value('products_name'); ?></td>
                        <td><?php echo $products->value('products_options_name'); ?></td>
                      </tr>
                      <?php
                    }

                    ?>

                    <tr>
                      <td></td>
                      <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('text_warning_of_delete'); ?></td>
                      <td class="text-end"><br/>
                        <?php echo HTML::button($CLICSHOPPING_ProductsAttributes->getDef('button_cancel'), null, $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&' . $page_info . '#tab2'), 'warning', null, 'sm'); ?>
                      </td>
                    </tr>
                    <?php
                  } else {
                    ?>
                    <tr>
                      <td></td>
                      <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('text_ok_to_delete'); ?></td>
                      <td class="text-end">
                        <?php
                        echo HTML::button($CLICSHOPPING_ProductsAttributes->getDef('button_delete'), null, $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&DeleteValue&value_id=' . $_GET['value_id'] . '&' . $page_info . '#tab2'), 'danger', null, 'sm') . ' ';
                        echo HTML::button($CLICSHOPPING_ProductsAttributes->getDef('button_cancel'), null, $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&' . $page_info . '#tab2'), 'warning', null, 'sm');
                        ?>
                      </td>
                    </tr>
                    <?php
                  }
                  ?>
                </table>
              </td>
            </tr>

            <?php
            } else {
            $Qvalues = $CLICSHOPPING_ProductsAttributes->db->prepare('select SQL_CALC_FOUND_ROWS distinct pov.products_options_values_id,
                                                                                                    pov.products_options_values_name,
                                                                                                    pov2po.products_options_id
                                                                         from :table_products_options_values pov left join :table_products_options_values_to_products_options pov2po on pov.products_options_values_id = pov2po.products_options_values_id
                                                                         where pov.language_id = :language_id
                                                                         order by pov.products_options_values_id
                                                                         limit :page_set_offset,
                                                                              :page_set_max_results
                                                                        ');
            $Qvalues->bindInt(':language_id', $CLICSHOPPING_Language->getId());
            $Qvalues->setPageSet((int)MAX_ROW_LISTS_OPTIONS);
            $Qvalues->execute();

            $listingTotalRow = $Qvalues->getPageSetTotalRows();

            if ($listingTotalRow > 0) {
              ?>
              <div class="row">
                <div class="col-md-12">
                  <div
                    class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qvalues->getPageSetLabel($CLICSHOPPING_ProductsAttributes->getDef('text_display_number_of_link')); ?></div>
                  <div class="float-end text-end"> <?php echo $Qvalues->getPageSetLinks(); ?></div>
                </div>
              </div>
              <?php
            } // end $listingTotalRow
            ?>
              <thead>
              <tr class="dataTableHeadingRow">
                <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_id'); ?></td>
                <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_opt_name'); ?></td>
                <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_opt_value'); ?></td>
                <td class="text-center"
                    colspan="2"><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_action'); ?></td>
              </tr>
              </thead>

            <?php
            $next_id = 1;
            $rows = 0;

            while ($Qvalues->fetch()) {
            $options_name = $CLICSHOPPING_ProductsAttributesAdmin->getOptionsName($Qvalues->valueInt('products_options_id'));
            $options_type_tab2 = $CLICSHOPPING_ProductsAttributesAdmin->getOptionsType($Qvalues->valueInt('products_options_id'));
            $values_name = $Qvalues->value('products_options_values_name');
            $rows++;
            ?>
            <tr>
              <?php
              if (isset($_GET['UpdateOptionValue']) && ($_GET['value_id'] == $Qvalues->valueInt('products_options_values_id'))) {
                echo HTML::form('values', $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&UpdateValue&' . $page_info . '#tab2'));

                $inputs = '';

                for ($i = 0, $n = \count($languages); $i < $n; $i++) {

                  $QvaluesName = $CLICSHOPPING_ProductsAttributes->db->prepare('select products_options_values_name
                                                                                from :table_products_options_values
                                                                                where products_options_values_id = :products_options_values_id
                                                                                and language_id = :language_id
                                                                               ');

                  $QvaluesName->bindInt(':language_id', $languages[$i]['id']);
                  $QvaluesName->bindInt(':products_options_values_id', $Qvalues->valueInt('products_options_values_id'));
                  $QvaluesName->execute();

                  $value_name_attrs = ($options_type_tab2 === 'color_picker') ? 'class="color" required aria-required="true"' : 'required aria-required="true"';
                  $inputs .= '<div class="row">
                        <span class="col-md-1">' . $languages[$i]['code'] . ':</span>
                        <span class="col-md-11">&nbsp;' . HTML::inputField('value_name[' . $languages[$i]['id'] . ']', $QvaluesName->value('products_options_values_name'), $value_name_attrs) . '</span>
                      </div>
                      ';
                }
                ?>
                <td><?php echo $QvaluesName->valueInt('products_options_values_id') . HTML::hiddenField('value_id', $QvaluesName->value('products_options_values_id')); ?></td>
                <td>
                  <?php echo "\n"; ?>
                  <select name="option_id" class="form-group">
                    <?php
                    $QoptionValues = $CLICSHOPPING_ProductsAttributes->db->prepare('select products_options_id,
                                                                                           products_options_name,
                                                                                           products_options_type
                                                                                     from :table_products_options
                                                                                     where language_id = :language_id
                                                                                     order by products_options_name
                                                                                    ');

                    $QoptionValues->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());

                    $QoptionValues->execute();

                    while ($QoptionValues->fetch()) {
                      echo "\n" . '<option name="' . $QoptionValues->value('products_options_name') . '" value="' . $QoptionValues->valueInt('products_options_id') . '" data-type="' . $QoptionValues->value('products_options_type') . '"';
                      if ($Qvalues->valueInt('products_options_id') == $QoptionValues->valueInt('products_options_id')) {
                        echo ' selected';
                      }
                      echo '>' . $QoptionValues->value('products_options_name') . '</option>';
                    }
                    ?>
                  </select>
                </td>
                <td><?php echo $inputs; ?></td>
                <td class="text-end">
                  <?php
                  echo HTML::button($CLICSHOPPING_ProductsAttributes->getDef('button_update'), null, null, 'primary', null, 'sm') . ' ';
                  echo HTML::button($CLICSHOPPING_ProductsAttributes->getDef('button_cancel'), null, $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&' . $page_info . '#tab2'), 'warning', null, 'sm');
                  ?>
                </td>
                <?php
                echo '</form>';
              } else {
                ?>
                <td><?php echo $Qvalues->valueInt('products_options_id'); ?></td>
                <td><?php echo $options_name; ?></td>
                <td><?php
                  if ($options_type_tab2 === 'color_picker') {
                    $hex = '#' . ltrim($values_name, '#');
                    echo '<span style="display:inline-block;width:20px;height:20px;background-color:' . HTML::outputProtected($hex) . ';border:1px solid #ccc;border-radius:3px;vertical-align:middle;margin-right:5px;"></span>' . HTML::outputProtected($values_name);
                  } else {
                    echo $values_name;
                  }
                ?></td>
                <td class="text-end">
                  <?php
                  echo HTML::button($CLICSHOPPING_ProductsAttributes->getDef('button_edit'), null, $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&UpdateOptionValue&value_id=' . $Qvalues->valueInt('products_options_values_id') . '#tab2'), 'primary', null, 'sm') . ' ';
                  echo HTML::button($CLICSHOPPING_ProductsAttributes->getDef('button_delete'), null, $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&DeleteOptionValue&value_id=' . $Qvalues->valueInt('products_options_values_id') . '&' . $page_info . '#tab2'), 'danger', null, 'sm');
                  ?>
                </td>
                <?php
              }

              $QmaxValuesId = $CLICSHOPPING_ProductsAttributes->db->prepare('select max(products_options_values_id) + 1 as next_id
                                                                                 from :table_products_options_values
                                                                                 ');
              $QmaxValuesId->execute();

              $next_id = $QmaxValuesId->valueInt('next_id');
              }
              } // end $listingTotalRow
              ?>
            </tr>


            <?php
            //***************************************
            // Update option
            //***************************************
            if (!isset($_GET['UpdateOptionValue']) && (!isset($_GET['DeleteOptionValue']))) {

              echo HTML::form('values', $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&AddProductOptionValues&' . $page_info . '#tab2'));
              ?>

              <tr>
                <td><?php echo $next_id; ?></td>
                <td>
                  <select name="option_id" id="tab2InsertOptionId">
                    <?php
                    $QoptionValues = $CLICSHOPPING_ProductsAttributes->db->prepare('select products_options_id,
                                                                                           products_options_name,
                                                                                           products_options_type
                                                                                     from :table_products_options
                                                                                     where language_id = :language_id
                                                                                     order by products_options_name
                                                                                   ');
                    $QoptionValues->bindInt(':language_id', $CLICSHOPPING_Language->getId());
                    $QoptionValues->execute();

                    while ($QoptionValues->fetch()) {
                      echo '<option name="' . $QoptionValues->value('products_options_name') . '" value="' . $QoptionValues->valueInt('products_options_id') . '" data-type="' . $QoptionValues->value('products_options_type') . '">' . $QoptionValues->value('products_options_name') . '</option>';
                    }

                    $inputs = '';
                    for ($i = 0, $n = \count($languages); $i < $n; $i++) {
                      $inputs .= $languages[$i]['code'] . ':&nbsp;' . HTML::inputField('value_name[' . $languages[$i]['id'] . ']', null, 'required aria-required="true" id="tab2InsertValueName_' . $languages[$i]['id'] . '"') . '<br />';
                    }
                    ?>
                  </select>
                </td>
                <td><?php echo HTML::hiddenField('value_id', $next_id) . $inputs; ?></td>
                <td
                  class="text-end"><?php echo HTML::button($CLICSHOPPING_ProductsAttributes->getDef('button_insert'), null, null, 'primary', null, 'sm'); ?></td>
              </tr>


              <?php
              echo '</form>';

            }
            ?>
            </tbody>
          </table>
        </div>
        <?php
        //***********************************
        // Tab3 -  Definition des attributs produits : etape 3
        //***********************************
        ?>
        <div class="tab-pane" id="tab3">
          <?php
          if (isset($_GET['UpdateAttribute'])) {
            $form_action = 'UpdateProductAttribute';
          } else {
            $form_action = 'AddProductAttributes';
          }
          $Qattributes = $CLICSHOPPING_ProductsAttributes->db->prepare('select SQL_CALC_FOUND_ROWS  pa.*
                                                                         from :table_products_attributes pa
                                                                         left join :table_products_description pd on pa.products_id = pd.products_id
                                                                         and pd.language_id = :language_id
                                                                         order by pa.products_attributes_id
                                                                         limit :page_set_offset,
                                                                              :page_set_max_results
                                                                        ');
          $Qattributes->bindInt(':language_id', $CLICSHOPPING_Language->getId());
          $Qattributes->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
          $Qattributes->execute();

          $listingTotalRow = $Qattributes->getPageSetTotalRows();

          echo HTML::form('attributes', $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&' . $form_action . '&' . $page_info . '#tab3'), 'post', 'enctype="multipart/form-data" id="attributes"');
          ?>
          <table class="table table-sm table-hover table-striped">
            <thead>
            <tr class="dataTableHeadingRow">
              <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_id'); ?></td>
              <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_image'); ?></td>
              <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_ref_attributes'); ?></td>
              <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_product'); ?></td>
              <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_opt_name'); ?></td>
              <?php
              if (DOWNLOAD_ENABLED == 'true') {
                ?>
                <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_download'); ?></td>
                <?php
              }
              ?>
              <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_opt_value'); ?></td>

              <?PHP
              if (MODE_B2B_B2C == 'True') {
                ?>
                <td
                  class="text-end"><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_opt_b2b'); ?></td>
                <?php
              } else {
                ?>
                <td></td>
                <?php
              }
              ?>
              <td
                class="text-end"><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_opt_price'); ?></td>
              <td
                class="text-center"><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_opt_price_prefix'); ?></td>
              <td
                class="text-center"><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_opt_status'); ?></td>
              <td
                class="text-center"><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_opt_order'); ?></td>
              <td class="text-center"
                  colspan="2"><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_action'); ?></td>
            </tr>
            </thead>
            <?php
            $next_id = 1;

            if ($listingTotalRow > 0) {

              while ($Qattributes->fetch()) {
                $products_name_only = $CLICSHOPPING_ProductsAdmin->getProductsName($Qattributes->valueInt('products_id'));
                $options_name = $CLICSHOPPING_ProductsAttributesAdmin->getOptionsName($Qattributes->valueInt('options_id'));
                $options_type = $CLICSHOPPING_ProductsAttributesAdmin->getOptionsType($Qattributes->valueInt('options_id'));
                $values_name = $CLICSHOPPING_ProductsAttributesAdmin->getValuesName($Qattributes->valueInt('options_values_id'));

                $rows++;
                ?>
                <tr>
                <?php
                if (isset($_GET['UpdateAttribute']) && ($_GET['attribute_id'] == $Qattributes->valueInt('products_attributes_id'))) {
                  ?>
                  <td><?php echo $Qattributes->valueInt('products_attributes_id') . HTML::hiddenField('attribute_id', $Qattributes->valueInt('products_attributes_id')); ?></td>
                  <td>
                    <?php
                    echo $CLICSHOPPING_ProductsAdmin->getInfoImage($Qattributes->value('products_attributes_image'), $products_name_only, 50, 50);
                    echo HTML::hiddenField('products_attributes_image', $Qattributes->value('products_attributes_image'));
                    echo HTML::fileField('products_image_resize', 'id="file"');
                    ?>
                  </td>
                  <td
                    class="text-center"><?php echo HTML::inputField('products_attributes_reference', $Qattributes->value('products_attributes_reference')); ?></td>
                  <td>
                    <select name="products_id">
                      <?php
                      $QproductsValue = $CLICSHOPPING_ProductsAttributes->db->prepare('select p.products_id,
                                                                                             pd.products_name,
                                                                                             p.products_model
                                                                                       from :table_products p,
                                                                                            :table_products_description pd
                                                                                       where pd.products_id = p.products_id
                                                                                       and pd.language_id = :language_id
                                                                                       order by pd.products_name
                                                                                      ');
                      $QproductsValue->bindInt(':language_id', $CLICSHOPPING_Language->getId());
                      $QproductsValue->execute();

                      while ($QproductsValue->fetch()) {
                        if ($Qattributes->valueInt('products_id') == $QproductsValue->valueInt('products_id')) {
                          echo "\n" . '<option name="' . $QproductsValue->value('products_name') . '" value="' . $QproductsValue->valueInt('products_id') . '" SELECTED>' . $QproductsValue->value('products_model') . ' - ' . $QproductsValue->value('products_name') . '</option>';
                        } else {
                          echo "\n" . '<option name="' . $QproductsValue->value('products_name') . '" value="' . $QproductsValue->valueInt('products_id') . '">' . $QproductsValue->value('products_model') . ' - ' . $QproductsValue->value('products_name') . '</option>';
                        }
                      }
                      ?>
                    </select>
                  </td>
                  <td>
                    <select name="options_id" id="tab3EditOptionsId">
                      <?php
                      $QoptionValues = $CLICSHOPPING_ProductsAttributes->db->prepare('select *
                                                                                     from :table_products_options
                                                                                     where language_id = :language_id
                                                                                     order by products_options_name
                                                                                    ');
                      $QoptionValues->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
                      $QoptionValues->execute();

                      while ($QoptionValues->fetch()) {
                        if ($Qattributes->valueInt('options_id') == $QoptionValues->valueInt('products_options_id')) {
                          echo "\n" . '<option name="' . $QoptionValues->value('products_options_name') . '" value="' . $QoptionValues->valueInt('products_options_id') . '" data-type="' . $QoptionValues->value('products_options_type') . '" SELECTED>' . $QoptionValues->value('products_options_name') . '</option>';
                        } else {
                          echo "\n" . '<option name="' . $QoptionValues->value('products_options_name') . '" value="' . $QoptionValues->valueInt('products_options_id') . '" data-type="' . $QoptionValues->value('products_options_type') . '">' . $QoptionValues->value('products_options_name') . '</option>';
                        }
                      }
                      ?>
                    </select>
                  </td>
                  <?php
                  if (DOWNLOAD_ENABLED == 'true') {
                    ?>
                    <td></td>
                    <?php
                  }
                  ?>
                  <td id="tab3EditValuesCell">
                    <?php
                    $Qvalues_edit = $CLICSHOPPING_ProductsAttributes->db->prepare('select *
                                                                              from :table_products_options_values
                                                                              where language_id = :language_id
                                                                              order by products_options_values_name
                                                                             ');
                    $Qvalues_edit->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
                    $Qvalues_edit->execute();

                    $tab3EditValuesData = [];
                    $tab3EditSelectedId = $Qattributes->valueInt('options_values_id');
                    $tab3EditSelectedName = '';
                    while ($Qvalues_edit->fetch()) {
                      $tab3EditValuesData[] = [
                        'id'   => $Qvalues_edit->valueInt('products_options_values_id'),
                        'name' => $Qvalues_edit->value('products_options_values_name'),
                      ];
                      if ($Qvalues_edit->valueInt('products_options_values_id') == $tab3EditSelectedId) {
                        $tab3EditSelectedName = $Qvalues_edit->value('products_options_values_name');
                      }
                    }
                    ?>
                    <select name="values_id" id="tab3EditValuesSelect" style="display:none;">
                      <?php foreach ($tab3EditValuesData as $vd): ?>
                        <option value="<?php echo (int)$vd['id']; ?>"<?php echo ((int)$vd['id'] === $tab3EditSelectedId) ? ' selected' : ''; ?>><?php echo HTML::outputProtected($vd['name']); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <div id="tab3EditValuesDropdown"
                         data-values="<?php echo HTML::outputProtected(json_encode($tab3EditValuesData)); ?>"
                         data-options-type="<?php echo HTML::outputProtected($options_type); ?>"
                         data-selected-name="<?php echo HTML::outputProtected($tab3EditSelectedName); ?>"
                         data-selected-id="<?php echo (int)$tab3EditSelectedId; ?>"
                         data-ajax-url="<?php echo CLICSHOPPING::link('ajax/Products/products_attributes_inline.php'); ?>"
                         style="position:relative;display:inline-block;min-width:180px;">
                      <div id="tab3EditValuesDisplay" style="border:1px solid #ced4da;border-radius:4px;padding:4px 8px;cursor:pointer;background:#fff;display:flex;align-items:center;gap:6px;min-height:31px;">
                        <span id="tab3EditValuesSwatch" style="display:inline-block;width:18px;height:18px;border:1px solid #ccc;border-radius:3px;flex-shrink:0;"></span>
                        <span id="tab3EditValuesLabel" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                        <span style="color:#666;">&#9660;</span>
                      </div>
                      <div id="tab3EditValuesList" style="display:none;position:absolute;z-index:9999;border:1px solid #ced4da;border-radius:4px;background:#fff;max-height:220px;overflow-y:auto;width:100%;box-shadow:0 2px 8px rgba(0,0,0,.15);"></div>
                    </div>
                  </td>
                  <?php
                  if (MODE_B2B_B2C == 'True') {
                    echo '<td>' . HTML::selectMenu('customers_group_id', GroupsB2BAdmin::getAllGroups(), $Qvalues->value('customers_group_id')) . '</td>';
                  } else {
                    echo '<td></td>';
                  }
                  ?>
                  <td
                    class="text-end"><?php echo HTML::inputField('value_price', $Qattributes->value('options_values_price'), 'required aria-required="true"'); ?></td>
                  <td
                    class="text-center"><?php echo HTML::inputField('price_prefix', $Qattributes->value('price_prefix'), 'required aria-required="true"'); ?></td>
                  <td></td>
                  <td
                    class="text-center"><?php echo HTML::inputField('value_sort_order', $Qattributes->value('products_options_sort_order')); ?></td>
                  <td class="text-end">
                    <?php
                    echo HTML::button(CLICSHOPPING::getDef('button_update'), null, null, 'primary', null, 'sm') . ' ';
                    echo HTML::button(CLICSHOPPING::getDef('button_cancel'), null, $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&' . $page_info . '#tab3'), 'warning', null, 'sm');
                    ?>
                  </td>
                  <?php
                  if (DOWNLOAD_ENABLED == 'true') {
                    $Qdownload = $CLICSHOPPING_ProductsAttributes->db->prepare('select products_attributes_filename,
                                                                                         products_attributes_maxdays,
                                                                                         products_attributes_maxcount
                                                                                  from :table_products_attributes_download
                                                                                  where products_attributes_id = :products_attributes_id
                                                                                ');
                    $Qdownload->bindInt(':products_attributes_id', $Qattributes->valueInt('products_attributes_id'));
                    $Qdownload->execute();

                    if ($Qdownload->rowCount() > 0) {
                      $products_attributes_filename = $Qdownload->value('products_attributes_filename');
                      $products_attributes_maxdays = $Qdownload->value('products_attributes_maxdays');
                      $products_attributes_maxcount = $Qdownload->value('products_attributes_maxcount');
                    }
                    ?>
                    <tr>
                      <td
                        colspan="2"><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_download'); ?></td>
                      <td
                        align="left"><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_text_filename'); ?></td>
                      <td valign="bottom"><?php echo HTML::fileField('new_products_attributes_filename'); ?></td>
                      <td>
                        <strong><?php echo HTML::hiddenField('products_attributes_filename', $products_attributes_filename) . $products_attributes_filename; ?>
                          <strong></td>
                      <td
                        colspan="2"><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_text_max_days') ?></td>
                      <td><?php echo HTML::inputField('products_attributes_maxdays', $products_attributes_maxdays, 'required aria-required="true"'); ?></td>
                      <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_text_max_count'); ?></td>
                      <td><?php echo HTML::inputField('products_attributes_maxcount', $products_attributes_maxcount, 'required aria-required="true"'); ?></td>
                      <td></td>
                      <td></td>
                    </tr>
                    <?php
                  }
                } elseif (isset($_GET['DeleteProductAttribute']) && ($_GET['attribute_id'] == $Qattributes->valueInt('products_attributes_id'))) {
                  ?>
                  <tr>
                  <td><strong><?php echo $Qattributes->valueInt('products_attributes_id'); ?></strong></td>
                  <td>
                    <?php
                    echo $CLICSHOPPING_ProductsAdmin->getInfoImage($Qattributes->value('products_attributes_image'), $products_name_only, 50, 50);
                    echo HTML::hiddenField('products_attributes_image', $Qattributes->value('products_attributes_image'));
                    ?>
                  </td>
                  <td class="text-center"><?php echo $Qattributes->value('products_attributes_reference'); ?></td>
                  <td><strong><?php echo $products_name_only; ?></strong></td>
                  <td><strong><?php echo $options_name; ?></strong></td>
                  <?php
                  if (DOWNLOAD_ENABLED == 'true') {
                    $Qfilename = $CLICSHOPPING_ProductsAttributes->db->prepare('select products_attributes_filename
                                                                                 from :table_products_attributes_download
                                                                                 where products_attributes_id =  :products_attributes_id
                                                                                 limit 1
                                                                                 ');
                    $Qfilename->bindInt(':products_attributes_id', $Qattributes->valueInt('products_attributes_id'));
                    $Qfilename->execute();
                    ?>
                    <td><?php echo $Qfilename->value('products_attributes_filename'); ?></td>
                    <?php
                  }
                  ?>
                  <td><strong><?php
                    if ($options_type === 'color_picker') {
                      $hex = '#' . ltrim($values_name, '#');
                      echo '<span style="display:inline-block;width:18px;height:18px;background-color:' . HTML::outputProtected($hex) . ';border:1px solid #ccc;border-radius:2px;vertical-align:middle;margin-right:4px;"></span>' . HTML::outputProtected($values_name);
                    } else {
                      echo $values_name;
                    }
                  ?></strong></td>
                  <?php
                  if (MODE_B2B_B2C == 'True') {
                    if ($Qattributes->valueInt('customers_group_id') != 0 && $Qattributes->valueInt('customers_group_id') != 99) {
                      $all_groups_name_special = GroupsB2BAdmin::getCustomersGroupName($Qattributes->valueInt('customers_group_id'));
                    } elseif ($Qattributes->valueInt('customers_group_id') == 99) {
                      $all_groups_name_special = $CLICSHOPPING_ProductsAttributes->getDef('text_all_groups');
                    } else {
                      $all_groups_name_special = $CLICSHOPPING_ProductsAttributes->getDef('visitor_name');
                    }

                    echo '<td>' . $all_groups_name_special . '</td>';
                  } else {
                    echo '<td></td>';
                  }
                  ?>
                  <td class="text-end">
                    <strong><?php echo $Qattributes->valueDecimal('options_values_price'); ?></strong></td>
                  <td class="text-center"><strong><?php echo $Qattributes->value('price_prefix'); ?></strong></td>
                  <td class="text-center">
                    <strong><?php echo $Qattributes->valueInt('products_options_sort_order'); ?></strong></td>
                  <td class="text-end" colspan="2">
                    <?php
                    echo HTML::button($CLICSHOPPING_ProductsAttributes->getDef('button_delete'), null, $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&DeleteAttribute&attribute_id=' . $_GET['attribute_id'] . '&' . $page_info . '#tab3'), 'danger', null, 'sm') . ' ';
                    echo HTML::button($CLICSHOPPING_ProductsAttributes->getDef('button_cancel'), null, $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&' . $page_info . '#tab3'), 'warning', null, 'sm');
                    ?>
                  </td>
                  <?php
                } elseif (!isset($_GET['DeleteProductAttribute'])) {
                  ?>
                  <td><?php echo $Qattributes->valueInt('products_attributes_id'); ?></td>
                  <td>

                    <?php
                    echo $CLICSHOPPING_ProductsAdmin->getInfoImage($Qattributes->value('products_attributes_image'), $products_name_only, 50, 50);
                    echo HTML::hiddenField('products_attributes_image', $Qattributes->value('products_attributes_image'));
                    ?>
                  </td>

                  <td><?php echo $Qattributes->value('products_attributes_reference'); ?></td>
                  <td><?php echo $products_name_only; ?></td>
                  <td><?php echo $options_name; ?></td>
                  <?php
                  if (DOWNLOAD_ENABLED == 'true') {
                    $Qfilename = $CLICSHOPPING_ProductsAttributes->db->prepare('select products_attributes_filename
                                                                                 from :table_products_attributes_download
                                                                                 where products_attributes_id = :products_attributes_id
                                                                                 limit 1
                                                                               ');
                    $Qfilename->bindInt(':products_attributes_id', $Qattributes->valueInt('products_attributes_id'));
                    $Qfilename->execute();
                    ?>
                    <td><?php echo $Qfilename->value('products_attributes_filename'); ?></td>
                    <?php
                  }
                  ?>
                  <td><?php
                    if ($options_type === 'color_picker') {
                      $hex = '#' . ltrim($values_name, '#');
                      echo '<span style="display:inline-block;width:18px;height:18px;background-color:' . HTML::outputProtected($hex) . ';border:1px solid #ccc;border-radius:2px;vertical-align:middle;margin-right:4px;"></span>' . HTML::outputProtected($values_name);
                    } else {
                      echo $values_name;
                    }
                  ?></td>
                  <?php
                  if (MODE_B2B_B2C == 'True') {
                    if ($Qattributes->valueInt('customers_group_id') != 0 && $Qattributes->valueInt('customers_group_id') != 99) {
                      $all_groups_name_special = GroupsB2BAdmin::getCustomersGroupName($Qattributes->valueInt('customers_group_id'));
                    } elseif ($Qattributes->valueInt('customers_group_id') == 99) {
                      $all_groups_name_special = $CLICSHOPPING_ProductsAttributes->getDef('text_all_groups');
                    } else {
                      $all_groups_name_special = $CLICSHOPPING_ProductsAttributes->getDef('visitor_name');
                    }

                    echo '<td>' . $all_groups_name_special . '</td>';
                  } else {
                    echo '<td></td>';
                  }
                  ?>
                  <td class="text-end"><?php echo $Qattributes->value('options_values_price'); ?></td>
                  <td class="text-center"><?php echo $Qattributes->value('price_prefix'); ?></td>
                  <td class="text-center">
                    <?php
                    //ProductsAttributes&ProductsAttributes&UpdateAttribute&attribute_id=1&option_page=1&value_page=1&attribute_page=1#tab3
                    if ($Qattributes->valueInt('status') == 1) {
                      echo HTML::link($CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&SetFlag&flag=0&products_attributes_id=' . $Qattributes->valueInt('products_attributes_id')), '<i class="bi-check text-success"></i>');
                    } else {
                      echo HTML::link($CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&SetFlag&flag=1&products_attributes_id=' . $Qattributes->valueInt('products_attributes_id')), '<i class="bi bi-x text-danger"></i>');
                    }
                    ?>
                  </td>
                  <td class="text-center"><?php echo $Qattributes->value('products_options_sort_order'); ?></td>
                  <td class="text-end" colspan="2">
                    <?php
                    echo HTML::button($CLICSHOPPING_ProductsAttributes->getDef('button_edit'), null, $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&UpdateAttribute&attribute_id=' . $Qattributes->valueInt('products_attributes_id') . '&' . $page_info . '#tab3'), 'primary', null, 'sm') . ' ';
                    echo HTML::button($CLICSHOPPING_ProductsAttributes->getDef('button_delete'), null, $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&DeleteProductAttribute&attribute_id=' . $Qattributes->valueInt('products_attributes_id') . '&' . $page_info . '#tab3'), 'danger', null, 'sm');
                    ?>
                  </td>
                  <?php
                }

                $QmaxAttributes = $CLICSHOPPING_ProductsAttributes->db->prepare('select max(products_attributes_id) + 1 as next_id
                                                                                   from :table_products_attributes
                                                                                 ');
                $QmaxAttributes->execute();

                $max_attributes_id_values = $QmaxAttributes->fetch();

                $next_id = $QmaxAttributes->valueInt('next_id');
                ?>
                </tr>
                <?php
              }
            } // end listingrow

            if (!isset($_GET['UpdateAttribute'])) {
              ?>
              <tr>
                <td><?php echo $next_id; ?></td>
                <td><?php echo HTML::fileField('products_image_resize', 'id="file"'); ?></td>
                <td
                  class="text-center"><?php echo HTML::inputField('products_attributes_reference', $Qattributes->value('products_attributes_reference')); ?></td>
                <td>
                  <select name="products_id">
                    <?php
                    $Qproducts = $CLICSHOPPING_ProductsAttributes->db->prepare('select p.products_id,
                                                                                       pd.products_name,
                                                                                       p.products_model
                                                                                from :table_products p,
                                                                                     :table_products_description pd
                                                                                where pd.products_id = p.products_id
                                                                                and pd.language_id = :language_id
                                                                                      and p.products_archive = 0
                                                                                order by pd.products_name
                                                                              ');
                    $Qproducts->bindInt(':language_id', $CLICSHOPPING_Language->getId());
                    $Qproducts->execute();

                    while ($Qproducts->fetch()) {
                      echo '<option name="' . $Qproducts->value('products_name') . '" value="' . $Qproducts->valueInt('products_id') . '">' . $Qproducts->value('products_model') . ' - ' . $Qproducts->value('products_name') . '</option>';
                    }
                    ?>
                  </select>
                </td>
                <td>
                  <select name="options_id" id="tab3InsertOptionsId">
                    <?php
                    $QoptionValues = $CLICSHOPPING_ProductsAttributes->db->prepare('select *
                                                                                   from :table_products_options
                                                                                   where language_id = :language_id
                                                                                   order by products_options_name
                                                                                 ');
                    $QoptionValues->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
                    $QoptionValues->execute();

                    while ($QoptionValues->fetch()) {
                      echo '<option name="' . $QoptionValues->value('products_options_name') . '" value="' . $QoptionValues->valueInt('products_options_id') . '" data-type="' . $QoptionValues->value('products_options_type') . '">' . $QoptionValues->value('products_options_name') . '</option>';
                    }
                    ?>
                  </select>
                </td>
                <?php
                if (DOWNLOAD_ENABLED == 'true') {
                  ?>
                  <td></td>
                  <?php
                }
                ?>
                <td id="tab3ValuesCell">
                  <?php
                  // Build values data array for JS color rendering
                  $Qvalues = $CLICSHOPPING_ProductsAttributes->db->prepare('select *
                                                                              from :table_products_options_values
                                                                              where language_id = :language_id
                                                                              order by products_options_values_name
                                                                             ');
                  $Qvalues->bindInt(':language_id', $CLICSHOPPING_Language->getId());
                  $Qvalues->execute();

                  $tab3ValuesData = [];
                  while ($Qvalues->fetch()) {
                    $tab3ValuesData[] = [
                      'id'   => $Qvalues->valueInt('products_options_values_id'),
                      'name' => $Qvalues->value('products_options_values_name'),
                    ];
                  }
                  ?>
                  <!-- Native select (hidden, used for form submission) -->
                  <select name="values_id" id="tab3ValuesSelect" style="display:none;">
                    <?php foreach ($tab3ValuesData as $vd): ?>
                      <option value="<?php echo (int)$vd['id']; ?>"><?php echo HTML::outputProtected($vd['name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <!-- Custom color-aware dropdown -->
                  <div id="tab3ValuesDropdown"
                       data-values="<?php echo HTML::outputProtected(json_encode($tab3ValuesData)); ?>"
                       data-ajax-url="<?php echo CLICSHOPPING::link('ajax/Products/products_attributes_inline.php'); ?>"
                       style="position:relative;display:inline-block;min-width:180px;">
                    <div id="tab3ValuesDisplay" style="border:1px solid #ced4da;border-radius:4px;padding:4px 8px;cursor:pointer;background:#fff;display:flex;align-items:center;gap:6px;min-height:31px;">
                      <span id="tab3ValuesSwatch" style="display:inline-block;width:18px;height:18px;border:1px solid #ccc;border-radius:3px;flex-shrink:0;"></span>
                      <span id="tab3ValuesLabel" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                      <span style="color:#666;">&#9660;</span>
                    </div>
                    <div id="tab3ValuesList" style="display:none;position:absolute;z-index:9999;border:1px solid #ced4da;border-radius:4px;background:#fff;max-height:220px;overflow-y:auto;width:100%;box-shadow:0 2px 8px rgba(0,0,0,.15);"></div>
                  </div>
                </td>
                <td>
                  <?php
                  if (MODE_B2B_B2C == 'True') {
                    echo HTML::selectMenu('customers_group_id', GroupsB2BAdmin::getAllGroups());
                  }
                  ?>
                </td>
                <td class="text-end"><?php echo HTML::inputField('value_price', null, 'required aria-required="true"'); ?></td>
                <td class="text-end"><?php echo HTML::inputField('price_prefix', null, 'required aria-required="true"'); ?></td>
                <td></td>
                <td class="text-end"><?php echo HTML::inputField('value_sort_order'); ?></td>
                <td></td>
                <td
                  class="text-end"><?php echo HTML::button($CLICSHOPPING_ProductsAttributes->getDef('button_insert'), null, null, 'primary', null, 'sm'); ?></td>
              </tr>
              <?php
              if (DOWNLOAD_ENABLED == 'true') {
                $products_attributes_maxdays = (int)DOWNLOAD_MAX_DAYS;
                $products_attributes_maxcount = (int)DOWNLOAD_MAX_COUNT;
                ?>

                <table class="table table-sm table-hover">
                  <tr>
                    <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_heading_download'); ?></td>
                    <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_text_filename'); ?></td>
                    <td valign="bottom"><?php echo HTML::fileField('new_products_attributes_filename'); ?></td>
                    <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_text_max_days') ?></td>
                    <td><?php echo HTML::inputField('products_attributes_maxdays', $products_attributes_maxdays); ?></td>
                    <td><?php echo $CLICSHOPPING_ProductsAttributes->getDef('table_text_max_count'); ?></td>
                    <td><?php echo HTML::inputField('products_attributes_maxcount', $products_attributes_maxcount); ?></td>
                  </tr>
                </table>

                <?php
              }
            }
            ?>
          </table>
          </form>
          <?php
          if ($listingTotalRow > 0) {
            ?>
            <div class="row">
              <div class="col-md-12">
                <div
                  class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qattributes->getPageSetLabel(CLICSHOPPING::getDef('text_display_number_of_link')); ?></div>
                <div class="float-end text-end"> <?php echo $Qattributes->getPageSetLinks(); ?></div>
              </div>
            </div>
            <?php
          }
          ?>
          <div class="alert alert-info" role="alert">
            <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_ProductsAttributes->getDef('title_help_attributs') . '"></i></h4>&nbsp;' . $CLICSHOPPING_ProductsAttributes->getDef('title_help_attributs', ['file_size' => @ini_get('upload_max_filesize')]); ?></div>
            <div class="mt-1"></div>
            <div><?php echo $CLICSHOPPING_ProductsAttributes->getDef('text_help_attributs', ['upload_max_filesize' => $upload_max_filesize]); ?></div>
          </div>
        </div>
        <?php
        //***********************************
        // Tab4 - Clonage des produits
        //***********************************
        ?>
        <div class="tab-pane" id="tab4">
          <?php echo HTML::form('option', $CLICSHOPPING_ProductsAttributes->link('ProductsAttributes&CloneAttributes')); ?>
          <div
            class="mainTitle"><?php echo $CLICSHOPPING_ProductsAttributes->getDef('heading_title_clone_products_attributes'); ?></div>
          <table class="table table-sm table-hover table-striped">
            <tr valign="middle">
              <td class="text-center"
                  width="20%"><?php echo $CLICSHOPPING_ProductsAttributes->getDef('clone_products_from'); ?>
                <select name="clone_products_id_from">
                  <?php
                  $Qproducts = $CLICSHOPPING_ProductsAttributes->db->prepare('select p.products_id,
                                                                                     pd.products_name,
                                                                                     p.products_model
                                                                                from :table_products p,
                                                                                     :table_products_description pd
                                                                                where pd.products_id = p.products_id
                                                                                and pd.language_id = :language_id
                                                                                and p.products_archive = 0
                                                                                order by pd.products_name
                                                                             ');
                  $Qproducts->bindInt(':language_id', $CLICSHOPPING_Language->getId());
                  $Qproducts->execute();

                  while ($Qproducts->fetch()) {
                    echo '<option name="' . $Qproducts->value('products_name') . '" value="' . $Qproducts->valueInt('products_id') . '">' . $Qproducts->value('products_model') . ' - ' . $Qproducts->value('products_name') . '</option>';
                  }
                  ?>
                </select>
              </td>
              <td colspan="2" valign="middle"
                  width="5%"><?php echo $CLICSHOPPING_ProductsAttributes->getDef('clone_products_to'); ?></td>
              <td width="20%">
                <select name="clone_products_id_to[]" multiple size="10">
                  <?php
                  $Qproducts = $CLICSHOPPING_ProductsAttributes->db->prepare('select p.products_id,
                                                                                       pd.products_name,
                                                                                       p.products_model
                                                                                from :table_products p,
                                                                                     :table_products_description pd
                                                                                where pd.products_id = p.products_id
                                                                                and pd.language_id = :language_id
                                                                                and p.products_archive = 0
                                                                                order by pd.products_name
                                                                               ');
                  $Qproducts->bindInt(':language_id', $CLICSHOPPING_Language->getId());
                  $Qproducts->execute();

                  while ($Qproducts->fetch()) {
                    echo '<option name="' . $Qproducts->value('products_name') . '" value="' . $Qproducts->valueInt('products_id') . '">' . $Qproducts->value('products_model') . ' - ' . $Qproducts->value('products_name') . '</option>';
                  }
                  ?>
                </select>
              </td>
              <td width="55%">
                <?php
                echo HTML::button($CLICSHOPPING_ProductsAttributes->getDef('button_copy'), null, null, 'primary', null, 'sm') . ' ';
                echo HTML::button($CLICSHOPPING_ProductsAttributes->getDef('button_delete'), null, null, 'danger', null, 'sm');
                ?>
              </td>
          </table>

          <div class="alert alert-info" role="alert">
            <div><?php echo '<h4><i class="bi bi-question-circle" title="' . $CLICSHOPPING_ProductsAttributes->getDef('title_help_clone') . '"></i></h4>'; ?></div>
            <div class="mt-1"></div>
            <div><?php echo $CLICSHOPPING_ProductsAttributes->getDef('text_help_clone'); ?></div>
          </div>
        </div>
      </div>
      <?php
      //***********************************
      // extension
      //***********************************
      echo $CLICSHOPPING_Hooks->output('ProductsAttributes', 'PageContent', null, 'display');
      ?>
    </div>
  </div>
  </form><!-- end form delete all -->
</div>
<script src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/colorpicker/jscolor.js'); ?>"></script>
<script src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/products_attributes.js'); ?>"></script>