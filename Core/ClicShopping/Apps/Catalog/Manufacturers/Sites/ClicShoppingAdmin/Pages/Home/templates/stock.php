<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
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
          <?php echo $CLICSHOPPING_Hooks->output('Stats', 'StatsStockManufacturers', null, 'display'); ?>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- //################################################################################################################ -->
  <!-- //                                            Listing                                                               -->
  <!-- //################################################################################################################ -->
  <?php echo HTML::form('delete_all', $CLICSHOPPING_Manufacturers->link('Manufacturers&DeleteAll&page=' . $page)); ?>
  <table
    id="table"
    data-toggle="table"
    data-icons-prefix="bi"
    data-icons="icons"
    data-sort-name="number"
    data-sort-order="asc"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true"
    data-show-export="true">

    <thead class="dataTableHeadingRow">
    <tr>
      <th data-switchable="true"></th>
      <th data-field="manufacturer"
          data-sortable="true"><?php echo $CLICSHOPPING_Manufacturers->getDef('table_heading_manufacturers'); ?></th>
      <th data-field="products_model"
          data-sortable="true"><?php echo $CLICSHOPPING_Manufacturers->getDef('table_heading_products_model'); ?></th>
      <th data-field="products_name"
          data-sortable="true"><?php echo $CLICSHOPPING_Manufacturers->getDef('table_heading_products_name'); ?></th>
      <th data-field="products_quantity"
          data-sortable="true"><?php echo $CLICSHOPPING_Manufacturers->getDef('table_heading_products_quantity'); ?></th>
      <th data-field="products_suppliers_cost"
          data-sortable="true"><?php echo $CLICSHOPPING_Manufacturers->getDef('table_heading_products_suppliers_cost'); ?></th>
      <th data-field="total_cost" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Manufacturers->getDef('table_heading_total_cost'); ?></th>
      <th data-field="action" data-switchable="false" class="text-end"></th>
    </tr>
    </thead>
    <tbody>
    <?php
    $Qmanufacturers = $CLICSHOPPING_Manufacturers->db->prepare('select DISTINCT SQL_CALC_FOUND_ROWS m.manufacturers_id,
                                                                                                      m.manufacturers_name,
                                                                                                      m.manufacturers_image,
                                                                                                      p.products_id,
                                                                                                      p.products_model,
                                                                                                      pd.products_name,
                                                                                                      p.products_image,
                                                                                                      p.products_quantity,
                                                                                                      p.products_price,
                                                                                                      p.products_cost,
                                                                                                      p.products_status
                                                                     from :table_manufacturers m,
                                                                         :table_products p,
                                                                         :table_products_description pd 
                                                                    where p.manufacturers_id = m.manufacturers_id
                                                                    and p.products_id = pd.products_id 
                                                                    order by manufacturers_name
                                                                    limit :page_set_offset,
                                                                          :page_set_max_results
                                                                   ');

    $Qmanufacturers->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $Qmanufacturers->execute();

    $listingTotalRow = $Qmanufacturers->getPageSetTotalRows();

    if ($listingTotalRow > 0) {

      while ($Qmanufacturers->fetch()) {
        ?>
        <tr>
          <td>
            <?php
            if (!empty($Qmanufacturers->value('products_image'))) {
              echo HTML::image($CLICSHOPPING_Template->getDirectoryShopTemplateImages() . $Qmanufacturers->value('products_image'), $Qmanufacturers->value('products_name'), (int)SMALL_IMAGE_WIDTH_ADMIN, (int)SMALL_IMAGE_HEIGHT_ADMIN);
            }
            ?>
          </td>
          <td scope="row"><?php echo $Qmanufacturers->value('manufacturers_name'); ?></td>
          <td scope="row"><?php echo $Qmanufacturers->value('products_model'); ?></td>
          <td scope="row"><?php echo $Qmanufacturers->value('products_name'); ?></td>
          <td scope="row"><?php echo $Qmanufacturers->value('products_quantity'); ?></td>
          <td scope="row"><?php echo $Qmanufacturers->value('products_cost'); ?></td>
          <td
            scope="row"><?php echo $Qmanufacturers->value('products_cost') * $Qmanufacturers->value('products_quantity'); ?></td>
          <td scope="row"></td>

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
