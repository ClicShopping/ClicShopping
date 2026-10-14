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

$Qzones = $CLICSHOPPING_TaxGeoZones->db->prepare('select  geo_zone_id,
                                                             geo_zone_name,
                                                             geo_zone_description,
                                                             last_modified,
                                                             date_added
                                                   from :table_geo_zones
                                                   where geo_zone_id =:geo_zone_id
                                                  ');
$Qzones->bindInt(':geo_zone_id', $_GET['zID']);

$Qzones->execute();

$zInfo = new ObjectInfo($Qzones->toArray());

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
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_TaxGeoZones->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_TaxGeoZones->getDef('text_info_heading_delete_zone'); ?></strong></div>
  <?php echo HTML::form('zones', $CLICSHOPPING_TaxGeoZones->link('TaxGeoZones&DeleteConfirm&zpage=' . $page . '&zID=' . $zInfo->geo_zone_id)); ?>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo $CLICSHOPPING_TaxGeoZones->getDef('text_info_delete_zone_intro'); ?><br/><br/>
      </div>
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo '<strong>' . $zInfo->geo_zone_name . '</strong>'; ?><br/><br/></div>
      <div class="col-md-12 text-center">
        <?php echo HTML::button($CLICSHOPPING_TaxGeoZones->getDef('button_delete'), null, null, 'danger', null, 'sm') . ' </span><span>' . HTML::button($CLICSHOPPING_TaxGeoZones->getDef('button_cancel'), null, $CLICSHOPPING_TaxGeoZones->link('TaxGeoZones&zpage=' . $_GET['zpage'] . '&zID=' . $_GET['zID']), 'warning', null, 'sm'); ?>
      </div>
    </div>
  </div>

  </form>
</div>