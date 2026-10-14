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
use ClicShopping\Sites\ClicShoppingAdmin\CallUserFuncModule;
use ClicShopping\Apps\Configuration\Modules\Classes\ClicShoppingAdmin\ModulesAdmin;

$CLICSHOPPING_Modules = Registry::get('Modules');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_CfgModule = Registry::get('CfgModulesAdmin');
$CLICSHOPPING_Db = Registry::get('Db');

Registry::set('ModulesAdmin', new ModulesAdmin());
$CLICSHOPPING_ModulesAdmin = Registry::get('ModulesAdmin');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$set = $_GET['set'] ?? '';

$modules = $CLICSHOPPING_CfgModule->getAll();

if (empty($set) || !$CLICSHOPPING_CfgModule->exists($set)) {
  $set = $modules[0]['code'];
}

$module_type = $CLICSHOPPING_CfgModule->get($set, 'code');
$module_directory = $CLICSHOPPING_CfgModule->get($set, 'directory');
$module_language_directory = $CLICSHOPPING_CfgModule->get($set, 'language_directory');

$module_site = $CLICSHOPPING_CfgModule->get($set, 'site');
$module_key = $CLICSHOPPING_CfgModule->get($set, 'key');

$template_integration = $CLICSHOPPING_CfgModule->get($set, 'template_integration');

define('HEADING_TITLE', $CLICSHOPPING_CfgModule->get($set, 'title'));

$appModuleType = $CLICSHOPPING_ModulesAdmin->getSwitchModules($module_type);

echo HTML::form('modules', $CLICSHOPPING_Modules->link('Modules&Update&set=' . $set . '&module=' . $_GET['module']));
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/products_unit.png', $CLICSHOPPING_Modules->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Modules->getDef('heading_title'); ?></span>
          <span class="col-md-6 text-end">
<?php
echo '<span class="cols-xs-3 float-end">';
echo HTML::button(CLICSHOPPING::getDef('button_cancel'), null, $CLICSHOPPING_Modules->link('Modules&set=' . $set), 'warning') . '&nbsp;';
echo HTML::button(CLICSHOPPING::getDef('button_update'), null, null, 'success');
echo '</span>';
?>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <?php
  $modules_installed = (\defined($module_key) ? explode(';', \constant($module_key)) : array());

  $new_modules_counter = 0;

  $file_extension = substr(CLICSHOPPING::getIndex(), strrpos(CLICSHOPPING::getIndex(), '.'));
  $directory_array = [];

  if ($dir = @dir($module_directory)) {
    while ($file = $dir->read()) {
      if (!is_dir($module_directory . $file)) {
        if (substr($file, strrpos($file, '.')) == $file_extension) {
          if (isset($_GET['list']) && ($_GET['list'] == 'new')) {
            if (!\in_array($file, $modules_installed)) {
              $directory_array[] = $file;
            }
          } else {
            if (\in_array($file, $modules_installed)) {
              $directory_array[] = $file;
            } else {
              $new_modules_counter++;
            }
          }
        }
      }
    }
    $dir->close();
  }

  if (isset($appModuleType)) {
    foreach (Apps::getModules($appModuleType) as $k => $v) {
      if (isset($_GET['list']) && ($_GET['list'] == 'new')) {
        if (!\in_array($k, $modules_installed)) {
          $directory_array[] = $k;
        }
      } else {
        if (\in_array($k, $modules_installed)) {
          $directory_array[] = $k;
        } else {
          $new_modules_counter++;
        }
      }
    }
  }

  sort($directory_array);

  $installed_modules = [];

  for ($i = 0, $n = \count($directory_array); $i < $n; $i++) {
    $file = $directory_array[$i];

    if (str_contains($file, '\\')) {
      $file_extension = '';

      $class = Apps::getModuleClass($file, $appModuleType);

      $module = new $class();
      $module->code = $file;

      $class = $file;
    } else {
      $file_extension = substr(CLICSHOPPING::getIndex(), strrpos(CLICSHOPPING::getIndex(), '.'));

      include($module_directory . $file);

      $class = substr($file, 0, strrpos($file, '.'));

      if (class_exists($class)) {
        $module = new $class;
      }
    }

    if (isset($module)) {
      if ($module->check() > 0) {
        if (($module->sort_order > 0) && !isset($installed_modules[$module->sort_order])) {
          $installed_modules[$module->sort_order] = $file;
        } else {
          $installed_modules[] = $file;
        }
      }

      if ((!isset($_GET['module']) || (isset($_GET['module']) && ($_GET['module'] == $class))) && !isset($mInfo)) {
        $module_info = [
          'code' => $module->code,
          'title' => $module->title,
          'description' => $module->description,
          'status' => $module->check(),
          'signature' => (isset($module->signature) ? $module->signature : null),
          'api_version' => (isset($module->api_version) ? $module->api_version : null)
        ];

        $module_keys = $module->keys();

        $keys_extra = [];

        for ($j = 0, $k = \count($module_keys); $j < $k; $j++) {

          $Qkeys = $CLICSHOPPING_Db->get('configuration', [
            'configuration_title',
            'configuration_value',
            'configuration_description',
            'use_function',
            'set_function'
          ], [
              'configuration_key' => $module_keys[$j]
            ]
          );

          $keys_extra[$module_keys[$j]]['title'] = $Qkeys->value('configuration_title');
          $keys_extra[$module_keys[$j]]['value'] = $Qkeys->value('configuration_value');
          $keys_extra[$module_keys[$j]]['description'] = $Qkeys->value('configuration_description');
          $keys_extra[$module_keys[$j]]['use_function'] = $Qkeys->value('use_function');
          $keys_extra[$module_keys[$j]]['set_function'] = $Qkeys->value('set_function');
        }

        $module_info['keys'] = $keys_extra;

        $mInfo = new \ArrayObject($module_info, \ArrayObject::ARRAY_AS_PROPS);
      }
    }
  }

  if (isset($mInfo) && (str_contains($mInfo->code, '\\'))) {
    $file_extension = '';
  } else {
    $file_extension = substr(CLICSHOPPING::getIndex(), strrpos(CLICSHOPPING::getIndex(), '.'));
  }

  $keys = '';
  ?>
  <div id="orderTabs" style="overflow: auto;">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li
        class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . CLICSHOPPING::getDef('tab_general') . '</a>'; ?></li>
    </ul>
    <div class="tabsClicShopping">
      <div class="tab-content">
        <div class="mainTitle"><?php echo CLICSHOPPING::getDef('text_box_heading_module'); ?></div>
        <div class="adminformTitle">
          <?php
          if (\is_array($mInfo->keys)) {
            foreach ($mInfo->keys as $key => $value) {
              $keys .= '<strong>' . $value['title'] . '</strong><br />' . $value['description'] . '<br />';

              if (\strlen($value['set_function']) > 0) {
                $keys .= CallUserFuncModule::execute($value['set_function'], $value['value'], $key);
              } else {
                $keys .= HTML::inputField('configuration[' . $key . ']', $value['value']);
              }
              $keys .= '<br /><br />';
            }

            $keys = substr($keys, 0, strrpos($keys, '<br /><br />'));
            echo $keys;
          }
          ?>
        </div>
      </div>
    </div>
  </div>
</div>
</form>
<div class="py-3"></div>