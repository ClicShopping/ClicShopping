<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\ClicShoppingAdmin;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use function is_object;
/**
 * Class CfgModulesAdmin
 *
 * This class is responsible for managing configuration modules in the ClicShopping Admin area.
 * It provides methods for retrieving module information, checking module existence, and counting active modules.
 */
class CfgModulesAdmin
{
  public array $_modules = [];

  /**
   * Constructor method.
   *
   * Initializes module configuration objects by dynamically loading classes from the specified module directory.
   * Each loaded module's metadata is stored in the `_modules` property for later use.
   *
   * @return void
   */
  public function __construct()
  {
    $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

    $file_extension = substr(CLICSHOPPING::getIndex(), strrpos(CLICSHOPPING::getIndex(), '.'));
    $directory = $CLICSHOPPING_Template->getModulesDirectory() . '/Module/CfgModules/';

    if ($dir = @dir($directory)) {
      while ($file = $dir->read()) {
        if (!is_dir($directory . $file)) {
          if (substr($file, strrpos($file, '.')) === $file_extension) {
            $class = substr($file, 0, strrpos($file, '.'));

            include($CLICSHOPPING_Template->getModulesDirectory() . '/Module/CfgModules/' . $class . '.php');

            $m = new $class();

            if (is_object($m)) {
              $this->_modules[] = [
                'code' => $m->code,
                'directory' => $m->directory,
                'language_directory' => $m->language_directory,
                'key' => $m->key,
                'title' => $m->title,
                'template_integration' => $m->template_integration,
                'site' => $m->site
              ];
            }
          }
        }
      }
    }
  }

  /**
   * Retrieves all modules.
   *
   * @return array An array containing all modules.
   */
  public function getAll()
  {
    return $this->_modules;
  }

  /**
   * Retrieves the value of a specific key from a module identified by its code.
   *
   * @param string $code The identifier code of the module to search for.
   * @param string $key The key within the module array whose value is to be retrieved.
   * @return mixed|null Returns the value of the specified key if the module and key exist, otherwise null.
   */
  public function get(string $code, string $key)
  {
    if (\is_array($this->_modules)) {
      foreach ($this->_modules as $m) {
        if ($m['code'] == $code) {
          return $m[$key];
        }
      }
    }
  }

  /**
   * Checks if a module with the specified code exists within the module list.
   *
   * @param string $code The code of the module to search for.
   * @return bool Returns true if the module with the specified code exists, false otherwise.
   */
  public function exists($code): bool
  {
    if (\is_array($this->_modules)) {
      foreach ($this->_modules as $m) {
        if ($m['code'] == $code) {
          return true;
        }
      }
    }

    return false;
  }

  /**
   * Counts the number of enabled modules from a semicolon-separated string of module names.
   *
   * @param string $modules A semicolon-separated string containing module names. Each module name is expected to include a file extension.
   * @return int The count of enabled modules.
   */
  public function countModules(string $modules = ''): int
  {
    $count = 0;

    if (empty($modules)) return $count;

    $modules_array = explode(';', $modules);

    for ($i = 0, $n = \count($modules_array); $i < $n; $i++) {
      $class = substr($modules_array[$i], 0, strrpos($modules_array[$i], '.'));

      if (isset($GLOBALS[$class]) && is_object($GLOBALS[$class])) {
        if ($GLOBALS[$class]->enabled) {
          $count++;
        }
      }
    }

    return $count;
  }
}