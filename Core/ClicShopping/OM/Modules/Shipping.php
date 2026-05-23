<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\OM\Modules;

use ClicShopping\OM\Apps;

/**
 * Class Shipping
 *
 * This class represents the Shipping module functionality and handles the retrieval
 * of information and class definitions for available shipping modules.
 */
class Shipping extends \ClicShopping\OM\Domains\ModulesAbstract
{
  /**
   * Retrieves information related to a specific application, key, and data combination.
   *
   * @param string $app The application namespace.
   * @param string $key The identifier key for the data module.
   * @param string $data The specific data module to process.
   * @return array An associative array containing the application key and corresponding class if valid, otherwise an empty array.
   */
  public function getInfo($app, $key, $data)
  {
    $result = [];

    $class = $this->ns . $app . '\\' . $data;

    if (is_subclass_of($class, 'ClicShopping\OM\Modules\\' . $this->code . 'Interface')) {
      $result[$app . '\\' . $key] = $class;
    }

    return $result;
  }

  /**
   * Retrieves the class name for a specified module.
   *
   * @param string $module The module string, typically formatted as "Vendor\App\Code".
   * @return string|false The fully qualified class name if found, otherwise false.
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
