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

use ClicShopping\Apps\Tools\Upgrade\Classes\ClicShoppingAdmin\Github;

$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Upgrade = Registry::get('Upgrade');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');

$CLICSHOPPING_Github = new Github();
$CLICSHOPPING_Page = Registry::get('Site')->getPage();

if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
}
?>
  <div class="contentBody">
    <div class="row">
      <div class="col-md-12">
        <div class="card card-block headerCard">
          <div class="row col-md-12">
            <div class="col-md-12">
              <dic class="row">
              <span
                class="col-md-1"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/apps.png', $CLICSHOPPING_Upgrade->getDef('heading_title'), '40', '40'); ?></span>
                <span
                  class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Upgrade->getDef('heading_title'); ?></span>
                <span
                  class="col-md-6 text-end"><?php echo HTML::button($CLICSHOPPING_Upgrade->getDef('button_marketplace'), null, $CLICSHOPPING_Upgrade->link('Marketplace'), 'primary'); ?>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-1"></div>
    <div class="col-md-12">
      <div class="row">
        <span class="alert alert-info" role="alert">
          <?php echo $CLICSHOPPING_Upgrade->getDef('text_step_upgrade'); ?>
        </span>
      </div>
      <div class="mt-1"></div>

      <?php
      $json_file_directory = CLICSHOPPING::BASE_DIR . 'Work/Temp/' . $_SESSION['app_json'];

      if (is_file($json_file_directory)) {
        ?>
        <div class="row">
        <span class="col-md-6">
          <blockquote>
            <ul>
              <?php
              $json_file = str_replace('.zip', '', $_SESSION['app_json']);
              $json_file = CLICSHOPPING::BASE_DIR . 'Work/Cache/Marketplace/' . $json_file . '.json';

              if (is_file($json_file)) {
                $get_json_file = file_get_contents($json_file, true);

                $result = json_decode($get_json_file);

                foreach ($result as $key => $value) {
                  $text = '';

                  if (!\is_array($value)) {
                    $text = $value;
                  }

                  if ($key == 'module_directory') {
                    $_SESSION['module_directory'] = $value;
                  }

                  if ($key == 'apps_name') {
                    $_SESSION['module_apps_name'] = $value;
                  }

                  echo '<li>' . $key . ' : ' . $text . '</li>';

                  if (\is_array($value)) {
                    echo '<div class="mt-1"></div>';

                    foreach ($value as $item) {
                      echo '      -' . $item->name . '<br />';
                      echo '      -' . $item->company . '<br />';
                      echo '      -' . $item->email . '<br />';
                      echo '      -' . $item->website . '<br />';
                      echo '      -' . $item->Community . '<br />';
                    }
                  }
                }

                if (isset($_SESSION['module_directory'])) {
                  echo '<div class="mt-1"></div>';
                  echo '<div class="alert alert-success" role="alert">';
                  echo '<span class="text-center"><h3>';
                  echo HTML::link(CLICSHOPPING::getConfig('http_server') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin') . 'index.php?A&' . $_SESSION['module_directory'] . '\\' . $_SESSION['module_apps_name'], $CLICSHOPPING_Upgrade->getDef('text_activate'));
                  echo '</h3></span>';
                  echo '</div>';
                }
              }
              ?>
            </ul>
          </blockquote>
        </span>
        </div>
        <?php
      }
      ?>
    </div>
    <div class="text-center">
      <?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'marketplace.png', $CLICSHOPPING_Upgrade->getDef('heading_title')); ?>
    </div>

  </div>
<?php
unset($_SESSION['module_apps_name']);
unset($_SESSION['module_directory']);
unset($_SESSION['app_json']);


