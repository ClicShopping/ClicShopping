<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\OM\Modules;

use ClicShopping\OM\Apps;
use ClicShopping\OM\Domains\ModulesAbstract;

/**
 * Represents the administrative menu system and provides functionalities
 * to retrieve information about modules and their corresponding class instances.
 */
class AdminMenu extends ModulesAbstract
{
  /**
   * Retrieves information about the specified app and key using the provided data.
   *
   * @param string $app The name of the application.
   * @param string $key The identifier or key related to the module.
   * @param string $data Additional data used to construct the class name.
   * @return array An associative array containing the app and key as the array key, and the corresponding class name as the value.
   */
  public function getInfo($app, $key, $data)
  {
    $result = [];

    $class = $this->ns . $app . '\\' . $data;

    if (is_subclass_of($class, 'ClicShopping\OM\Interfaces\\' . $this->code . 'Interface')) {
      $result[$app . '\\' . $key] = $class;
    }

    return $result;
  }

  /**
   * Retrieves the fully qualified class name for the specified module.
   *
   * @param string $module The module identifier in the format 'Vendor\App\Module'.
   * @return string|bool Returns the fully qualified class name if found, or false if the module does not exist.
   */
  public function getClass($module)
  {

    [$vendor, $app, $code] = explode('\\', $module, 3);

    $info = Apps::getInfo($vendor . '\\' . $app);

    if (isset($info['modules'][$this->code][$code])) {
      return $this->ns . $vendor . '\\' . $app . '\\' . $info['modules'][$this->code][$code];
    } else {
      return false;
    }
  }
}
