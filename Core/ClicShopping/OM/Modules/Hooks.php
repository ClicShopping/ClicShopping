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
 * Class Hooks
 *
 * Provides methods to handle module information retrieval, class resolution, and filtering of modules based on specific criteria.
 */
class Hooks extends \ClicShopping\OM\Domains\ModulesAbstract
{
  /**
   * Retrieves information about modules that belong to a specific application and key.
   *
   * @param string $app The application name.
   * @param string $key The key identifying a specific group of modules.
   * @param array $data An associative array where the key is the module code and the value is the class name.
   * @return array An associative array containing module identifiers as keys and their corresponding class names as values.
   */
  public function getInfo($app, $key, $data)
  {
    $result = [];

    foreach ($data as $code => $class) {
      if (!is_string($class)) {
        continue;
      }

      $class = $this->ns . $app . '\\' . $class;

      if (is_subclass_of($class, 'ClicShopping\OM\Modules\\' . $this->code . 'Interface')) {
        $result[$app . '\\' . $key . '\\' . $code] = $class;
      }
    }

    return $result;
  }

  /**
   * Retrieves the fully qualified class name of a module or returns false if the module cannot be resolved.
   *
   * @param string $module The module identifier in the format 'vendor\app\group\code'.
   *
   * @return string|false Returns the full class name as a string if found, or false if the module cannot be resolved.
   */
  public function getClass($module)
  {
    if (!str_contains($module, '/')) {
      return $module;
    }

    [$vendor, $app, $group, $code] = explode('\\', $module, 4);

    $info = Apps::getInfo($vendor . '\\' . $app);

    if (isset($info['modules'][$this->code][$group][$code])) {
      return $this->ns . $vendor . '\\' . $app . '\\' . $info['modules'][$this->code][$group][$code];
    } else {
      return false;
    }
  }

  /**
   * Filters the given modules array based on the specified filter criteria.
   *
   * @param array $modules An associative array of modules, where keys are module identifiers and values are module data.
   * @param array $filter An associative array containing 'site', 'group', and 'hook' keys to define the filter conditions.
   *                       - 'site': The site name to match.
   *                       - 'group': The group name to match.
   *                       - 'hook': The specific hook to check within the module data.
   *
   * @return array An array of modules that match the filter criteria, keyed by their identifiers.
   */
  public function filter($modules, $filter)
  {
    $result = [];

    foreach ($modules as $key => $data) {
      if (($key === $filter['site'] . '/' . $filter['group']) && isset($data[$filter['hook']])) {
        $result[$key] = [
          $filter['hook'] => $data[$filter['hook']]
        ];
      }
    }

    return $result;
  }
}
