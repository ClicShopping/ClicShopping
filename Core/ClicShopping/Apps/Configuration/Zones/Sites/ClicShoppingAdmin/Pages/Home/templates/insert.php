<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Zones = Registry::get('Zones');
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
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/zones.gif', $CLICSHOPPING_Zones->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Zones->getDef('heading_title'); ?></span>
          <span class="col-md-7 text-end">
<?php
echo HTML::button($CLICSHOPPING_Zones->getDef('button_cancel'), null, $CLICSHOPPING_Zones->link('Zones'), 'warning') . ' ';
echo HTML::form('status_zones', $CLICSHOPPING_Zones->link('Zones&Insert&page=' . $page));
echo HTML::button($CLICSHOPPING_Zones->getDef('button_insert'), null, null, 'success')
?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_Zones->getDef('text_info_heading_new_zone'); ?></strong></div>
  <div class="adminformTitle">

    <div class="row">
      <div class="col-md-12">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Zones->getDef('text_info_edit_intro'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Zones->getDef('text_info_edit_intro'); ?></label>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Zones->getDef('text_info_zones_name'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Zones->getDef('text_info_zones_name'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('zone_name', '', 'required aria-required="true"'); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Zones->getDef('text_info_zones_code'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Zones->getDef('text_info_zones_code'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::inputField('zone_code'); ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col-md-5">
        <div class="form-group row">
          <label for="<?php echo $CLICSHOPPING_Zones->getDef('text_info_country_name'); ?>"
                 class="col-5 col-form-label"><?php echo $CLICSHOPPING_Zones->getDef('text_info_country_name'); ?></label>
          <div class="col-md-5">
            <?php echo HTML::selectMenuCountryList('zone_country_id', null); ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  </form>
</div>