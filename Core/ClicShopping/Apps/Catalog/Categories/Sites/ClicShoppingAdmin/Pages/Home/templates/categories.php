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
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Categories = Registry::get('Categories');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Hooks = Registry::get('Hooks');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_CategoriesAdmin = Registry::get('CategoriesAdmin');

if (!FileSystem::isWritable($CLICSHOPPING_Template->getDirectoryPathTemplateShopImages())) {
  ?>
  <div class="alert alert-warning"
       role="alert"><?php echo $CLICSHOPPING_Categories->getDef('error_catalog_image_directory_not_writeable'); ?></div>
  <?php
}
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/categorie.gif', $CLICSHOPPING_Categories->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-1 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Categories->getDef('heading_title'); ?></span>
          <span class="col-md-3">
            <div>
            <?php
            echo HTML::form('search', $CLICSHOPPING_Categories->link('Categories'), 'post', '', ['session_id' => true]);
            echo HTML::inputField('search', '', 'id="inputKeywords" placeholder="' . $CLICSHOPPING_Categories->getDef('heading_title') . '"');
            ?>
             </form>
            </div>
          </span>
          <span class="col-md-3 text-center">
           <div>
            <?php
            $current_category_id = 0;

            if (isset($_POST['cPath'])) $current_category_id = HTML::sanitize($_POST['cPath']);
            if (isset($_GET['cPath'])) $current_category_id = HTML::sanitize($_GET['cPath']);

            echo HTML::form('goto', $CLICSHOPPING_Categories->link('Categories'), 'post', '', ['session_id' => true]);
            echo HTML::selectField('cPath', $CLICSHOPPING_CategoriesAdmin->getCategoryTree(), $current_category_id, 'onchange="this.form.submit();"');
            echo '</form>';
            ?>
           </div>
          </span>
          <span class="col-md-4 text-end">
            <?php
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

            if (isset($_GET['search']) || isset($_POST['cPath'])) {
              echo HTML::button($CLICSHOPPING_Categories->getDef('button_back'), null, $CLICSHOPPING_Categories->link('Categories&' . $cPath_back . 'cID=' . $current_category_id), 'primary') . '&nbsp;';
            }

            if (!isset($_GET['search'])) {
              echo HTML::button($CLICSHOPPING_Categories->getDef('button_new_category'), null, $CLICSHOPPING_Categories->link('Insert&cPath=' . $current_category_id), 'info') . '&nbsp;';
              echo HTML::button($CLICSHOPPING_Categories->getDef('button_products'), null, CLICSHOPPING::link(null, 'A&Catalog\Products&Edit&cPath=' . $current_category_id), 'success');
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
          <?php echo $CLICSHOPPING_Hooks->output('Stats', 'StatsCategories', null, 'display'); ?>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <?php
  if (!isset($_POST['cPath']) || $_POST['cPath'] == 0) {
    ?>
    <div class="alert alert-info"
         role="alert"><?php echo $CLICSHOPPING_Categories->getDef('text_alert_info_categories'); ?></div>
    <?php
  }
  ?>
  <!-- //################################################################################################################ -->
  <!-- //                                             LISTING                                                            -->
  <!-- //################################################################################################################ -->

  <table
    id="table"
    data-toggle="table"
    data-icons-prefix="bi"
    data-icons="icons"
    data-sort-name="sort_order"
    data-sort-order="asc"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true">

    <thead class="dataTableHeadingRow">
    <tr>
      <th data-field="directory" data-switchable="false"></th>
      <th data-field="id"
          data-sortable="true"><?php echo $CLICSHOPPING_Categories->getDef('table_heading_categories_products'); ?></th>
      <th data-field="status" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Categories->getDef('table_heading_status'); ?></th>
      <th data-field="last_modified" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Categories->getDef('table_heading_last_modified'); ?></th>
      <th data-field="sort_order" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Categories->getDef('table_heading_sort_order'); ?></th>
      <th data-field="action" data-switchable="false" class="text-end"
          data-width="150"><?php echo $CLICSHOPPING_Categories->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    $categories_count = 0;

    if (isset($_POST['search'])) {
      $search = HTML::sanitize($_POST['search']);
    } else {
      $search = null;
    }

    $Qcategories = $CLICSHOPPING_CategoriesAdmin->getSearch($search);

    $listingTotalRow = $Qcategories->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      $categories_count = 0;

      while ($Qcategories->fetch()) {
        $categories_count++;

        // Get categories_id for product if search
        if (!empty($search)) {
          $cPath = $Qcategories->valueInt('categories_id');
        } else {
          if (isset($_POST['cPath'])) {
            $cPath = HTML::sanitize($_POST['cPath']);
          } else {
            if (isset($_GET['cPath'])) {
              $cPath = HTML::sanitize($_GET['cPath']);
            } else {
              $cPath = null;
            }
          }
        }

        if (((!isset($_GET['cID']) && !isset($_GET['pID'])) || (isset($_GET['cID']) && ((int)$_GET['cID'] === $Qcategories->valueInt('categories_id')))) && !isset($cInfo)) {
          $category_childs = array('childs_count' => $CLICSHOPPING_CategoriesAdmin->getChildsInCategoryCount($Qcategories->valueInt('categories_id')));
          $category_products = array('products_count' => $CLICSHOPPING_CategoriesAdmin->getCatalogInCategoryCount($Qcategories->valueInt('categories_id')));

          $cInfo_array = array_merge($Qcategories->toArray(), $category_childs, $category_products);
          $cInfo = new ObjectInfo($cInfo_array);
        }
        ?>
        <tr>
          <td><?php echo '<a href="' . $CLICSHOPPING_Categories->link('Categories&' . $CLICSHOPPING_CategoriesAdmin->getCategoriesPath($Qcategories->valueInt('categories_id'))) . '"'; ?>
            <i class="bi bi-folder-fill text-primary"></i></td>
          <td><?php echo '<strong>' . $Qcategories->value('categories_name') . '</strong>'; ?></td>
          <td>
            <?php
            if ($Qcategories->valueInt('status') == 1) {
              echo HTML::link($CLICSHOPPING_Categories->link('Categories&SetFlag&flag=0&cID=' . $Qcategories->valueInt('categories_id') . '&cPath=' . $cPath), '<i class="bi-check text-success"></i>');
            } else {
              echo HTML::link($CLICSHOPPING_Categories->link('Categories&SetFlag&flag=1&cID=' . $Qcategories->valueInt('categories_id') . '&cPath=' . $cPath), '<i class="bi bi-x text-danger"></i>');
            }
            ?>
          </td>
          <td>
            <?php
            if (!\is_null($Qcategories->value('last_modified'))) {
              echo DateTime::toShort($Qcategories->value('last_modified'));
            }
            ?>
          </td>
          <td class="text-center"><?php echo $Qcategories->valueInt('sort_order'); ?></td>
          <th class="text-end">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <?php
              echo '<a href="' . $CLICSHOPPING_Categories->link('Edit&cPath=' . $cPath . '&cID=' . $Qcategories->valueInt('categories_id')) . '"><h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Categories->getDef('icon_edit') . '"></i></h4></a>';
              echo '&nbsp;';
              echo '<a href="' . $CLICSHOPPING_Categories->link('Move&cPath=' . $cPath . '&cID=' . $Qcategories->valueInt('categories_id')) . '"><h4><i class="bi bi-arrows-move" title="' . $CLICSHOPPING_Categories->getDef('icon_move') . '"></i></h4></a>';
              echo '&nbsp;';
              echo '<a href="' . $CLICSHOPPING_Categories->link('Delete&cPath=' . $cPath . '&cID=' . $Qcategories->valueInt('categories_id')) . '"><h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_Categories->getDef('icon_delete') . '"></i></h4></a>';
              ?>
            </div>
          </th>
        </tr>
        <?php
      }
    } // end $listingTotalRow
    ?>
    </tbody>
  </table>
  </form>
  <div class="mt-1"></div>
  <div><?php echo $CLICSHOPPING_Categories->getDef('text_categories') . '&nbsp;' . $categories_count; ?></div>
</div>
