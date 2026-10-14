<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\DateTime;
use ClicShopping\OM\FileSystem;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Products = Registry::get('Products');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_ProductsAdmin = Registry::get('ProductsAdmin');
$CLICSHOPPING_CategoriesAdmin = Registry::get('CategoriesAdmin');
$CLICSHOPPING_Image = Registry::get('Image');
$CLICSHOPPING_Language = Registry::get('Language');

$cPath_back = '';

$cPath_array = $CLICSHOPPING_CategoriesAdmin->getPathArray();

if (isset($cPath_array) && \count($cPath_array) > 0) {
  for ($i = 0, $n = \count($cPath_array) - 1; $i < $n; $i++) {
    if (empty($cPath_back)) {
      $cPath_back .= $cPath_array[$i];
    } else {
      $cPath_back .= '_' . $cPath_array[$i];
    }
  }
}

$cPath_back = (!\is_null($cPath_back)) ? 'cPath=' . $cPath_back . '&' : '';

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

$current_category_id = 0;

if (isset($_POST['cPath'])) {
  $current_category_id = HTML::sanitize($_POST['cPath']);
} elseif (isset($_GET['cPath'])) {
  $current_category_id = HTML::sanitize($_GET['cPath']);
}

if (isset($_GET['error']) && $_GET['error'] == 'fileNotSupported') {
  ?>
  <div class="alert alert-warning"
       role="alert"><?php echo $CLICSHOPPING_Products->getDef('error_file_not_supported'); ?></div>
  <?php
}

if (!FileSystem::isWritable($CLICSHOPPING_Template->getDirectoryPathTemplateShopImages())) {
  ?>
  <div class="alert alert-warning"
       role="alert"><?php echo $CLICSHOPPING_Products->getDef('error_catalog_image_directory_not_writeable'); ?></div>
  <?php
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
            class="col-md-1 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Products->getDef('heading_title'); ?></span>
          <span class="col-sm-2 text-center">
            <div>
              <?php
              echo HTML::form('search', $CLICSHOPPING_Products->link('Products'), 'post', null, ['session_id' => true]);
              echo HTML::inputField('search', '', 'id="inputKeywords" placeholder="' . $CLICSHOPPING_Products->getDef('heading_title_search') . '"');
              ?>
             </form>
            </div>
          </span>
          <span class="col-sm-2 text-center">
            <div>
            <?php
            echo HTML::form('goto', $CLICSHOPPING_Products->link('Products'), 'post', '', ['session_id' => true]);
            echo HTML::selectField('cPath', $CLICSHOPPING_CategoriesAdmin->getCategoryTree(), $current_category_id, 'onchange="this.form.submit();"');
            ?>
             </form>
            </div>
          </span>

          <span class="col-md-6 text-end">
            <?php
            if (isset($_GET['search']) || $current_category_id) {
              echo HTML::button($CLICSHOPPING_Products->getDef('button_back'), null, $CLICSHOPPING_Products->link('Products&' . $cPath_back . 'cID=' . $current_category_id), 'primary') . '&nbsp;';
            }

            if (!isset($_GET['search'])) {
              echo HTML::button($CLICSHOPPING_Products->getDef('button_insert'), null, $CLICSHOPPING_Products->link('Edit&Insert&cPath=' . $current_category_id), 'success') . '&nbsp;';
            }
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <?php echo $CLICSHOPPING_Hooks->output('Stats', 'StatsProductsOutOfStock', null, 'display'); ?>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <?php
  if ($current_category_id == 0) {
    ?>
    <div class="alert alert-info"
         role="alert"><?php echo $CLICSHOPPING_Products->getDef('text_alert_info_product'); ?></div>
    <?php
  }

  echo HTML::form('delete_all', $CLICSHOPPING_Products->link('Products&DeleteAll&cPath=' . $current_category_id));
  ?>
  <div id="toolbar" class="float-end">
    <button id="button" class="btn btn-danger"><?php echo $CLICSHOPPING_Products->getDef('button_delete'); ?></button>
  </div>

  <table
    id="table"
    data-toggle="table"
    data-icons-prefix="bi"
    data-icons="icons"
    data-id-field="selected"
    data-select-item-name="selected[]"
    data-click-to-select="true"
    data-sort-order="asc"
    data-sort-name="sort_order"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true">

    <thead class="dataTableHeadingRow">
    <tr>
      <th data-checkbox="true" data-field="state"></th>
      <th data-field="selected" data-sortable="true" data-visible="false"
          data-switchable="false"><?php echo $CLICSHOPPING_Products->getDef('id'); ?></th>
      <th data-switchable="false"></th>
      <th data-field="products" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_categories_products'); ?></th>
      <th data-field="sku" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_sku'); ?></th>
      <th data-field="status" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_status'); ?></th>
      <th data-field="price" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_price'); ?></th>
      <th data-field="quantity" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_qty'); ?></th>
      <th data-field="last_modified" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_last_modified'); ?>&nbsp;
      </th>
      <th data-field="created" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_created'); ?>&nbsp;
      </th>
      <th data-field="sort_order" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_sort_order'); ?>&nbsp;
      </th>
      <th data-field="action" data-switchable="false"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    $products_count = 0;
    $rows = 0;

    $search = '';

    if (isset($_POST['search'])) {
      $search = HTML::sanitize($_POST['search']);
    } elseif (isset($_GET['search'])) {
      $search = HTML::sanitize($_GET['search']);
    }

    $Qproducts = $CLICSHOPPING_ProductsAdmin->getSearch($search, $current_category_id);

    $listingTotalRow = $Qproducts->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($Qproducts->fetch()) {
        $products_count++;
        $rows++;

// Get categories_id for product if search
        if (isset($_POST['search'])) {
          $cPath = $Qproducts->valueInt('categories_id');
        } else {
          if (isset($_POST['cPath'])) {
            $cPath = $current_category_id;
          } elseif (isset($_GET['cPath'])) {
            $cPath = $current_category_id;
          } else {
            $cPath = '';
          }
        }

        if (((!isset($_GET['pID']) && !isset($_GET['cID'])) || (isset($_GET['pID']) && ((int)$_GET['pID'] === $Qproducts->valueInt('products_id')))) && !isset($pInfo) && !isset($cInfo)) {
          $Qreviews = $CLICSHOPPING_Products->db->get('reviews', '(avg(reviews_rating) / 5 * 100) as average_rating', ['products_id' => $Qproducts->valueInt('products_id')]);

          $pInfo_array = array_merge($Qproducts->toArray(), $Qreviews->toArray());
          $pInfo = new ObjectInfo($pInfo_array);
        }
        ?>
        <tr>
          <td></td>
          <td><?php echo $Qproducts->valueInt('products_id'); ?></td>
          <td class="dataTableContent">
            <?php
            if ($Qproducts->valueInt('products_status') == 1) {
              echo '<a href="' . HTTP::getShopUrlDomain() . 'index.php?Products&Description&products_id=' . $Qproducts->valueInt('products_id') . '" target="_blank" rel="noreferrer"><h4><i class="bi bi-easil3" title="' . $CLICSHOPPING_Products->getDef('icon_preview') . '"></i></h4></a>';
            }
            ?>
            <?php echo HTML::link($CLICSHOPPING_Products->link('Preview&pID=' . $Qproducts->valueInt('products_id')), '<h4><i class="bi bi-easil3" title="' . $CLICSHOPPING_Products->getDef('icon_preview') . '"></i></h4>'); ?>
            <?php echo $CLICSHOPPING_Image->getSmallImageAdmin($Qproducts->valueInt('products_id')); ?>
          </td>
          <td><?php echo $Qproducts->value('products_name') . ' [' . $Qproducts->value('products_model') . ']'; ?></td>
          <td class="text-start"><?php echo $Qproducts->value('products_sku'); ?></td>
          <td class="text-center">
            <?php
            if ($Qproducts->valueInt('products_status') === 1) {
              echo HTML::link($CLICSHOPPING_Products->link('Products&SetFlag&flag=0&pID=' . $Qproducts->valueInt('products_id') . '&cPath=' . $cPath), '<i class="bi-check text-success"></i>');
            } else {
              echo HTML::link($CLICSHOPPING_Products->link('Products&SetFlag&flag=1&pID=' . $Qproducts->valueInt('products_id') . '&cPath=' . $cPath), '<i class="bi bi-x text-danger"></i>');
            }
            ?>
          </td>
          <td class="text-center"><?php echo $Qproducts->value('products_price'); ?></td>
          <td class="text-center"><?php echo $Qproducts->valueInt('products_quantity'); ?></td>
          <?php
          if (!\is_null($Qproducts->value('products_last_modified'))) {
            echo '<td class="text-center">' . DateTime::toShort($Qproducts->value('products_last_modified')) . '</td>';
          } else {
            echo '<td class="text-center"></td>';
          }
          ?>
          <td class="text-start"><?php echo $Qproducts->value('admin_user_name'); ?></td>
          <td class="text-end"><?php echo $Qproducts->valueInt('products_sort_order'); ?></td>
          <td class="text-end">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <?php
              echo HTML::link($CLICSHOPPING_Products->link('Edit&cPath=' . $cPath . '&pID=' . $Qproducts->valueInt('products_id')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Products->getDef('icon_edit') . '"></i></h4>');
              echo '&nbsp;';
              echo HTML::link($CLICSHOPPING_Products->link('CopyTo&cPath=' . $cPath . '&pID=' . $Qproducts->valueInt('products_id')), '<h4><i class="bi bi-clipboard2" title="' . $CLICSHOPPING_Products->getDef('icon_copy_to') . '"></i></h4>');
              echo '&nbsp;';
              echo HTML::link($CLICSHOPPING_Products->link('Move&cPath=' . $cPath . '&pID=' . $Qproducts->valueInt('products_id')), '<h4><i class="bi bi-arrows-move" title="' . $CLICSHOPPING_Products->getDef('icon_move') . '"></i></h4>');
              echo '&nbsp;';
              echo HTML::link($CLICSHOPPING_Products->link('Archive&cPath=' . $cPath . '&pID=' . $Qproducts->valueInt('products_id')), '<h4><i class="bi bi-archive" title="' . $CLICSHOPPING_Products->getDef('icon_archive_to') . '"></i></h4>');
              echo '&nbsp;';
              echo HTML::link($CLICSHOPPING_Products->link('Delete&cPath=' . $cPath . '&pID=' . $Qproducts->valueInt('products_id')), '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_Products->getDef('icon_delete') . '"></i></h4>');
              echo '&nbsp;';
              ?>
            </div>
          </td>
        </tr>
        <?php
      }
    } // end $listingTotalRow
    ?>
    </tbody>
  </table>
  </form>
  <div><?php echo $CLICSHOPPING_Products->getDef('text_products') . '&nbsp;' . $products_count; ?></div>

  <?php
  if (empty($cPath)) {
    ?>
    <div class="mt-1"></div>
    <div class="alert alert-info text-center" role="alert">
      <?php echo $CLICSHOPPING_Products->getDef('text_warning_info_display'); ?>
    </div>
    <?php
  }

  if ($listingTotalRow > 0 && !isset($_POST['search'])) {
    ?>
    <div class="row">
      <div class="col-md-12">
        <div
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qproducts->getPageSetLabel($CLICSHOPPING_Products->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"> <?php echo $Qproducts->getPageSetLinks('Catalog\Products&Products&cPath=' . $current_category_id); ?></div>
      </div>
    </div>
    <?php
  } // end $listingTotalRow
  ?>
</div>