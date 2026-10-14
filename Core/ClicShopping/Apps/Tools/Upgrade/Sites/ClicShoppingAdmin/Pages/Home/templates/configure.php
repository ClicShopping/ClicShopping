<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Upgrade = Registry::get('Upgrade');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$current_module = $CLICSHOPPING_Page->data['current_module'];

$CLICSHOPPING_Upgrade_Config = Registry::get('UpgradeAdminConfig' . $current_module);

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}
?>

<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/apps.png', $CLICSHOPPING_Upgrade->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Upgrade->getDef('heading_title'); ?></span>
          <span
            class="col-md-7 text-end"><?php echo HTML::button($CLICSHOPPING_Upgrade->getDef('button_upgrade'), null, $CLICSHOPPING_Upgrade->link('Upgrade'), 'success'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="appUpgradeToolbar">
    <li class="nav-item">
      <?php
      foreach ($CLICSHOPPING_Upgrade->getConfigModules() as $m) {

        if ($CLICSHOPPING_Upgrade->getConfigModuleInfo($m, 'is_installed') === true) {
          echo '<li class="nav-link active" data-module="' . $m . '"><a href="' . $CLICSHOPPING_Upgrade->link('Configure&module=' . $m) . '">' . $CLICSHOPPING_Upgrade->getConfigModuleInfo($m, 'short_title') . '</a></li>';
        }
      }
      ?>
    </li>
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="true"
         aria-expanded="false">Install</a>
      <div class="dropdown-menu">
        <?php
        foreach ($CLICSHOPPING_Upgrade->getConfigModules() as $m) {
          if ($CLICSHOPPING_Upgrade->getConfigModuleInfo($m, 'is_installed') === false) {
            echo '<a class="dropdown-item" href="' . $CLICSHOPPING_Upgrade->link('Configure&module=' . $m) . '">' . $CLICSHOPPING_Upgrade->getConfigModuleInfo($m, 'title') . '</a>';
          }
        }
        ?>
      </div>
    </li>
  </ul>
  <?php
  if ($CLICSHOPPING_Upgrade_Config->is_installed === true) {
    ?>
    <form name="CustomersCustomersConfigure"
          action="<?php echo $CLICSHOPPING_Upgrade->link('Configure&Process&module=' . $current_module); ?>"
          method="post">

      <div class="mainTitle">
        <?php echo $CLICSHOPPING_Upgrade->getConfigModuleInfo($current_module, 'title'); ?>
      </div>
      <div class="adminformTitle">
        <div class="card-block">
          <p class="card-text">
            <?php
            foreach ($CLICSHOPPING_Upgrade_Config->getInputParameters() as $cfg) {
              echo '<div>' . $cfg . '</div>';
              echo '<div class="mt-1"></div>';
            }
            ?>
          </p>
        </div>
      </div>

      <div class="mt-1"></div>
      <div class="col-md-12">
        <?php
        echo HTML::button($CLICSHOPPING_Upgrade->getDef('button_save'), null, null, 'success');

        if ($CLICSHOPPING_Upgrade->getConfigModuleInfo($current_module, 'is_uninstallable') === true) {
          echo '<span class="float-end">' . HTML::button($CLICSHOPPING_Upgrade->getDef('button_dialog_uninstall'), null, '#', 'warning', ['params' => 'data-bs-toggle="modal" data-bs-target="#ppUninstallModal"']) . '</span>';
        }
        ?>
      </div>
    </form>
    <?php
    if ($CLICSHOPPING_Upgrade->getConfigModuleInfo($current_module, 'is_uninstallable') === true) {
      ?>
      <div id="ppUninstallModal" class="modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span>
              </button>
              <h4 class="modal-title"><?php echo $CLICSHOPPING_Upgrade->getDef('dialog_uninstall_title'); ?></h4>
            </div>
            <div class="modal-body">
              <?php echo $CLICSHOPPING_Upgrade->getDef('dialog_uninstall_body'); ?>
            </div>
            <div class="modal-footer">
              <?php echo HTML::button($CLICSHOPPING_Upgrade->getDef('button_delete'), null, $CLICSHOPPING_Upgrade->link('Configure&Delete&module=' . $current_module), 'danger'); ?>
              <?php echo HTML::button($CLICSHOPPING_Upgrade->getDef('button_uninstall'), null, $CLICSHOPPING_Upgrade->link('Configure&Uninstall&module=' . $current_module), 'danger'); ?>
              <?php echo HTML::button($CLICSHOPPING_Upgrade->getDef('button_cancel'), null, '#', 'warning', ['params' => 'data-bs-dismiss="modal"']); ?>
            </div>
          </div>
        </div>
      </div>
      <?php
    }
  } else {
    ?>
    <div class="col-md-12 mainTitle">
      <strong><?php echo $CLICSHOPPING_Upgrade->getConfigModuleInfo($current_module, 'title'); ?></strong></div>
    <div class="adminformTitle">
      <div class="row">
        <div class="mt-1"></div>
        <div class="col-md-12">
          <div><?php echo $CLICSHOPPING_Upgrade->getConfigModuleInfo($current_module, 'introduction'); ?></div>
          <div class="separator">
            <div><?php echo HTML::button($CLICSHOPPING_Upgrade->getDef('button_install_title', ['title' => $CLICSHOPPING_Upgrade->getConfigModuleInfo($current_module, 'title')]), null, $CLICSHOPPING_Upgrade->link('Configure&Install&module=' . $current_module), 'warning'); ?></div>
          </div>
        </div>
      </div>
    </div>
    <?php
  }
  ?>
</div>
<div class="py-5"></div>