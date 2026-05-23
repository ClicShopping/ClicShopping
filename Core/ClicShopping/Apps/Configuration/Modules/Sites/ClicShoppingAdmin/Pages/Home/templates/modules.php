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

$CLICSHOPPING_Modules = Registry::get('Modules');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_CfgModule = Registry::get('CfgModulesAdmin');
$CLICSHOPPING_Db = Registry::get('Db');

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

$appModuleType = null;

switch ($module_type) {
  case 'dashboard':
    $appModuleType = 'AdminDashboard';
    break;
  case 'header_tags':
    $appModuleType = 'HeaderTags';
    break;
  case 'payment':
    $appModuleType = 'Payment';
    break;

  case 'shipping':
    $appModuleType = 'Shipping';
    break;

  case 'order_total':
    $appModuleType = 'OrderTotal';
    break;
}
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
            <button type="button" class="btn btn-link"><a href="https://www.clicshopping.org/forum/files/"
                                                          target="_blank"
                                                          rel="noreferrer"><?php echo $CLICSHOPPING_Modules->getDef('button_marketplace'); ?></a></button>
            <?php 
	      echo HTML::button($CLICSHOPPING_Modules->getDef('button_extension'), null, CLICSHOPPING::link(null, 'A&Tools\Upgrade&Upgrade'), 'primary');

            if (isset($_GET['action']) && $_GET['action'] == 'edit') {
              echo '<span class="cols-xs-3 float-end">';
              echo HTML::button($CLICSHOPPING_Modules->getDef('button_cancel'), null, $CLICSHOPPING_Modules->link('Modules&set=' . $set), 'warning') . '&nbsp;';
              echo HTML::form('modules', $CLICSHOPPING_Modules->link('Modules&set=' . $set . '&module=' . $_GET['module'] . '&action=save'));
              echo HTML::button($CLICSHOPPING_Modules->getDef('button_update'), null, null, 'success');
              echo '</span>';
            } elseif (isset($_GET['list'])) {
              echo '            <span>' . HTML::button($CLICSHOPPING_Modules->getDef('button_back'), null, $CLICSHOPPING_Modules->link('Modules&set=' . $set), 'primary') . '</span>';
            } else {
              echo '            <span>' . HTML::button($CLICSHOPPING_Modules->getDef('button_module_install'), null, $CLICSHOPPING_Modules->link('Modules&set=' . $set . '&list=new'), 'success') . '</span>';
            }
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
        if (substr($file, strrpos($file, '.')) === $file_extension) {
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
  ?>
  <table
    id="table"
    data-toggle="table"
    data-icons-prefix="bi"
    data-icons="icons"
    data-sort-name="sort_order"
    data-sort-order="asc"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true">

    <thead class="dataTableHeadingRow">
    <tr>
      <th data-field="modules"
          data-sortable="true"><?php echo $CLICSHOPPING_Modules->getDef('table_heading_modules'); ?></th>
      <th data-field="group" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Modules->getDef('table_heading_group'); ?></th>
      <th data-field="sort_order" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Modules->getDef('table_heading_sort_order'); ?></th>
      <th data-field="status" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_Modules->getDef('table_heading_status'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_Modules->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
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

        if (is_file($module_language_directory . '/' . $CLICSHOPPING_Language->get('directory') . '/modules/' . $module_type . '/' . pathinfo($file, PATHINFO_FILENAME) . '.txt')) {
          $CLICSHOPPING_Language->loadDefinitions($module_site . '/modules/' . $module_type . '/' . pathinfo($file, PATHINFO_FILENAME));
        } else {
          $CLICSHOPPING_Language->loadDefinitions($CLICSHOPPING_Template->getDirectoryPathShopDefaultTemplateHtml() . '/languages/' . $CLICSHOPPING_Language->get('directory') . '/modules/' . $module_type . '/' . pathinfo($file, PATHINFO_FILENAME));
        }

        include($module_directory . $file);

        $class = substr($file, 0, strrpos($file, '.'));

        if (class_exists($class)) {
          $module = new $class;
        }
      }

      if (isset($module)) {
        if ($module->check() > 0) {
          if ($module->sort_order > 0 && !isset($installed_modules[$module->sort_order])) {
            $installed_modules[$module->sort_order] = $file;
          } else {
            $installed_modules[] = $file;
          }
        }

        if ((!isset($_GET['module']) || (isset($_GET['module']) && ($_GET['module'] === $class))) && !isset($mInfo)) {
          $module_info = [
            'code' => $module->code,
            'title' => $module->title,
            'description' => $module->description,
            'group' => $module->group,
            'status' => $module->check(),
            'signature' => $module->signature ?? null,
            'api_version' => $module->api_version ?? null
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
        ?>
        <tr>
          <td><?php echo $module->title; ?></td>
          <td class="text-start"><?php echo $module->group; ?></td>
          <td class="text-end">
            <?php
            if (\in_array($module->code . $file_extension, $modules_installed) && is_numeric($module->sort_order)) {
              echo $module->sort_order;
            }
            ?>
          </td>
          <td class="text-center">
            <?php
            if ($module->enabled == 'True') {
              echo '<i class="bi-check text-success"></i>';
            } else {
              echo '<i class="bi bi-x text-danger"></i>';
            }
            ?>
          </td>
          <td class="text-end">
            <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
              <?php
              if ($module->check() > 0) {
                echo HTML::link($CLICSHOPPING_Modules->link('Edit&set=' . $set . '&module=' . $class), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_Modules->getDef('icon_edit') . '"></i></h4>') . '&nbsp;';
                echo HTML::link($CLICSHOPPING_Modules->link('Modules&Modules&Remove&set=' . $set . '&module=' . $class), '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_Modules->getDef('icon_remove') . '"></i></h4>');
              } else {
                echo HTML::link($CLICSHOPPING_Modules->link('Modules&Modules&Install&set=' . $set . '&module=' . $class), '<h4><i class="bi bi-bag-plus" title="' . $CLICSHOPPING_Modules->getDef('icon_install') . '"></i></h4>');
              }
              echo '&nbsp;';
              ?>
            </div>
          </td>
        </tr>
        <?php
      }
    }

    if (!isset($_GET['list'])) {
      ksort($installed_modules);

      $Qcheck = $CLICSHOPPING_Db->get('configuration', 'configuration_value', ['configuration_key' => $module_key]);

      if ($Qcheck->fetch() !== false) {
        if ($Qcheck->value('configuration_value') != implode(';', $installed_modules)) {
          Registry::get('Db')->save('configuration', [
            'configuration_value' => implode(';', $installed_modules),
            'last_modified' => 'now()'
          ],
            ['configuration_key' => $module_key]
          );
        }
      } else {
        $CLICSHOPPING_Db->save('configuration', [
            'configuration_title' => 'Installed Modules',
            'configuration_key' => $module_key,
            'configuration_value' => implode(';', $installed_modules),
            'configuration_description' => 'This is automatically updated. No need to edit.',
            'configuration_group_id' => 6,
            'sort_order' => 0,
            'date_added' => 'now()'
          ]
        );
      }

      if ($template_integration === true) {
        $Qcheck = $CLICSHOPPING_Db->get('configuration', 'configuration_value', ['configuration_key' => 'TEMPLATE_BLOCK_GROUPS']);

        if ($Qcheck->fetch() !== false) {
          $tbgroups_array = explode(';', $Qcheck->value('configuration_value'));

          if (!\in_array($module_type, $tbgroups_array)) {
            $tbgroups_array[] = $module_type;
            sort($tbgroups_array);

            $CLICSHOPPING_Db->save('configuration', [
              'configuration_value' => implode(';', $tbgroups_array),
              'last_modified' => 'now()'
            ],
              ['configuration_key' => 'TEMPLATE_BLOCK_GROUPS']
            );
          }
        } else {
          $CLICSHOPPING_Db->save('configuration', [
              'configuration_title' => 'Installed Template Block Groups',
              'configuration_key' => 'TEMPLATE_BLOCK_GROUPS',
              'configuration_value' => $module_type,
              'configuration_description' => 'This is automatically updated. No need to edit.',
              'configuration_group_id' => 6,
              'sort_order' => 0,
              'date_added' => 'now()'
            ]
          );

        }
      }
    }
    ?>
    </tbody>
  </table>
  <div class="alert alert-danger">
    <?php echo $CLICSHOPPING_Modules->getDef('text_info_module'); ?>
  </div>
</div>
<div class="py-3"></div>
