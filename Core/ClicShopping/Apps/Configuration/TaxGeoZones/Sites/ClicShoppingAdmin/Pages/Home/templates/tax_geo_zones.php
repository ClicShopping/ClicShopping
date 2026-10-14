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

$CLICSHOPPING_TaxGeoZones = Registry::get('TaxGeoZones');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$page = (isset($_GET['zpage']) && is_numeric($_GET['zpage'])) ? $_GET['zpage'] : 1;
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/geo_zones.gif', $CLICSHOPPING_TaxGeoZones->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_TaxGeoZones->getDef('heading_title'); ?></span>
          <span
            class="col-md-7 text-end"><?php echo HTML::button($CLICSHOPPING_TaxGeoZones->getDef('button_insert'), null, $CLICSHOPPING_TaxGeoZones->link('Insert&page=' . $page), 'success'); ?></span>
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
    data-sort-name="sort_order"
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
      <th data-field="zones"
          data-sortable="true"><?php echo $CLICSHOPPING_TaxGeoZones->getDef('table_heading_tax_zones'); ?></th>
      <th data-field="description"
          data-sortable="true"><?php echo $CLICSHOPPING_TaxGeoZones->getDef('table_heading_tax_description'); ?></th>
      <th data-field="number"
          data-sortable="true"><?php echo $CLICSHOPPING_TaxGeoZones->getDef('table_heading_number_zone'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_TaxGeoZones->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    $Qzones = $CLICSHOPPING_TaxGeoZones->db->prepare('select SQL_CALC_FOUND_ROWS  geo_zone_id,
                                                                                     geo_zone_name,
                                                                                     geo_zone_description,
                                                                                     last_modified,
                                                                                     date_added
                                                        from :table_geo_zones
                                                        order by geo_zone_name
                                                        limit :page_set_offset,
                                                             :page_set_max_results
                                                        ');

    $Qzones->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN, 'zpage');
    $Qzones->execute();

    $listingTotalRow = $Qzones->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($Qzones->fetch()) {
        if ((!isset($_GET['zID']) || (isset($_GET['zID']) && ((int)$_GET['zID'] === $Qzones->valueInt('geo_zone_id')))) && !isset($zInfo)) {
          $Qtotal = $CLICSHOPPING_TaxGeoZones->db->prepare('select count(*) as num_zones
                                                              from :table_zones_to_geo_zones
                                                              where geo_zone_id = :geo_zone_id
                                                              ');
          $Qtotal->bindInt(':geo_zone_id', $Qzones->valueInt('geo_zone_id'));
          $Qtotal->execute();

          $zInfo = new ObjectInfo(array_merge($Qzones->toArray(), $Qtotal->toArray()));
        }
        ?>
        <tr>
          <th
            scope="row"><?php echo HTML::link($CLICSHOPPING_TaxGeoZones->link('List&zpage=' . $page . '&zID=' . $Qzones->valueInt('geo_zone_id')), $Qzones->value('geo_zone_name')) . '&nbsp;'; ?></th>
          <td><?php echo $Qzones->value('geo_zone_description'); ?></td>
          <td><?php echo $zInfo->num_zones ?? null; ?></td>
          <td class="text-end">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <?php
              echo HTML::link($CLICSHOPPING_TaxGeoZones->link('Edit&zpage=' . $page . '&zID=' . $Qzones->valueInt('geo_zone_id')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_TaxGeoZones->getDef('icon_edit') . '"></i></h4>');
              echo '&nbsp;';
              echo HTML::link($CLICSHOPPING_TaxGeoZones->link('ListGeo&zpage=' . $page . '&zID=' . $Qzones->valueInt('geo_zone_id')), '<h4><i class="bi bi-geo-alt" title="' . $CLICSHOPPING_TaxGeoZones->getDef('icon_geo') . '"></i></h4>');
              echo '&nbsp;';
              echo HTML::link($CLICSHOPPING_TaxGeoZones->link('Delete&zpage=' . $page . '&zID=' . $Qzones->valueInt('geo_zone_id')), '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_TaxGeoZones->getDef('icon_delete') . '"></i></h4>');
              echo '&nbsp;';
              ?>
            </div>
          </td>
        </tr>
        <?php
      } // end while
    } // end $listingTotalRow
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
          class="col-md-6 float-start pagenumber hidden-xs TextDisplayNumberOfLink"><?php echo $Qzones->getPageSetLabel($CLICSHOPPING_TaxGeoZones->getDef('text_display_number_of_link')); ?></div>
        <div
          class="float-end text-end"><?php echo $Qzones->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y'))); ?></div>
      </div>
    </div>
    <?php
  }
  ?>
</div>
