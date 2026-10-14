<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Manufacturers = Registry::get('Manufacturers');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Hooks = Registry::get('Hooks');

$languages = $CLICSHOPPING_Language->getLanguages();

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/manufacturers.gif', $CLICSHOPPING_Manufacturers->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Manufacturers->getDef('heading_title'); ?></span>
          <span class="col-md-7 text-end">
            <?php echo HTML::button($CLICSHOPPING_Manufacturers->getDef('button_new'), null, $CLICSHOPPING_Manufacturers->link('Edit'), 'success'); ?>
           </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- ################# -->
  <!-- Hooks Stats - just use execute function to display the hook-->
  <!-- ################# -->
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <?php echo $CLICSHOPPING_Hooks->output('Stats', 'StatsManufacturers', null, 'display'); ?>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- //################################################################################################################ -->
  <!-- //                                            Listing                                                               -->
  <!-- //################################################################################################################ -->
  <?php echo HTML::form('delete_all', $CLICSHOPPING_Manufacturers->link('Manufacturers&DeleteAll&page=' . $page)); ?>

  <div id="toolbar" class="float-end">
    <button id="button"
            class="btn btn-danger"><?php echo $CLICSHOPPING_Manufacturers->getDef('button_delete'); ?></button>
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
    data-sort-name="manufacturer"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true"
    data-search="true">

    <thead class="dataTableHeadingRow">
    <tr>
      <th data-checkbox="true" data-field="state"></th>
      <th data-field="selected" data-sortable="true" data-visible="false"
          data-switchable="false"><?php echo $CLICSHOPPING_Manufacturers->getDef('id'); ?></th>
      <th data-switchable="false"></th>
      <th data-field="manufacturer"
          data-sortable="true"><?php echo $CLICSHOPPING_Manufacturers->getDef('table_heading_manufacturers'); ?></th>
      <th data-field="products" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Manufacturers->getDef('table_heading_products'); ?></th>
      <th data-field="status" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Manufacturers->getDef('table_heading_status'); ?></th>
      <th data-field="suppliers" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Manufacturers->getDef('table_heading_suppliers'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_Manufacturers->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    $Qmanufacturers = $CLICSHOPPING_Manufacturers->db->prepare('select SQL_CALC_FOUND_ROWS manufacturers_id,
                                                                                             manufacturers_name,
                                                                                             manufacturers_image,
                                                                                             date_added,
                                                                                             suppliers_id,
                                                                                             last_modified,
                                                                                             manufacturers_status
                                                                    from :table_manufacturers
                                                                    order by manufacturers_name
                                                                    limit :page_set_offset,
                                                                          :page_set_max_results
                                                                   ');

    $Qmanufacturers->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $Qmanufacturers->execute();

    $listingTotalRow = $Qmanufacturers->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($Qmanufacturers->fetch()) {
        if ((!isset($_GET['mID']) || (isset($_GET['mID']) && ((int)$_GET['mID'] == $Qmanufacturers->valueInt('manufacturers_id')))) && !isset($mInfo)) {

          $Qproducts = $CLICSHOPPING_Manufacturers->db->get('products', 'count(*) as products_count', ['manufacturers_id' => $Qmanufacturers->valueInt('manufacturers_id')]);

          $mInfo_array = array_merge($Qmanufacturers->toArray(), $Qproducts->toArray());
          $mInfo = new ObjectInfo($mInfo_array);
        }
        ?>
        <tr>
          <td></td>
          <td><?php echo $Qmanufacturers->valueInt('manufacturers_id'); ?></td>
          <td>
            <?php
            if (!empty($Qmanufacturers->value('manufacturers_image'))) {
              echo HTML::image($CLICSHOPPING_Template->getDirectoryShopTemplateImages() . $Qmanufacturers->value('manufacturers_image'), $Qmanufacturers->value('manufacturers_name'), (int)SMALL_IMAGE_WIDTH_ADMIN, (int)SMALL_IMAGE_HEIGHT_ADMIN);
            }
            ?>
          </td>
          <td scope="row"><?php echo $Qmanufacturers->value('manufacturers_name'); ?></td>
          <td>
            <?php
            $Qproducts = $CLICSHOPPING_Manufacturers->db->prepare('select count(products_id) as count
                                                                     from :table_products
                                                                     where manufacturers_id = :manufacturers_id
                                                                   ');
            $Qproducts->bindInt(':manufacturers_id', $Qmanufacturers->valueInt('manufacturers_id'));

            $Qproducts->execute();

            echo $Qproducts->valueInt('count');
            ?>
          </td>
          <td class="text-center">
            <?php
            if ($Qmanufacturers->valueInt('manufacturers_status') === 0) {
              echo '<a href="' . $CLICSHOPPING_Manufacturers->link('Manufacturers&SetFlag&page=' . $page . '&flag=1&id=' . $Qmanufacturers->valueInt('manufacturers_id')) . '"><i class="bi-check text-success"></i></a>';
            } else {
              echo '<a href="' . $CLICSHOPPING_Manufacturers->link('Manufacturers&SetFlag&page=' . $page . '&flag=0&id=' . $Qmanufacturers->valueInt('manufacturers_id')) . '"><i class="bi bi-x text-danger"></i></a>';
            }
            ?>
          </td>
          <td class="text-center">
            <?php
            if ($Qmanufacturers->valueInt('suppliers_id') !== 0) {
              echo '<i class="bi-check text-success"></i>';
            }
            ?>
          </td>
          <td class="text-end align-middle">
            <?php
            echo '<a href="' . $CLICSHOPPING_Manufacturers->link('Edit&page=' . $page . '&mID=' . $Qmanufacturers->valueInt('manufacturers_id')) . '"><h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Manufacturers->getDef('icon_edit') . '"></i></h4></a>';
            echo '&nbsp;';
            ?>
          </td>
        </tr>
        <?php
      } //end while
    } // end $listingTotalRow
    ?>
    </tbody>
  </table>
  </form><!-- end form delete all -->
  <?php
  if ($listingTotalRow > 0) {
    ?>
    <div class="row">
      <div class="col-md-12">
        <div
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qmanufacturers->getPageSetLabel($CLICSHOPPING_Manufacturers->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $Qmanufacturers->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  } // end $listingTotalRow
  ?>
</div>