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
$CLICSHOPPING_TaxGeoZones = Registry::get('TaxGeoZones');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();

if (!isset($_GET['spage']) || !is_numeric($_GET['spage'])) {
  $_GET['spage'] = 1;
}

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
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
            class="col-md-7 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_TaxGeoZones->getDef('heading_title'); ?></span>
          <span class="col-md-4 text-end">
<?php
echo HTML::button($CLICSHOPPING_TaxGeoZones->getDef('button_back'), null, $CLICSHOPPING_TaxGeoZones->link('TaxGeoZones'), 'primary') . ' ';
echo HTML::button($CLICSHOPPING_TaxGeoZones->getDef('button_insert'), null, $CLICSHOPPING_TaxGeoZones->link('InsertGeo&zpage=' . $page . '&zID=' . $_GET['zID'] . '&spage=' . $_GET['spage']), 'success');
?>
          </span>
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
    data-sort-name="country"
    data-sort-order="asc"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true">

    <thead class="dataTableHeadingRow">
    <tr>
      <th data-field="country"
          data-sortable="true"><?php echo $CLICSHOPPING_TaxGeoZones->getDef('table_heading_country'); ?></th>
      <th data-field="zone"
          data-sortable="true"><?php echo $CLICSHOPPING_TaxGeoZones->getDef('table_heading_country_zone'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_TaxGeoZones->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    $rows = 0;

    $Qzones = $CLICSHOPPING_TaxGeoZones->db->prepare('select  SQL_CALC_FOUND_ROWS a.association_id,
                                                                                    a.zone_country_id,
                                                                                    c.countries_name,
                                                                                    a.zone_id,
                                                                                    a.geo_zone_id,
                                                                                    a.last_modified,
                                                                                    a.date_added,
                                                                                    z.zone_name
                                                        from :table_zones_to_geo_zones a left join :table_countries c on a.zone_country_id = c.countries_id
                                                                                         left join :table_zones z on a.zone_id = z.zone_id
                                                        where a.geo_zone_id = :geo_zone_id
                                                        order by association_id
                                                        limit :page_set_offset,
                                                              :page_set_max_results
                                                        ');
    $Qzones->bindInt(':geo_zone_id', $_GET['zID']);
    $Qzones->setPageSet(MAX_DISPLAY_SEARCH_RESULTS_ADMIN, 'spage');
    $Qzones->execute();

    $listingTotalRow = $Qzones->getPageSetTotalRows();

    if ($listingTotalRow > 0) {

      while ($Qzones->fetch()) {

        if ((!isset($_GET['sID']) || (isset($_GET['sID']) && ((int)$_GET['sID'] === $Qzones->valueInt('association_id')))) && !isset($sInfo)) {
          $sInfo = new ObjectInfo($Qzones->toArray());
          if (\is_null($sInfo->countries_name)) {
            $sInfo->countries_name = $CLICSHOPPING_TaxGeoZones->getDef('text_all_countries');
          }

          if (\is_null($sInfo->zone_name)) {
            $sInfo->zone_name = $CLICSHOPPING_TaxGeoZones->getDef('text_selected');
          }
        }
        ?>
        <tr>
          <th
            scope="row"><?php echo $Qzones->hasValue('countries_name') ? $Qzones->value('countries_name') : $CLICSHOPPING_TaxGeoZones->getDef('text_all_countries'); ?></th>
          <td><?php echo $Qzones->hasValue('zone_name') ? $Qzones->value('zone_name') : $CLICSHOPPING_TaxGeoZones->getDef('text_selected'); ?></td>
          <td class="text-end">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <?php
              echo '<a href="' . $CLICSHOPPING_TaxGeoZones->link('EditGeo&List&zpage=' . $page . '&zID=' . $_GET['zID'] . '&spage=' . $_GET['spage'] . '&sID=' . $Qzones->valueInt('association_id')) . '"><h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_TaxGeoZones->getDef('icon_edit') . '"></i></h4></a>';
              echo '&nbsp;';
              echo '<a href="' . $CLICSHOPPING_TaxGeoZones->link('DeleteGeo&List&zpage=' . $page . '&zID=' . $_GET['zID'] . '&spage=' . $_GET['spage'] . '&sID=' . $Qzones->valueInt('association_id')) . '"><h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_TaxGeoZones->getDef('icon_delete') . '"></i></h4></a>';
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
  } // end $listingTotalRow
  ?>
</div>
