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
use ClicShopping\Sites\ClicShoppingAdmin\Tax;

$CLICSHOPPING_TaxRates = Registry::get('TaxRates');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/tax_rates.gif', $CLICSHOPPING_TaxRates->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_TaxRates->getDef('heading_title'); ?></span>
          <span
            class="col-md-7 text-end"><?php echo HTML::button($CLICSHOPPING_TaxRates->getDef('button_insert'), null, $CLICSHOPPING_TaxRates->link('Insert&page=' . $page), 'success'); ?></span>
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
    data-sort-name="zone"
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
      <th data-field="priority"><?php echo $CLICSHOPPING_TaxRates->getDef('table_heading_tax_rate_priority'); ?></th>
      <th data-field="title"><?php echo $CLICSHOPPING_TaxRates->getDef('table_heading_tax_class_title'); ?></th>
      <th data-field="zone"
          data-sortable="true"><?php echo $CLICSHOPPING_TaxRates->getDef('table_heading_zone'); ?></th>
      <th data-field="rate"><?php echo $CLICSHOPPING_TaxRates->getDef('table_heading_tax_rate'); ?></th>
      <th data-field="description"><?php echo $CLICSHOPPING_TaxRates->getDef('table_heading_tax_description'); ?></th>
      <th data-field="tax_erp"><?php echo $CLICSHOPPING_TaxRates->getDef('table_heading_code_tax_erp'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_TaxRates->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    $Qrates = $CLICSHOPPING_TaxRates->db->prepare('select  SQL_CALC_FOUND_ROWS  r.tax_rates_id,
                                                                                     z.geo_zone_id,
                                                                                     z.geo_zone_name,
                                                                                     tc.tax_class_title,
                                                                                     tc.tax_class_id,
                                                                                     r.tax_priority,
                                                                                     r.tax_rate,
                                                                                     r.tax_description,
                                                                                     r.date_added,
                                                                                     r.last_modified,
                                                                                     r.code_tax_erp
                                                          from :table_tax_class tc,
                                                               :table_tax_rates r left join :table_geo_zones z on r.tax_zone_id = z.geo_zone_id
                                                          where r.tax_class_id = tc.tax_class_id
                                                          limit :page_set_offset,
                                                                :page_set_max_results
                                                          ');

    $Qrates->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $Qrates->execute();

    $listingTotalRow = $Qrates->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($Qrates->fetch()) {
        if ((!isset($_GET['tID']) || (isset($_GET['tID']) && ((int)$_GET['tID'] === $Qrates->valueInt('tax_rates_id')))) && !isset($trInfo)) {
          $trInfo = new ObjectInfo($Qrates->toArray());
        }
        ?>
        <tr>
          <th scope="row"><?php echo $Qrates->valueInt('tax_priority'); ?></th>
          <td><?php echo $Qrates->value('tax_class_title'); ?></td>
          <td><?php echo $Qrates->value('geo_zone_name'); ?></td>
          <td><?php echo Tax::displayTaxRateValue($Qrates->valueDecimal('tax_rate')); ?></td>
          <td><?php echo $Qrates->value('tax_description'); ?></td>
          <td><?php echo $Qrates->value('code_tax_erp'); ?></td>
          <td class="text-end">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <?php
              echo HTML::link($CLICSHOPPING_TaxRates->link('Edit&page=' . $page . '&tID=' . $Qrates->valueInt('tax_rates_id')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_TaxRates->getDef('icon_edit') . '"></i></h4>');
              echo '&nbsp;';
              echo HTML::link($CLICSHOPPING_TaxRates->link('Delete&page=' . $page . '&tID=' . $Qrates->valueInt('tax_rates_id')), '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_TaxRates->getDef('icon_delete') . '"></i></h4>');
              echo '&nbsp;';
              ?>
            </div>
          </td>
        </tr>
        <?php
      } // end while
    }
    ?>
    </tbody>
  </table>
  <div class="mt-1"></div>
  <?php
  if ($listingTotalRow > 0) {
    ?>
    <div class="row">
      <div class="col-md-12">
        <div
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qrates->getPageSetLabel($CLICSHOPPING_TaxRates->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $Qrates->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  }
  ?>
</div>
