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
$CLICSHOPPING_Image = Registry::get('Image');
$CLICSHOPPING_Products = Registry::get('Products');
$CLICSHOPPING_Image = Registry::get('Image');
$CLICSHOPPING_Language = Registry::get('Language');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/stats_customers.gif', $CLICSHOPPING_Products->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-3 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Products->getDef('heading_title'); ?></span>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <table
    id="table"
    data-toggle="table"
    data-icons-prefix="bi"
    data-icons="icons"
    data-sort-name="model"
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
      <th data-switchable="false" width="20"></th>
      <th data-switchable="false" width="50"></th>
      <th data-field="products" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_products'); ?></th>
      <th data-field="model" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_model'); ?></th>
      <th data-field="replenishment" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_warehouse_time_replenishment'); ?></th>
      <th data-field="warehouse" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_warehouse'); ?></th>
      <th data-field="warehouse_row" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_warehouse_row'); ?></th>
      <th data-field="warehouse_level" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_warehouse_level'); ?></th>
      <th data-field="qty_left" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_qty_left'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_action'); ?></th>
    </tr>
    </thead>
    <tbody>
    <?php
    $Qcheck = $CLICSHOPPING_Products->db->query("show columns from :table_products like 'products_warehouse_time_replenishment'");

    if ($Qcheck->fetch() === false) {
      $Qproducts = $CLICSHOPPING_Products->db->prepare('select  SQL_CALC_FOUND_ROWS  p.products_id,
                                                                                    p.products_quantity,
                                                                                    p.products_model,
                                                                                    pd.products_name,
                                                                                    p.products_image,
                                                                                    p.products_packaging
                                                       from :table_products p,
                                                            :table_products_description pd
                                                       where p.products_id = pd.products_id
                                                       and pd.language_id = :language_id
                                                       and p.products_quantity < :products_quantity
                                                       group by pd.products_id
                                                       order by pd.products_name ASC
                                                       limit :page_set_offset,
                                                            :page_set_max_results
                                                      ');
    } else {
      $Qproducts = $CLICSHOPPING_Products->db->prepare('select SQL_CALC_FOUND_ROWS  p.products_id,
                                                                                  p.products_quantity,
                                                                                  p.products_model,
                                                                                  pd.products_name,
                                                                                  p.products_image,
                                                                                  p.products_warehouse_time_replenishment,
                                                                                  p.products_warehouse,                                                                                     
                                                                                  p.products_warehouse_row,
                                                                                  p.products_warehouse_level_location,
                                                                                  p.products_packaging
                                                        from :table_products p,
                                                        :table_products_description pd
                                                        where p.products_id = pd.products_id
                                                        and pd.language_id = :language_id
                                                        and p.products_quantity < :products_quantity
                                                        group by pd.products_id
                                                        order by pd.products_name ASC
                                                        limit :page_set_offset,
                                                        :page_set_max_results
                                                        ');
    }

    $Qproducts->bindInt(':language_id', $CLICSHOPPING_Language->getId());
    $Qproducts->bindInt(':products_quantity', STOCK_REORDER_LEVEL);
    $Qproducts->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $Qproducts->execute();

    $listingTotalRow = $Qproducts->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      $rows = 0;

      while ($Qproducts->fetch()) {
        $rows++;

        if (\strlen($rows) < 2) {
          $rows = '0' . $rows;
        }
        ?>
        <tr>
          <td scope="row"
              width="50px"></td>
          <td><?php echo $CLICSHOPPING_Image->getSmallImageAdmin($Qproducts->valueInt('products_id')); ?></td>
          <td>&nbsp;<?php echo $Qproducts->value('products_name'); ?></td>
          <td><?php echo $Qproducts->value('products_model'); ?></td>
          <td><?php echo $Qproducts->value('products_warehouse_time_replenishment'); ?></td>
          <td><?php echo $Qproducts->value('products_warehouse'); ?></td>
          <td><?php echo $Qproducts->value('products_warehouse_row'); ?></td>
          <td><?php echo $Qproducts->value('products_warehouse_level_location'); ?></td>
          <td class="text-center"><strong><?php echo $Qproducts->value('products_quantity'); ?></strong></td>
          <td class="text-end">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <?php
              echo HTML::link($CLICSHOPPING_Products->link('Products&search=' . $Qproducts->value('products_name')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Products->getDef('icon_edit') . '"></i></h4>');
              echo '&nbsp;';
              echo HTML::link($CLICSHOPPING_Products->link('Preview&pID=' . $Qproducts->valueInt('products_id')), '<h4><i class="bi bi-easil3" title="' . $CLICSHOPPING_Products->getDef('icon_preview') . '"></i></h4>');
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
  <?php
  if ($listingTotalRow > 0) {
    ?>
    <div class="row">
      <div class="col-md-12">
        <div
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qproducts->getPageSetLabel($CLICSHOPPING_Products->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $Qproducts->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  } // end $listingTotalRow
  ?>
</div>
</form>
<div class="py-4"></div>