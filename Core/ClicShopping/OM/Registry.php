<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

use function array_slice;

/**
 * Class Registry
 * Handles the management of registered classes and their instances in a central registry system.
 */
class Registry
{
  protected static array $aliases = [];
  protected static array $data = [];

  /**
   * Retrieves the value associated with the specified key from the registry.
   *
   * @param string $key The key for which the value should be retrieved.
   * @return mixed The value associated with the provided key or null if the key is not registered.
   */
  public static function get(string $key): mixed
  {
    if (static::exists($key)) {
      $value = static::$data[$key];
    } else {
      $registry_class = null;

      if (array_key_exists($key, static::$aliases)) {
        $registry_class = 'ClicShopping\\OM\\' . static::$aliases[$key];
      } else {
        $bt = debug_backtrace(0, 2);
        $class = $bt[1]['class'];
        $ns_array = explode('\\', $class);

        if (\count($ns_array) > 5) {
          if (implode('\\', array_slice($ns_array, 0, 4)) === 'ClicShopping\\Custom\\Sites') {
            $registry_class = implode('\\', array_slice($ns_array, 0, 5)) . '\\Registry\\' . $key;
          } elseif (implode('\\', array_slice($ns_array, 0, 4)) === 'ClicShopping\\Sites') {
            $registry_class = implode('\\', array_slice($ns_array, 0, 5)) . '\\Registry\\' . $key;
          }
        }
      }

      if (!isset($registry_class)) {
        $site = CLICSHOPPING::getSite();

        if (isset($site)) {
          if (class_exists('ClicShopping\\Custom\\Sites\\' . $site . '\\' . $key)) {
            $registry_class = 'ClicShopping\\Custom\\Sites\\' . $site . '\\' . $key;
          } else {
            $registry_class = 'ClicShopping\\Sites\\' . $site . '\\' . $key;
          }
        }
      }

      if (isset($registry_class)) {
        while (!isset($value)) {
          if (is_a($registry_class, '\\ClicShopping\\OM\\RegistryAbstract', true)) {
            $RegistryObject = new $registry_class();

            if ($RegistryObject->hasAlias()) {
              $registry_class = 'ClicShopping\\OM\\' . $RegistryObject->getAlias();
              continue;
            } else {
              $value = static::$data[$key] = $RegistryObject->getValue();
            }
          } else {
            break;
          }
        }
      }
    }

    if (!isset($value)) {
      trigger_error('ClicShopping\Registry::get(): "' . $key . '" is not registered');
    }

    return $value ?? null;
  }

  /**
   * Registers a value in the registry under the given key.
   *
   * @param string $key The unique identifier for the value to be stored.
   * @param mixed $value The value to be stored in the registry.
   * @param bool $force Determines whether to forcibly overwrite an existing value for the given key. Default is false.
   * @return bool Returns true if the value was successfully set, or false if the key already exists and $force is not true.
   */
  public static function set(string $key, $value, bool $force = false): bool
  {
    if (static::exists($key) && ($force !== true)) {
      trigger_error('ClicShopping\Registry::set(): "' . $key . '" is already registered and is not forced to be replaced');
      return false;
    }

    static::$data[$key] = $value;

    return true;
  }

  /**
   * Checks if the specified key exists in the data array.
   *
   * @param string $key The key to check for existence.
   * @return bool Returns true if the key exists, false otherwise.
   */
  public static function exists(string $key): bool
  {
    return array_key_exists($key, static::$data);
  }

  /**
   * Removes an entry identified by the provided key from the data array.
   *
   * @param string $key The key of the entry to be removed.
   * @return void
   */
  public static function remove(string $key): void
  {
    unset(static::$data[$key]);
  }

  /**
   * Adds an alias for a class to the registry.
   *
   * @param string $key The alias name to be registered.
   * @param string $class The fully qualified class name to be associated with the alias.
   * @return bool Returns true if the alias was successfully registered, false if the alias already exists.
   */
  public static function addAlias(string $key, string $class): bool
  {
    if (static::aliasExists($key)) {
      trigger_error('ClicShopping\Registry::addAlias(): "' . $key . '" is already registered to "' . static::$aliases[$key] . '" and cannot be replaced by "' . $class . '"');
      return false;
    }
    static::$aliases[$key] = $class;

    return true;
  }

  /**
   * Adds multiple aliases to the internal alias mapping.
   *
   * @param array $keys An associative array where the keys are the alias names and the values are the corresponding class names.
   * @return void
   */
  public static function addAliases(array $keys): void
  {
    foreach ($keys as $key => $class) {
      static::addAlias($key, $class);
    }
  }

  /**
   * Checks if the given alias key exists in the aliases array.
   *
   * @param string $key The key of the alias to check.
   * @return bool Returns true if the alias exists, false otherwise.
   */
  public static function aliasExists(string $key): bool
  {
    return array_key_exists($key, static::$aliases);
  }

  /**
   * Removes the specified alias from the list of aliases.
   *
   * @param string $key The key of the alias to be removed.
   * @return void
   */
  public static function removeAlias(string $key): void
  {
    unset(static::$aliases[$key]);
  }
}
