<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_TaxGeoZones = Registry::get('TaxGeoZones');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$page = (isset($_GET['zpage']) && is_numeric($_GET['zpage'])) ? $_GET['zpage'] : 1;
$spage = (isset($_GET['spage']) && is_numeric($_GET['spage'])) ? $_GET['spage'] : 1;
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
          <span class="col-md-7 text-end">
<?php

echo HTML::form('zones', $CLICSHOPPING_TaxGeoZones->link('TaxGeoZones&InsertZone&zpage=' . $page));
echo HTML::button($CLICSHOPPING_TaxGeoZones->getDef('button_insert'), null, null, 'primary') . ' ';
echo HTML::button($CLICSHOPPING_TaxGeoZones->getDef('button_cancel'), null, $CLICSHOPPING_TaxGeoZones->link('TaxGeoZones&zpage=' . $page . '&action=list&spage=' . $spage . '&' . (isset($_GET['sID']) ? 'sID=' . $_GET['sID'] : '')), 'warning');
?>

          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_TaxGeoZones->getDef('text_info_heading_new_zone'); ?></strong></div>
  <div class="adminformTitle">
    <div class="row">
      <div class="col-md-12">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_TaxGeoZones->getDef('text_info_new_zone_intro'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_TaxGeoZones->getDef('text_info_new_zone_intro'); ?></label>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_TaxGeoZones->getDef('text_info_zone_name'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_TaxGeoZones->getDef('text_info_zone_name'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('geo_zone_name'); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_TaxGeoZones->getDef('text_info_zone_description'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_TaxGeoZones->getDef('text_info_zone_description'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('geo_zone_description'); ?>
          </div>
        </div>
      </div>
    </div>

  </div>
  </form>
</div>
