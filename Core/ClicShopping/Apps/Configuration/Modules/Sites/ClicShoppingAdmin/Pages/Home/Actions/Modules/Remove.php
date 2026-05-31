<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Modules\Sites\ClicShoppingAdmin\Pages\Home\Actions\Modules;

use ClicShopping\Apps\Configuration\Modules\Classes\ClicShoppingAdmin\ModulesAdmin;
use ClicShopping\OM\Apps;
use ClicShopping\OM\Cache;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class Remove extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Modules');
  }

  public function execute()
  {
    $CLICSHOPPING_CfgModule = Registry::get('CfgModulesAdmin');

    Registry::set('ModulesAdmin', new ModulesAdmin());
    $CLICSHOPPING_ModulesAdmin = Registry::get('ModulesAdmin');

    $modules = $CLICSHOPPING_CfgModule->getAll();

    $set = $_GET['set'] ?? '';

    if (empty($set) || !$CLICSHOPPING_CfgModule->exists($set)) {
      $set = $modules[0]['code'];
    }

    $module_type = $CLICSHOPPING_CfgModule->get($set, 'code');
    $module_directory = $CLICSHOPPING_CfgModule->get($set, 'directory');

    $module_key = $CLICSHOPPING_CfgModule->get($set, 'key');

    $appModuleType = $CLICSHOPPING_ModulesAdmin->getSwitchModules($module_type);

    if (str_contains($_GET['module'], '\\')) {
      $class = Apps::getModuleClass($_GET['module'], $appModuleType);

      if (class_exists($class)) {
        $file_extension = '';
        $module = new $class();
        $class = $_GET['module'];
      }
    } else {
      $file_extension = substr(CLICSHOPPING::getIndex(), strrpos(CLICSHOPPING::getIndex(), '.'));
      $class = basename($_GET['module']);

      if (is_file($module_directory . $class . $file_extension)) {
        include_once($module_directory . $class . $file_extension);
        $module = new $class;
      }
    }

    if (isset($module)) {

      $module->remove();

      $modules_installed = explode(';', \constant($module_key));

      if (\in_array($class . $file_extension, $modules_installed)) {
        unset($modules_installed[array_search($class . $file_extension, $modules_installed)]);
      }

      Registry::get('Db')->save('configuration', ['configuration_value' => implode(';', $modules_installed)],
        ['configuration_key' => $module_key]
      );

      Cache::clear('configuration');

      $this->app->redirect('Modules&set=' . $set);
    }
  }
}