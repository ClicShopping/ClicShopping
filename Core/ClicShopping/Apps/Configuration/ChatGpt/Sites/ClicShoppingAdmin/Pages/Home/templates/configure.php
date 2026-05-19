<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');
$CLICSHOPPING_Composer = Registry::get('Composer');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Db = Registry::get('Db');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$current_module = $CLICSHOPPING_Page->data['current_module'];

$CLICSHOPPING_ChatGpt_Config = Registry::get('ChatGptAdminConfig' . $current_module);

if ($CLICSHOPPING_MessageStack->exists('ChatGpt')) {
  echo $CLICSHOPPING_MessageStack->get('ChatGpt');
}
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/chatgpt.gif', $CLICSHOPPING_ChatGpt->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-8 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ChatGpt->getDef('heading_title'); ?></span>
          <span class="col-md-3 text-end">
          <?php
            if (\defined('CLICSHOPPING_APP_CHATGPT_RA_STATUS')) {
              $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_categories_embedding"');
              $result = $Qcheck->fetch();

              // If the table does not exist (empty result), show the form
              if (empty($result) || $result === false) {
                echo HTML::form('updateChatGpt', $CLICSHOPPING_ChatGpt->link('Configure&Update'));
                echo HTML::button($CLICSHOPPING_ChatGpt->getDef('module_ch_sql_update'), null, $CLICSHOPPING_ChatGpt->link('ChatGpt'), 'warning',  ['params' => 'id="sqlUpdate" onclick="return confirm(\'' . $CLICSHOPPING_ChatGpt->getDef('module_ch_confirmation') . '\');"']) . '&bnsp;';
                echo '</form>';
              }
            }

            echo HTML::button($CLICSHOPPING_ChatGpt->getDef('module_ch_short_title'), null, $CLICSHOPPING_ChatGpt->link('DashBoard'), 'primary');
          ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="appChatGptToolbar">
    <li class="nav-item">
      <?php
      foreach ($CLICSHOPPING_ChatGpt->getConfigModules() as $m) {
        if ($CLICSHOPPING_ChatGpt->getConfigModuleInfo($m, 'is_installed') === true) {
          echo '<li class="nav-link active" data-module="' . $m . '"><a href="' . $CLICSHOPPING_ChatGpt->link('Configure&module=' . $m) . '">' . $CLICSHOPPING_ChatGpt->getConfigModuleInfo($m, 'short_title') . '</a></li>';
        }
      }
      ?>
    </li>
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="true"
         aria-expanded="false">Install</a>
      <div class="dropdown-menu">
        <?php
        foreach ($CLICSHOPPING_ChatGpt->getConfigModules() as $m) {
          if ($CLICSHOPPING_ChatGpt->getConfigModuleInfo($m, 'is_installed') === false) {
            echo '<a class="dropdown-item" href="' . $CLICSHOPPING_ChatGpt->link('Configure&module=' . $m) . '">' . $CLICSHOPPING_ChatGpt->getConfigModuleInfo($m, 'title') . '</a>';
          }
        }
        ?>
      </div>
    </li>
  </ul>
  <?php
  if ($CLICSHOPPING_Composer->checkComposerInstalled() === false) {
    echo '<div class="alert alert-warning" role="alert">' . $CLICSHOPPING_ChatGpt->getDef('text_error_composer') . '</div>';
  }

  if ($CLICSHOPPING_Composer->checkExecEnabled() === false) {
    echo '<div class="alert alert-warning" role="alert">' . $CLICSHOPPING_ChatGpt->getDef('text_error_exec') . '</div>';
  }

  if ($CLICSHOPPING_ChatGpt_Config->is_installed === true) {
    ?>
    <form name="ConfigurationChatGptConfigure"
          action="<?php echo $CLICSHOPPING_ChatGpt->link('Configure&Process&module=' . $current_module); ?>"
          method="post">

      <div class="mainTitle">
        <?php echo $CLICSHOPPING_ChatGpt->getConfigModuleInfo($current_module, 'title'); ?>
      </div>
      <div class="adminformTitle">
        <div class="card-block">
          <p class="card-text">
            <?php
            foreach ($CLICSHOPPING_ChatGpt_Config->getInputParameters() as $cfg) {
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
        echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_save'), null, null, 'success');
        if ($CLICSHOPPING_ChatGpt->getConfigModuleInfo($current_module, 'is_uninstallable') === true) {
          echo '<span class="float-end">' . HTML::button($CLICSHOPPING_ChatGpt->getDef('button_dialog_uninstall'), null, '#', 'warning', ['params' => 'data-bs-toggle="modal" data-bs-target="#ppUninstallModal"']) . '</span>';
        }
        ?>
      </div>
    </form>
    <?php
    if ($CLICSHOPPING_ChatGpt->getConfigModuleInfo($current_module, 'is_uninstallable') === true) {
      ?>
      <div id="ppUninstallModal" class="modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span>
              </button>
              <h4 class="modal-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('dialog_uninstall_title'); ?></h4>
            </div>
            <div class="modal-body">
              <?php echo $CLICSHOPPING_ChatGpt->getDef('dialog_uninstall_body'); ?>
            </div>
            <div class="modal-footer">
              <?php echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_delete'), null, $CLICSHOPPING_ChatGpt->link('Configure&Delete&module=' . $current_module), 'danger'); ?>
              <?php echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_uninstall'), null, $CLICSHOPPING_ChatGpt->link('Configure&Uninstall&module=' . $current_module), 'danger'); ?>
              <?php echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_cancel'), null, '#', 'warning', ['params' => 'data-bs-dismiss="modal"']); ?>
            </div>
          </div>
        </div>
      </div>
      <?php
    }
  } else {
    ?>
    <div class="col-md-12 mainTitle">
      <strong><?php echo $CLICSHOPPING_ChatGpt->getConfigModuleInfo($current_module, 'title'); ?></strong>
    </div>
    <div class="adminformTitle">
      <div class="row">
        <div class="mt-1"></div>
        <div class="col-md-12">
          <div><?php echo $CLICSHOPPING_ChatGpt->getConfigModuleInfo($current_module, 'introduction'); ?></div>
          <div class="mt-1"></div>
          <div><?php echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_install_title', ['title' => $CLICSHOPPING_ChatGpt->getConfigModuleInfo($current_module, 'title')]), null, $CLICSHOPPING_ChatGpt->link('Configure&Install&module=' . $current_module), 'warning'); ?></div>
        </div>
      </div>
    </div>
    <?php
  }
  ?>
</div>
<div class="py-5"></div>
