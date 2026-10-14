<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\Registry;

  $CLICSHOPPING_Template = Registry::get('TemplateAdmin');
  $CLICSHOPPING_Hooks = Registry::get('Hooks');
  $CLICSHOPPING_Language = Registry::get('Language');
?>
<!DOCTYPE html>
<html <?php echo CLICSHOPPING::getDef('html_params'); ?>>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=<?php echo CLICSHOPPING::getDef('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="robots" content="noindex,nofollow" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="generator" content="ClicShopping" />
  <meta name="author" content="ClicShopping" />
  <meta name="description" content="ClicShopping Administration" />
  <title>ClicShopping, <?php echo CLICSHOPPING::getDef('title', ['store_name' => STORE_NAME]); ?></title>
  <base href="<?php echo CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin'); ?>" />
  <link rel="icon" type="image/webp"  href="<?php echo CLICSHOPPING::link('Shop/images/logo_clicshopping.webp'); ?>" />

  <?php
     $source_folder = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/Module/Hooks/ClicShoppingAdmin/Header/';
     $output = 'HeaderOutput*';
     $call = 'HeaderCall*';
     $hook_call = 'Header';

     $CLICSHOPPING_Template->useRecursiveModulesHooksForTemplate($source_folder,  $output,  $call, $hook_call);
?>
  <script src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/general.js'); ?>"></script>
</head>

<body onload="SetFocus();">
<!-- page loader -->
  <?php
    if (DEBUG_MODE == 'False') {
   ?>
  <div id="preloader">
    <div id="preloader_status"><i class="spinner-border" role="status"></i></div>
  </div>
  <?php
    }
  ?>
  <div class="container-fluid">
    <div class="col-md-12">
      <noscript>
        <div class="alert alert-warning no-script" role="alert">
          <div class="no-script-inner"><?php echo CLICSHOPPING::getDef('no_script_text'); ?></div>
        </div>
      </noscript>
    </div>
    <div class="wrapper" id="wrapper">
<?php
//**************************************
// Administrator menu
//**************************************

  if (VERTICAL_MENU_CONFIGURATION == 'true') {
?>
          <?php echo $CLICSHOPPING_Hooks->output('Header', 'HeaderMenuSideBar', null, 'display'); ?>
<?php
  } else {
?>
        <div class="row">
          <?php echo $CLICSHOPPING_Hooks->output('Header', 'HeaderMenu', null, 'display'); ?>
        </div>
<?php
  }

//**************************************
// message menu
//**************************************
  
  if (isset($_SESSION['admin'])) {
?>
        <div class="contentBody dashboard">
          <?php echo $CLICSHOPPING_Hooks->output('Header', 'HeaderInfo', null, 'display'); ?>
        </div>
      <?php
  }

  if (Registry::get('MessageStack')->exists('main')) {
?>
        <div class="row">
          <div class="col-md-12">
            <?php echo Registry::get('MessageStack')->get('main'); ?>
          </div>
        </div>
<?php
  }
?>