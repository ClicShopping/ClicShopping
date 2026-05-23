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
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Image = Registry::get('Image');
$CLICSHOPPING_Products = Registry::get('Products');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>

<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/stats_products_purchased.gif', $CLICSHOPPING_Products->getDef('heading_title'), '40', '40'); ?></span>
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
      <th data-switchable="false" width="20"></th>
      <th data-field="number"
          data-sortable="true"><?php echo $CLICSHOPPING_Products->getDef('table_heading_number'); ?></th>
      <th data-field="products"
          data-sortable="true"><?php echo $CLICSHOPPING_Products->getDef('table_heading_products'); ?></th>
      <th data-field="purchased" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_purchased'); ?></th>
      <th data-field="clear" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_Products->getDef('table_heading_clear'); ?></th>
    </tr>
    </thead>
    <tbody>
    <?php
    $Qproducts = $CLICSHOPPING_Products->db->prepare('SELECT SQL_CALC_FOUND_ROWS p.products_id,
                                                                                 p.products_image,
                                                                                 pd.products_name,
                                                                                 SUM(op.products_quantity) AS total_sold
                                                      FROM :table_orders_products op
                                                      JOIN :table_orders o
                                                           ON o.orders_id = op.orders_id
                                                      JOIN :table_products p
                                                           ON p.products_id = op.products_id
                                                      JOIN :table_products_description pd
                                                           ON pd.products_id = p.products_id
                                                      WHERE o.orders_status != 4
                                                        AND pd.language_id = :language_id
                                                        AND p.products_archive = 0
                                                      GROUP BY p.products_id
                                                      ORDER BY total_sold DESC,
                                                               pd.products_name
                                                      LIMIT :page_set_offset, 
                                                          :page_set_max_results
                                                      ');

    $Qproducts->bindInt(':language_id', $CLICSHOPPING_Language->getId());
    $Qproducts->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $Qproducts->execute();

    $listingTotalRow = $Qproducts->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($Qproducts->fetch()) {
        ?>
        <tr>
          <th scope="row" width="50px"><?php echo $Qproducts->valueInt('products_id'); ?></th>
          <td><?php echo $CLICSHOPPING_Image->getSmallImageAdmin($Qproducts->valueInt('products_id')); ?></td>
          <td><?php echo HTML::link(CLICSHOPPING::link(null, 'A&Catalog\Products&Preview&pID=' . $Qproducts->valueInt('products_id')), $Qproducts->value('products_name')); ?></td>
          <td class="text-center"><?php echo $Qproducts->valueInt('total_sold'); ?>&nbsp;</td>
          <td class="text-end">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <?php
              echo HTML::link($CLICSHOPPING_Products->link('Preview&pID=' . $Qproducts->valueInt('products_id')), '<h4><i class="bi bi-easel3" title="' . $CLICSHOPPING_Products->getDef('icon_preview') . '"></i></h4>');
              echo '&nbsp;';
              echo HTML::link($CLICSHOPPING_Products->link('Products&UpdateStatsProductsPurchased&resetPurchased=1&products_id=' . $Qproducts->valueInt('products_id') . '&page=' . $page), '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_Products->getDef('icon_delete') . '"></i></h4>');
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
<div class="py-4"></div>