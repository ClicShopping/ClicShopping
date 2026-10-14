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

$CLICSHOPPING_Zones = Registry::get('Zones');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$Qzones = $CLICSHOPPING_Zones->db->prepare('select *
                                              from :table_zones z,
                                                   :table_countries c
                                              where z.zone_country_id = c.countries_id
                                              and zone_id = :zone_id
                                             ');

$Qzones->bindInt(':zone_id', $_GET['cID']);
$Qzones->execute();

$cInfo = new ObjectInfo($Qzones->toArray());

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
            class="col-md-2 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Zones->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_Zones->getDef('text_info_heading_delete_zone'); ?></strong></div>
  <?php echo HTML::form('zones', $CLICSHOPPING_Zones->link('Zones&DeleteConfirm&page=' . $page . '&cID=' . $cInfo->zone_id)); ?>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo $CLICSHOPPING_Zones->getDef('text_info_delete_info'); ?><br/><br/></div>
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo '<strong>' . $cInfo->zone_name . '</strong>'; ?><br/><br/></div>
      <div class="col-md-12 text-center">
        <span><br/><?php echo HTML::button($CLICSHOPPING_Zones->getDef('button_delete'), null, null, 'danger', null, 'sm') . ' </span><span>' . HTML::button($CLICSHOPPING_Zones->getDef('button_cancel'), null, $CLICSHOPPING_Zones->link('Zones&page=' . (int)$_GET['page'] . '&cID=' . $cInfo->zone_id), 'warning', null, 'sm'); ?></span>
      </div>
    </div>
  </div>
  </form>
</div>