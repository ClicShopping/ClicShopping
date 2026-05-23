<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

use \InvalidArgumentException;
/**
 * Class Session
 *
 * Responsible for managing the session handler and providing functionality
 * to load and initialize the session storage driver.
 */
class Session
{
  /**
   * Loads the session storage handler
   *
   * @param string $name The name of the session
   *
   */

  protected static string $driver;
  protected static string $default_driver = 'File';

  /**
   * Loads and initializes the session driver, setting the session name and lifetime.
   *
   * @param string|null $name The name of the session. If not specified, a default value 'clicshopid' will be used.
   * @return object The initialized session object.
   * @throws InvalidArgumentException If the specified session driver does not exist.
   */
  public static function load(?string $name = null)
  {
    if (!isset(static::$driver)) {
      if (defined('USE_MEMCACHED') && USE_MEMCACHED === 'True') {
        static::$driver = 'Memcached';
      } else {
        static::$driver = CLICSHOPPING::configExists('store_sessions') ? CLICSHOPPING::getConfig('store_sessions') : static::$default_driver;
      }
    }

    if (!class_exists(__NAMESPACE__ . '\\Session\\' . static::$driver)) {
      static::$driver = static::$default_driver;
      throw new InvalidArgumentException('ClicShopping\OM\Session::load(): Neither specified nor default driver exists.');
    }

    $class_name = __NAMESPACE__ . '\\Session\\' . static::$driver;
    $obj = new $class_name();

    if (!isset($name)) {
      $name = 'clicshopid';
    }

    $obj->setName($name);

    $obj->setLifeTime(ini_get('session.gc_maxlifetime'));

    return $obj;
  }
}
