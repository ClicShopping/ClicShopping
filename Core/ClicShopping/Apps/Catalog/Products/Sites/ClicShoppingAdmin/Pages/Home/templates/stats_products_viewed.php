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

$rows = 0;
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/stats_products_viewed.gif', $CLICSHOPPING_Products->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-9 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Products->getDef('heading_title'); ?></span>
          <span class="col-md-1 text-end"></span>
          <span
            class="col-md-1 text-end">
            <?php
              echo HTML::form('stats_products_viewed', $CLICSHOPPING_Products->link('Products&UpdateStatsProductsViewed&resetViewed=0'));
              echo HTML::button($CLICSHOPPING_Products->getDef('button_delete'), $CLICSHOPPING_Products->link('Products&Update&resetViewed=0&page=' . $page), null, 'danger');
            ?>
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
      <th data-switchable="false" width="50"></th>
      <th data-field="number"
          data-sortable="true"><?php echo $CLICSHOPPING_Products->getDef('table_heading_number'); ?></th>
      <th data-field="products"
          data-sortable="true"><?php echo $CLICSHOPPING_Products->getDef('table_heading_products'); ?></th>
      <th data-field="null" data-sortable="true"></th>
      <th data-field="viewed" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_viewed'); ?></th>
      <th data-field="clear" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_Products->getDef('table_heading_clear'); ?></th>
    </tr>
    </thead>
    <tbody>
    <?php
    $Qproducts = $CLICSHOPPING_Products->db->prepare('select  SQL_CALC_FOUND_ROWS  p.products_id,
                                                                                    pd.products_name,
                                                                                    p.products_image,
                                                                                    pd.products_viewed
                                                      from :table_products p,
                                                           :table_products_description pd
                                                      where p.products_id = pd.products_id
                                                      and pd.language_id = :language_id
                                                      and p.products_archive = 0
                                                      and pd.products_viewed > 0
                                                      order by pd.products_viewed DESC
                                                      limit :page_set_offset,
                                                            :page_set_max_results
                                                      ');

    $Qproducts->bindInt(':language_id', $CLICSHOPPING_Language->getId());
    $Qproducts->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $Qproducts->execute();

    $listingTotalRow = $Qproducts->getPageSetTotalRows();

    if ($listingTotalRow > 0) {

      while ($products = $Qproducts->fetch()) {
        $rows++;

        if (\strlen($rows) < 2) {
          $rows = '0' . $rows;
        }
        ?>
        <tr>
          <td scope="row">
          <td
          </td>
          <td><?php echo $CLICSHOPPING_Image->getSmallImageAdmin($Qproducts->valueInt('products_id')); ?></td>
          <td><?php echo $rows; ?>.</td>
          <td><?php echo HTML::link(CLICSHOPPING::link(null, 'A&Catalog\Products&Preview&pID=' . $Qproducts->valueInt('products_id')), $Qproducts->value('products_name')); ?></td>
          <td class="text-center"><?php echo $Qproducts->valueInt('products_viewed'); ?>&nbsp;</td>
          <td class="text-end">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <?php
              echo HTML::link($CLICSHOPPING_Products->link('Preview&pID=' . $Qproducts->valueInt('products_id')), '<h4><i class="bi bi-easel2" title="' . $CLICSHOPPING_Products->getDef('icon_preview') . '"></i></h4>');
              echo '&nbsp;';
              echo HTML::link($CLICSHOPPING_Products->link('Products&UpdateStatsProductsViewed&resetViewed=1&products_id=' . $Qproducts->valueInt('products_id') . '&page=' . $page), '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_Products->getDef('icon_delete') . '"></i></h4>');
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