<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\ClicShoppingAdmin;

use ClicShopping\OM\CLICSHOPPING;
use function call_user_func;
/**
 * CallUserFuncModule class provides functionality to dynamically call functions or methods
 * within the ClicShoppingAdmin site framework. This allows for executing functions or methods
 * provided by their full name (including namespaced method calls) or dynamic loading of
 * configuration parameter files.
 */
class CallUserFuncModule
{
  /**
   * Executes a specified function or method and returns its result.
   *
   * @param string $function The name of the function or method to execute. For static class methods, use the format 'ClassName::methodName'.
   *                         For functions with parameters, use the format 'functionName(param1, param2)'.
   * @param mixed|null $default Optional parameter to pass as the default value to the function or method.
   * @param mixed|null $key Optional parameter to pass as the key to the function or method.
   *
   * @return mixed Returns the result of the executed function or method.
   */
  public static function execute(string $function, array|string|null $default = null, ?string $key = null)
  {
    if (str_contains($function, '::')) {
      $class_method = explode('::', $function);

      return call_user_func(array($class_method[0], $class_method[1]), $default, $key);
    } else {

      $function_name = $function;
      $function_parameter = '';

      if (str_contains($function, '(')) {
        $function_array = explode('(', $function, 2);

        $function_name = $function_array[0];
        $function_parameter = substr($function_array[1], 0, -1);
      }

      if (!function_exists($function_name)) {
        if (is_file(CLICSHOPPING::BASE_DIR . 'Sites/ClicShoppingAdmin/Assets/CfgParameters/' . $function_name . '.php')) {
          include_once(CLICSHOPPING::BASE_DIR . 'Sites/ClicShoppingAdmin/Assets/CfgParameters/' . $function_name . '.php');
        } else {
          include_once(CLICSHOPPING::BASE_DIR . 'Custom/Sites/ClicShoppingAdmin/Assets/CfgParameters/' . $function_name . '.php');
        }
      }

      if (!empty($function_parameter)) {
        return call_user_func($function_name, $function_parameter, $default, $key);
      } else {
        return call_user_func($function_name, $default, $key);
      }
    }
  }
}

