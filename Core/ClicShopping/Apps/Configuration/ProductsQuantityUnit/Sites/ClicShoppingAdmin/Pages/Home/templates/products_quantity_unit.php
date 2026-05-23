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

$CLICSHOPPING_ProductsQuantityUnit = Registry::get('ProductsQuantityUnit');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/products_unit.png', $CLICSHOPPING_ProductsQuantityUnit->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ProductsQuantityUnit->getDef('heading_title'); ?></span>
          <span
            class="col-md-9 text-end"><?php echo HTML::button($CLICSHOPPING_ProductsQuantityUnit->getDef('button_insert'), null, $CLICSHOPPING_ProductsQuantityUnit->link('Insert'), 'success', null, 'xs'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- //################################################################################################################ -->
  <!-- //                                             LISTING                                                            -->
  <!-- //################################################################################################################ -->

  <table
    id="table"
    data-toggle="table"
    data-icons-prefix="bi"
    data-icons="icons"
    data-sort-name="symbol"
    data-sort-order="asc"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true"
    data-search="true">

  <thead class="dataTableHeadingRow">
    <tr>
      <th data-field="quantity_status"
          data-sortable="false"><?php echo $CLICSHOPPING_ProductsQuantityUnit->getDef('table_heading_products_unit_quantity_status'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_ProductsQuantityUnit->getDef('table_heading_action'); ?></th>
    </tr>
    </thead>
    <tbody>
    <tr>
      <?php
      $QproductsQuantityUnit = $CLICSHOPPING_ProductsQuantityUnit->db->prepare('select  SQL_CALC_FOUND_ROWS  *
                                                                                    from :table_products_quantity_unit
                                                                                    where language_id = :language_id
                                                                                    order by products_quantity_unit_id
                                                                                    limit :page_set_offset,
                                                                                          :page_set_max_results
                                                                                    ');

      $QproductsQuantityUnit->bindInt(':language_id', $CLICSHOPPING_Language->getId());
      $QproductsQuantityUnit->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
      $QproductsQuantityUnit->execute();

      $listingTotalRow = $QproductsQuantityUnit->getPageSetTotalRows();

      if ($listingTotalRow > 0) {
      while ($QproductsQuantityUnit->fetch()) {

      if ((!isset($_GET['oID']) || (isset($_GET['oID']) && ((int)$_GET['oID'] === $QproductsQuantityUnit->valueInt('products_quantity_unit_id')))) && !isset($oInfo)) {
        $oInfo = new ObjectInfo($QproductsQuantityUnit->toArray());
      }

      if (DEFAULT_PRODUCTS_QUANTITY_UNIT_STATUS_ID == $QproductsQuantityUnit->valueInt('products_quantity_unit_id')) {
        echo '                <th scope="row"><strong>' . $QproductsQuantityUnit->value('products_quantity_unit_title') . ' (' . $CLICSHOPPING_ProductsQuantityUnit->getDef('text_default') . ')</strong></th>' . "\n";
      } else {
        echo '                <th>' . $QproductsQuantityUnit->value('products_quantity_unit_title') . '</th>' . "\n";
      }
      ?>
      <td class="text-end">
        <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
          <?php
          if ($QproductsQuantityUnit->valueInt('products_quantity_unit_id') > 0) {
            echo '<a href="' . $CLICSHOPPING_ProductsQuantityUnit->link('Delete&page=' . $page . '&oID=' . $QproductsQuantityUnit->valueInt('products_quantity_unit_id')) . '"><h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_ProductsQuantityUnit->getDef('icon_edit') . '"></i></h4></a>';
          }
          echo '&nbsp;';
          if (DEFAULT_PRODUCTS_QUANTITY_UNIT_STATUS_ID != $QproductsQuantityUnit->valueInt('products_quantity_unit_id')) {
            echo '<a href="' . $CLICSHOPPING_ProductsQuantityUnit->link('Edit&page=' . $page . '&oID=' . $QproductsQuantityUnit->valueInt('products_quantity_unit_id')) . '"><h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_ProductsQuantityUnit->getDef('icon_delete') . '"></i></h4></a>';
          }
          ?>
        </div>
      </td>
    </tr>
    <?php
    } //enwhile
    } // end $listingTotalRow
    ?>
    </tbody>
  </table>
  </td>
  </table>
  <div class="mt-1"></div>
  <?php
  if ($listingTotalRow > 0) {
    ?>
    <div class="row">
      <div class="col-md-12">
        <div
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $QproductsQuantityUnit->getPageSetLabel($CLICSHOPPING_ProductsQuantityUnit->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $QproductsQuantityUnit->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  }
  ?>
</div>