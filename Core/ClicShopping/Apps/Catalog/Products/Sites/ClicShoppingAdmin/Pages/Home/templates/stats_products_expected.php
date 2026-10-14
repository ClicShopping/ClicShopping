<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Image = Registry::get('Image');
$CLICSHOPPING_Products = Registry::get('Products');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$Qupdate = $CLICSHOPPING_Products->db->prepare('update :table_products
                                                 set products_date_available = :products_date_available
                                                 where to_days(now()) > to_days(products_date_available)
                                                ');
$Qupdate->bindValue(':products_date_available', null);
$Qupdate->execute();

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/products_expected.gif', $CLICSHOPPING_Products->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Products->getDef('heading_title'); ?></span>
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
    data-sort-name="expected"
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
      <th data-field="products"
          data-sortable="true"><?php echo $CLICSHOPPING_Products->getDef('table_heading_products'); ?></th>
      <th data-field="expected" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Products->getDef('table_heading_date_expected'); ?></th>
      <th data-field="action" data-sortable="true"
          class="text-end"><?php echo $CLICSHOPPING_Products->getDef('table_heading_action'); ?></th>
    </tr>
    </thead>
    <tbody>
    <?php
    $Qproducts = $CLICSHOPPING_Products->db->prepare('select  SQL_CALC_FOUND_ROWS  pd.products_id,
                                                                                      pd.products_name,
                                                                                      p.products_image,
                                                                                      p.products_date_available
                                                         from :table_products_description pd,
                                                              :table_products p
                                                         where p.products_id = pd.products_id
                                                         and (p.products_date_available = :products_date_available
                                                             or p.products_date_available <> :products_date_available1)
                                                         and pd.language_id = :language_id
                                                         order by p.products_date_available DESC
                                                         limit :page_set_offset, :page_set_max_results
                                                         ');

    $Qproducts->bindInt(':language_id', $CLICSHOPPING_Language->getId());
    $Qproducts->bindInt(':products_date_available', null);
    $Qproducts->bindInt(':products_date_available1', null);
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
          <td scope="row"
              width="50px"></td>
          <td
            class="dataTableContent"><?php echo $CLICSHOPPING_Image->getSmallImageAdmin($Qproducts->valueInt('products_id')); ?></td>
          <td class="dataTableContent"><?php echo $Qproducts->value('products_name'); ?></td>
          <td
            class="dataTableContent text-center"><?php echo DateTime::toShort($products['products_date_available']); ?></td>
          <td
            class="text-end">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <?php
              echo HTML::link($CLICSHOPPING_Products->link('Preview&pID=' . $Qproducts->valueInt('products_id')), '<h4><i class="bi bi-easil3" title="' . $CLICSHOPPING_Products->getDef('icon_preview') . '"></i></h4>');
              echo '&nbsp;';
              echo $CLICSHOPPING_Products->link('Products&search=' . $Qproducts->value('products_name'), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Products->getDef('icon_edit') . '"></i></h4>');
              ?>
            </div>
          </td>
        </tr>
        <?php
      } // end $listingTotalRow
    }
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
