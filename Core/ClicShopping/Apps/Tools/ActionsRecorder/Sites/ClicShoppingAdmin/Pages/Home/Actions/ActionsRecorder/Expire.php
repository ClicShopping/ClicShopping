<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Tools\ActionsRecorder\Sites\ClicShoppingAdmin\Pages\Home\Actions\ActionsRecorder;

use ClicShopping\Apps\Tools\ActionsRecorder\Classes\ClicShoppingAdmin\ActionsRecorder as ActionsRecorderClass;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class Expire extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('ActionsRecorder');
  }

  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

    Registry::set('ActionsRecorderClass', new ActionsRecorderClass());
    $CLICSHOPPING_ActionsRecorderClass = Registry::get('ActionsRecorderClass');

    $file_extension = substr(CLICSHOPPING::getIndex(), strrpos(CLICSHOPPING::getIndex(), '.'));
    $directory_array = [];

    if ($dir = @dir(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/modules/action_recorder/')) {
      while ($file = $dir->read()) {
        if (!is_dir(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/modules/action_recorder/' . $file)) {
          if (substr($file, strrpos($file, '.')) == $file_extension) {
            $directory_array[] = $file;
          }
        }
      }
      sort($directory_array);
      $dir->close();
    }

    for ($i = 0, $n = \count($directory_array); $i < $n; $i++) {
      $file = $directory_array[$i];

      include($CLICSHOPPING_Template->getDirectoryPathModuleShop() . '/action_recorder/' . $file);

      $CLICSHOPPING_ActionsRecorderClass->getClass($file);
    }

    $modules_array = [];

    $Qmodules = $this->app->db->get('action_recorder', 'distinct module', null, 'module');

    while ($Qmodules->fetch()) {
      $modules_array[] = $Qmodules->value('module');
    }

    $expired_entries = 0;

    if (isset($_GET['module']) && \in_array($_GET['module'], $modules_array)) {
      $get_module_class = $CLICSHOPPING_ActionsRecorderClass->getClassModule($_GET['module']);

      if (\is_object($get_module_class)) {
        $expired_entries += $get_module_class->expireEntries();
      } else {
        $expired_entries = $this->app->db->delete('action_recorder', ['module' => $_GET['module']]);
      }
    } else {
      if (\is_array($modules_array)) {
        foreach ($modules_array as $module) {
          $get_module_class = $CLICSHOPPING_ActionsRecorderClass->getClassModule($module);
          if (isset($get_module_class) && \is_object($get_module_class)) {
            $expired_entries += $get_module_class->expireEntries();
          }
        }
      }
    }

    $CLICSHOPPING_MessageStack->add($this->app->getDef('success_expired_entries', ['expired_entries' => $expired_entries]), 'success');

    $this->app->redirect('ActionsRecorder');
  }
}