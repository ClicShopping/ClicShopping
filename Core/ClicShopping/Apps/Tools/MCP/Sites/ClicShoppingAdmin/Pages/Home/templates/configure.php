<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_MCP = Registry::get('MCP');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$current_module = $CLICSHOPPING_Page->data['current_module'];

$CLICSHOPPING_MCP_Config = Registry::get('MCPAdminConfig' . $current_module);

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
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/migration.png', $CLICSHOPPING_MCP->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_MCP->getDef('heading_title'); ?></span>
          <span
            class="col-md-7 text-end"><?php echo HTML::button($CLICSHOPPING_MCP->getDef('button_mcp_data'), null, $CLICSHOPPING_MCP->link('MCP'), 'success'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="appMCPToolbar">
    <li class="nav-item">
      <?php
      foreach ($CLICSHOPPING_MCP->getConfigModules() as $m) {

        if ($CLICSHOPPING_MCP->getConfigModuleInfo($m, 'is_installed') === true) {
          echo '<li class="nav-link active" data-module="' . $m . '"><a href="' . $CLICSHOPPING_MCP->link('Configure&module=' . $m) . '">' . $CLICSHOPPING_MCP->getConfigModuleInfo($m, 'short_title') . '</a></li>';
        }
}
      ?>
    </li>
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="true"
         aria-expanded="false">Install</a>
      <div class="dropdown-menu">
        <?php
        foreach ($CLICSHOPPING_MCP->getConfigModules() as $m) {
          if ($CLICSHOPPING_MCP->getConfigModuleInfo($m, 'is_installed') === false) {
            echo '<a class="dropdown-item" href="' . $CLICSHOPPING_MCP->link('Configure&module=' . $m) . '">' . $CLICSHOPPING_MCP->getConfigModuleInfo($m, 'title') . '</a>';
          }
}
        ?>
      </div>
    </li>
  </ul>
  <?php
  if ($CLICSHOPPING_MCP_Config->is_installed === true) {
    ?>
    <form name="CustomersCustomersConfigure"
          action="<?php echo $CLICSHOPPING_MCP->link('Configure&Process&module=' . $current_module); ?>"
          method="post">

      <div class="mainTitle">
        <?php echo $CLICSHOPPING_MCP->getConfigModuleInfo($current_module, 'title'); ?>
      </div>
      <div class="adminformTitle">
        <div class="card-block">

          <p class="card-text">
            <?php
            foreach ($CLICSHOPPING_MCP_Config->getInputParameters() as $cfg) {
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
        echo HTML::button($CLICSHOPPING_MCP->getDef('button_save'), null, null, 'success');

        if ($CLICSHOPPING_MCP->getConfigModuleInfo($current_module, 'is_uninstallable') === true) {
          echo '<span class="float-end">' . HTML::button($CLICSHOPPING_MCP->getDef('button_dialog_uninstall'), null, '#', 'warning', ['params' => 'data-bs-toggle="modal" data-bs-target="#ppUninstallModal"']) . '</span>';
        }
        ?>
      </div>
    </form>
    <?php
    if ($CLICSHOPPING_MCP->getConfigModuleInfo($current_module, 'is_uninstallable') === true) {
      ?>
      <div id="ppUninstallModal" class="modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span>
              </button>
              <h4 class="modal-title"><?php echo $CLICSHOPPING_MCP->getDef('dialog_uninstall_title'); ?></h4>
            </div>
            <div class="modal-body">
              <?php echo $CLICSHOPPING_MCP->getDef('dialog_uninstall_body'); ?>
            </div>
            <div class="modal-footer">
              <?php echo HTML::button($CLICSHOPPING_MCP->getDef('button_delete'), null, $CLICSHOPPING_MCP->link('Configure&Delete&module=' . $current_module), 'danger'); ?>
              <?php echo HTML::button($CLICSHOPPING_MCP->getDef('button_uninstall'), null, $CLICSHOPPING_MCP->link('Configure&Uninstall&module=' . $current_module), 'danger'); ?>
              <?php echo HTML::button($CLICSHOPPING_MCP->getDef('button_cancel'), null, '#', 'warning', ['params' => 'data-bs-dismiss="modal"']); ?>
            </div>
          </div>
        </div>
      </div>
      <?php
    }
} else {
    ?>
    <div class="col-md-12 mainTitle">
      <strong><?php echo $CLICSHOPPING_MCP->getConfigModuleInfo($current_module, 'title'); ?></strong></div>
    <div class="adminformTitle">
      <div class="row">
        <div class="mt-1"></div>
        <div class="col-md-12">
          <div><?php echo $CLICSHOPPING_MCP->getConfigModuleInfo($current_module, 'introduction'); ?></div>
          <div class="separator">
            <div><?php echo HTML::button($CLICSHOPPING_MCP->getDef('button_install_title', ['title' => $CLICSHOPPING_MCP->getConfigModuleInfo($current_module, 'title')]), null, $CLICSHOPPING_MCP->link('Configure&Install&module=' . $current_module), 'warning'); ?></div>
          </div>
        </div>
      </div>
    </div>
    <?php
  }
  ?>
</div>
<div class="py-4"></div>