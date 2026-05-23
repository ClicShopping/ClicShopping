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
use ClicShopping\Sites\ClicShoppingAdmin\HTMLOverrideAdmin;

$CLICSHOPPING_TaxGeoZones = Registry::get('TaxGeoZones');
$CLICSHOPPING_Address = Registry::get('Address');

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
          <span class="col-md-4 text-end">
<?php
echo HTML::form('zones', $CLICSHOPPING_TaxGeoZones->link('TaxGeoZones&UpdateGeoZone&List&zpage=' . $page . '&zID=' . $_GET['zID'] . '&spage=' . $_GET['spage'] . '&sID=' . $sInfo->association_id));
echo HTML::button($CLICSHOPPING_TaxGeoZones->getDef('button_update'), null, null, 'success') . ' ';
echo HTML::button($CLICSHOPPING_TaxGeoZones->getDef('button_cancel'), null, $CLICSHOPPING_TaxGeoZones->link('ListGeo&list&zpage=' . $page . '&zID=' . $_GET['zID'] . '&spage=' . $_GET['spage'] . '&' . (isset($_GET['sID']) ? 'sID=' . $_GET['sID'] : '')), 'warning');
?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_TaxGeoZones->getDef('text_info_heading_edit_sub_zone'); ?></strong></div>
  <div class="adminformTitle">
    <div class="row">
      <div class="col-md-12">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_TaxGeoZones->getDef('text_info_new_sub_zone_intro'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_TaxGeoZones->getDef('text_info_new_sub_zone_intro'); ?></label>
        </div>
      </div>
    </div>


    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_TaxGeoZones->getDef('text_info_country'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_TaxGeoZones->getDef('text_info_country'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::selectMenuCountryList('zone_country_id', $sInfo->zone_country_id, 'onchange="update_zone(this.form);"'); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_TaxGeoZones->getDef('text_info_country_zone'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_TaxGeoZones->getDef('text_info_country_zone'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::selectField('zone_id', $CLICSHOPPING_Address->getPrepareCountryZonesPullDown($sInfo->zone_country_id), $sInfo->zone_id); ?>
          </div>
        </div>
      </div>
    </div>
  </div>


  <script type="text/javascript"><!--
    function resetZoneSelected(theForm) {
      if (theForm.state.value != '') {
        theForm.zone_id.selectedIndex = '0';
        if (theForm.zone_id.options.length > 0) {
          theForm.state.value = '<?php echo $CLICSHOPPING_TaxGeoZones->getDef('js_state_select'); ?>';
        }
      }
    }

    function update_zone(theForm) {
      var NumState = theForm.zone_id.options.length;
      var SelectedCountry = "";

      while (NumState > 0) {
        NumState--;
        theForm.zone_id.options[NumState] = null;
      }

      SelectedCountry = theForm.zone_country_id.options[theForm.zone_country_id.selectedIndex].value;

      <?php echo HTMLOverrideAdmin::getJsZoneList('SelectedCountry', 'theForm', 'zone_id'); ?>

    }

    //--></script>


  </form>
</div>

