<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Stripe = Registry::get('Stripe');
$CLICSHOPPING_Composer = Registry::get('Composer');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$current_module = $CLICSHOPPING_Page->data['current_module'];

$CLICSHOPPING_Stripe_Config = Registry::get('StripeAdminConfig' . $current_module);

if ($CLICSHOPPING_MessageStack->exists('Stripe')) {
  echo $CLICSHOPPING_MessageStack->get('Stripe');
}
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/modules_modules_checkout_payment.gif', $CLICSHOPPING_Stripe->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Stripe->getDef('heading_title'); ?></span>
          <span
            class="col-md-6 text-end"><?php echo HTML::button($CLICSHOPPING_Stripe->getDef('button_sort_order'), null, CLICSHOPPING::link(null, 'A&Configuration\Modules&Modules&set=payment'), 'primary'); ?>
          <span
            class="col-md-1 text-end"><?php echo HTML::button($CLICSHOPPING_Stripe->getDef('button_help'), null, $CLICSHOPPING_Stripe->link('Help'), 'info'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 alert alert-warning" role="alert">
    <span><?php echo $CLICSHOPPING_Stripe->getDef('text_warning_group'); ?></span>
  </div>

  <?php
  if ($CLICSHOPPING_Composer->checkComposerInstalled() === false) {
    echo '<div class="alert alert-warning" role="alert">' . $CLICSHOPPING_Stripe->getDef('text_error_composer') . '</div>';
  }

  if ($CLICSHOPPING_Composer->checkExecEnabled() === false) {
    echo '<div class="alert alert-warning" role="alert">' . $CLICSHOPPING_Stripe->getDef('text_error_exec') . '</div>';
  }

  if ($CLICSHOPPING_Stripe_Config->is_installed === true) {
    ?>
    <form name="Configure"
          action="<?php echo $CLICSHOPPING_Stripe->link('Configure&Process&module=' . $current_module); ?>"
          method="post">

      <div class="mainTitle">
        <?php echo $CLICSHOPPING_Stripe->getConfigModuleInfo($current_module, 'title'); ?>
      </div>
      <div class="adminformTitle">
        <div class="card-block">

          <p class="card-text">
            <?php
            foreach ($CLICSHOPPING_Stripe_Config->getInputParameters() as $cfg) {
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
        echo HTML::button($CLICSHOPPING_Stripe->getDef('button_save'), null, null, 'success');

        if ($CLICSHOPPING_Stripe->getConfigModuleInfo($current_module, 'is_uninstallable') === true) {
          echo '<span class="float-end">' . HTML::button($CLICSHOPPING_Stripe->getDef('button_dialog_uninstall'), null, '#', 'warning', ['params' => 'data-bs-toggle="modal" data-bs-target="#ppUninstallModal"']) . '</span>';
        }
        ?>

    </form>
    <?php
    if ($CLICSHOPPING_Stripe->getConfigModuleInfo($current_module, 'is_uninstallable') === true) {
      ?>
      <div id="ppUninstallModal" class="modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span>
              </button>
              <h4 class="modal-title"><?php echo $CLICSHOPPING_Stripe->getDef('dialog_uninstall_title'); ?></h4>
            </div>
            <div class="modal-body">
              <?php echo $CLICSHOPPING_Stripe->getDef('dialog_uninstall_body'); ?>
            </div>
            <div class="modal-footer">
              <?php echo HTML::button($CLICSHOPPING_Stripe->getDef('button_delete'), null, $CLICSHOPPING_Stripe->link('Configure&Delete&module=' . $current_module), 'danger'); ?>
              <?php echo HTML::button($CLICSHOPPING_Stripe->getDef('button_uninstall'), null, $CLICSHOPPING_Stripe->link('Configure&Uninstall&module=' . $current_module), 'danger'); ?>
              <?php echo HTML::button($CLICSHOPPING_Stripe->getDef('button_cancel'), null, '#', 'warning', ['params' => 'data-bs-dismiss="modal"']); ?>
            </div>
          </div>
        </div>
      </div>
      <?php
    }
  } else {
    ?>
    <div class="col-md-12 mainTitle">
      <strong><?php echo $CLICSHOPPING_Stripe->getConfigModuleInfo($current_module, 'title'); ?></strong></div>
    <div class="adminformTitle">
      <div class="row">
        <div class="mt-1"></div>
        <div class="col-md-12">
          <div><?php echo $CLICSHOPPING_Stripe->getConfigModuleInfo($current_module, 'introduction'); ?></div>
          <div class="separator">
            <div><?php echo HTML::button($CLICSHOPPING_Stripe->getDef('button_install_title', ['title' => $CLICSHOPPING_Stripe->getConfigModuleInfo($current_module, 'title')]), null, $CLICSHOPPING_Stripe->link('Configure&Install&module=' . $current_module), 'warning'); ?></div>
          </div>
        </div>
      </div>
    </div>
    <?php
  }
  ?>
  <div class="py-4"></div>
</div>
