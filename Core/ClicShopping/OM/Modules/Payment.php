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
 * Represents a payment module extending the abstract functionality provided in the ClicShopping\OM\ModulesAbstract class.
 * This class provides methods for retrieving information and class definitions for specific payment modules.
 */
class Payment extends \ClicShopping\OM\Domains\ModulesAbstract
{
  /**
   * Retrieves and constructs module information based on the given application, key, and data.
   *
   * @param string $app The name of the application.
   * @param string $key A specific key representing a part of the module identifier.
   * @param string $data The class or module data used for instantiation or verification.
   *
   * @return array An associative array containing the constructed module information
   *               if the class is a valid subclass of the specified interface; otherwise, an empty array.
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
   * Retrieves the full class name of a module based on the provided module identifier.
   *
   * @param string $module The module identifier in the format 'Vendor\App\Code'.
   * @return string|false The fully qualified class name if found, or false if not found.
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
