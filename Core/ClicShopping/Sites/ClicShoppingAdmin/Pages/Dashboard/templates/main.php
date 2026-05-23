<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\Apps;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Hooks = Registry::get('Hooks');
?>
<!-- header_eof //-->
<!-- body //-->
<div class="contentBody dashboard">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="card-title">
          <div class="row col-sm-12">
            <span class="col-sm-1 logoHeading">
              <?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/home.gif', CLICSHOPPING::getDef('heading_title'), '40', '40'); ?>
            </span>
            <span class="col-sm-2 pageHeading"><?php echo CLICSHOPPING::getDef('heading_title'); ?></span>
            <span class="col-sm-9 text-end">
              <?php echo $CLICSHOPPING_Hooks->output('DashboardShortCut', 'DashboardShortCut', null, 'display'); ?>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col-md-12">
      <div class="d-flex flex-wrap justify-content-center">
        <?php
        echo $CLICSHOPPING_Hooks->output('TopDashboard', 'TopDashboard', null, 'display');

        $source_folder = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/Module/Hooks/ClicShoppingAdmin/TopDashboard/';

        if (is_dir($source_folder)) {
        $files_get = $CLICSHOPPING_Template->getSpecificFiles($source_folder, 'TopDashboard*');

        if (\is_array($files_get)) {
          foreach ($files_get as $value) {
            if (!empty($value['name'])) {
              echo $CLICSHOPPING_Hooks->output('TopDashboard', $value['name']);
            }
          }
        }
        ?>
      </div>
    </div>
    <?php
    echo $CLICSHOPPING_Hooks->output('TopDashboard', 'PageTabContent', null, 'display');
    }
    ?>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12">
    <span class="col-md-8 float-start">
      <div class="row">
        <div class="col-md-12">
          <div class="d-flex flex-wrap">
<?php
if (\defined('MODULE_ADMIN_DASHBOARD_INSTALLED') && !\is_null(MODULE_ADMIN_DASHBOARD_INSTALLED)) {
  $adm_array = explode(';', MODULE_ADMIN_DASHBOARD_INSTALLED);

  if (!empty(MODULE_ADMIN_DASHBOARD_INSTALLED)) {

    $col = 0;

    foreach ($adm_array as $adm) {
      if (str_contains($adm, '\\')) {
        $class = Apps::getModuleClass($adm, 'AdminDashboard');
      }

      $ad = new $class();

      if ($ad->isEnabled()) {
        echo $ad->getOutput();
      }
    }
  } else {
    echo '<div class="alert alert-primary">';
    echo '<div class="col-md-12 text-center">' . HTML::link(CLICSHOPPING::link(null, 'A&Configuration\Modules&Modules&set=dashboard&list=new'), ClicShopping::getDef('text_install_dashboard')) . '<br /></div>';
    echo '<div class="col-md-12 text-center">' . ClicShopping::getDef('text_good_luck') . '</div>';
    echo '</div>';
  }
}
?>
        </div>
      </div>
    </div>
  </span>

    <!-- ------------------------------------------------------------ //-->
    <!--          ONGLET Statistics           //-->
    <!-- ------------------------------------------------------------ //-->

    <span class="col-md-4 float-md-end" id="indexTabs" style="overflow: auto;">
<style>
  .nav {
    width: 400px;
  }
</style>
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab" class="nav">
      <li class="nav-item">
        <a href="#tab1" role="tab" data-bs-toggle="tab"
           class="nav-link active"><?php echo CLICSHOPPING::getDef('tab_statistics'); ?></a>
      </li>
      <li class="nav-item">
        <a href="#tab3" role="tab" data-bs-toggle="tab"
           class="nav-link"><?php echo CLICSHOPPING::getDef('tab_customer'); ?></a>
      </li>
    </ul>

    <div class="tabsClicShopping">
      <div class="tab-content">
        <div class="tab-pane active" id="tab1">
          <div class="mainTitle"><?php echo CLICSHOPPING::getDef('title_index_order'); ?></div>
          <div
            class="adminformTitle backgroundBlank"><?php echo $CLICSHOPPING_Hooks->output('Dashboard', 'ActionStatsCountStatus'); ?></div>
          <div class="mainTitle"><?php echo CLICSHOPPING::getDef('heading_title_divers'); ?></div>
          <div class="adminformTitle backgroundBlank">
            <div id="tab1Content"></div>
            <?php echo $CLICSHOPPING_Hooks->output('StatsDashboard', 'PageTabContent', null, 'display'); ?>
          </div>
        </div>
        <!-- ------------------------------------------------------------ //-->
        <!--          ONGLET Information         //-->
        <!-- ------------------------------------------------------------ //-->
        <div class="tab-pane" id="tab3">
          <div class="mainTitle"><?php echo CLICSHOPPING::getDef('title_customer_information'); ?></div>
          <div
            class="backgroundBlank"><?php echo $CLICSHOPPING_Hooks->output('Dashboard', 'ActionInformation'); ?></div>
        </div>
      </div>
       <?php echo $CLICSHOPPING_Hooks->output('Index', 'PageTabTwitter', null, 'display'); ?>
    </div>
    <div><?php echo $CLICSHOPPING_Hooks->output('Dashboard', 'ActionLinkedin'); ?></div>
    <div><?php echo $CLICSHOPPING_Hooks->output('Dashboard', 'ActionDonate'); ?></div>
  </span>
    <div class="clearfix"></div>
  </div>
</div>
<div class="py-4"></div>
