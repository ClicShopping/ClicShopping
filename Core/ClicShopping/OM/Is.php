<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

use Exception;
use function call_user_func_array;

/**
 * The Is class provides a mechanism to dynamically call static methods that
 * resolve to specific classes implementing the `ClicShopping\OM\IsInterface`.
 * It validates whether the targeted class exists and conforms to the interface
 * before invoking an `execute` method on the resolved class with the provided arguments.
 */
class Is
{
  /**
   * Dynamically handles static method calls to classes implementing a specific interface.
   *
   * @param string $name The name of the method being called, corresponding to a class name within the namespace.
   * @param array $arguments An array of arguments to be passed to the method being executed.
   * @return bool Returns the result of the class method execution. Returns false if the class does not exist,
   *              does not implement the required interface, or if the method is not callable.
   */
  public static function __callStatic(string $name, array $arguments): bool
  {
    $class = __NAMESPACE__ . '\\Is\\' . $name;

    try {
      if (!class_exists($class)) {
        throw new Exception('ClicShopping\Is module class does not exist: ' . $class);
      }

      if (!is_subclass_of($class, 'ClicShopping\\OM\\Interfaces\\IsInterface')) {
        throw new Exception('ClicShopping\Is module class does not implement ClicShopping\OM\IsInterface: ' . $class);
      }

      $callable = [
        $class,
        'execute'
      ];

      if (is_callable($callable)) {
        return call_user_func_array($callable, $arguments);
      }
    } catch (Exception $e) {
      trigger_error($e->getMessage());
    }

    return false;
  }
}