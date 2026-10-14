<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

$CLICSHOPPING_TaxGeoZones = Registry::get('TaxGeoZones');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$Qzones = $CLICSHOPPING_TaxGeoZones->db->prepare('select a.association_id,
                                                            a.zone_country_id,
                                                            a.zone_id,
                                                            a.geo_zone_id,
                                                            a.last_modified,
                                                            a.date_added
                                                    from :table_zones_to_geo_zones a
                                                    where a.association_id = :association_id
                                                    ');

$Qzones->bindInt('association_id', $_GET['sID']); ///3
$Qzones->execute();

$sInfo = new ObjectInfo($Qzones->toArray());

$Qcountries = $CLICSHOPPING_TaxGeoZones->db->prepare('select countries_id,
                                                              countries_name
                                                       from :table_countries
                                                       where countries_id = :countries_id
                                                      ');

$Qcountries->bindInt('countries_id', $sInfo->zone_country_id); ///3
$Qcountries->execute();

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
            class="col-md-7 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_TaxGeoZones->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_TaxGeoZones->getDef('text_info_heading_delete_sub_zone'); ?></strong></div>
  <?php echo HTML::form('zones', $CLICSHOPPING_TaxGeoZones->link('TaxGeoZones&DeleteGeoConfirm&ListGeo&zpage=' . $page . '&zID=' . $_GET['zID'] . '&spage=' . $page . '&sID=' . $sInfo->association_id)); ?>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo $CLICSHOPPING_TaxGeoZones->getDef('text_info_delete_sub_zone_intro'); ?>
        <br/><br/></div>
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo '<strong>' . $Qcountries->value('countries_name') . '</strong>'; ?><br/><br/>
      </div>
      <div class="col-md-12 text-center">
        <?php echo HTML::button($CLICSHOPPING_TaxGeoZones->getDef('button_delete'), null, null, 'primary', null, 'sm') . ' </span><span>' . HTML::button($CLICSHOPPING_TaxGeoZones->getDef('button_cancel'), null, $CLICSHOPPING_TaxGeoZones->link('ListGeo&zpage=' . $page . '&zID=' . $_GET['zID'] . '&spage=' . $_GET['spage'] . '&' . (isset($_GET['sID']) ? 'sID=' . $_GET['sID'] : '')), 'warning', null, 'sm'); ?>
      </div>
    </div>
  </div>

  </form>
</div>