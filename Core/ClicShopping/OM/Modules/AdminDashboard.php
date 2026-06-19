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
 * Represents the AdminDashboard module extending the base functionality of ModulesAbstract.
 * Provides methods to retrieve module information and resolve module classes.
 */
class AdminDashboard extends ModulesAbstract
{
  /**
   * Retrieves information associated with the given application, key, and data.
   *
   * @param string $app The application namespace to be used for building the class.
   * @param string $key The key associated with the corresponding data.
   * @param string $data The data or component to be linked with the application.
   *
   * @return array An array containing the resolved class information if it matches the expected interface.
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
   * Retrieves the full class name for a specific module.
   *
   * @param string $module The input module string, typically formatted with namespace delimiters.
   * @return string|bool Returns the full class name as a string if the module is found, or false if it is not.
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
