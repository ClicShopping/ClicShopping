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
 * Handles operations related to header tag modules by retrieving module information
 * and constructing proper class names for a given application context.
 */
class HeaderTags extends \ClicShopping\OM\Domains\ModulesAbstract
{
  /**
   * Retrieves information about a specific app and its associated data.
   *
   * @param string $app The name of the application.
   * @param string $key A unique key associated with the app.
   * @param string $data The specific data to be used for fetching information.
   * @return array An associative array with the app and key as the index and the class as the value, or an empty array if the class does not meet conditions.
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
   * Retrieves the full class name for a given module.
   *
   * @param string $module The module name in the format 'Vendor\App\Code'.
   * @return string|bool The fully qualified class name if found, or false if the module does not exist.
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
