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
 * Class OrderTotal
 *
 * Extends the \ClicShopping\OM\ModulesAbstract class to provide functionality
 * for retrieving information about modules and returning specific class instances
 * based on the provided module name.
 */
class OrderTotal extends ModulesAbstract
{
  /**
   * Retrieves information based on the specified parameters and validates the class against an interface.
   *
   * @param string $app The name of the application to retrieve information for.
   * @param string $key A specific key related to the application.
   * @param string $data The data to build the class name for validation.
   *
   * @return array An associative array containing the app and key as the key, and the validated class as the value. Returns an empty array if the class does not meet the required conditions.
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
   * Retrieves the full class name of a module based on its namespace structure and application info.
   *
   * @param string $module The module identifier in the format 'Vendor\App\Code'.
   * @return string|false The fully qualified class name if found, or false if the module does not exist.
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
