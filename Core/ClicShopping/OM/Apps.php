<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

use DirectoryIterator;
use function call_user_func;

/**
 * Class Apps
 *
 * This class provides static methods to handle and retrieve application data, such as obtaining a list of all apps,
 * retrieving specific modules, checking the existence of apps, and managing routes.
 */
class Apps
{

  /**
   * Retrieves a list of all available applications by scanning the designated base directory.
   *
   * @return array Returns an array of applications, where each entry contains information about an application.
   */
  public static function getAll(): array
  {
    $result = [];

    $apps_directory = CLICSHOPPING::BASE_DIR . 'Apps';

    if ($vdir = new DirectoryIterator($apps_directory)) {
      foreach ($vdir as $vendor) {
        if (!$vendor->isDot() && $vendor->isDir()) {
          if ($adir = new DirectoryIterator($vendor->getPath() . DIRECTORY_SEPARATOR . $vendor->getFilename())) {
            foreach ($adir as $app) {
              if (!$app->isDot() && $app->isDir() && static::exists($vendor->getFilename() . '\\' . $app->getFilename())) {
                if (($json = static::getInfo($vendor->getFilename() . '\\' . $app->getFilename())) !== false) {
                  $result[] = $json;
                }
              }
            }
          }
        }
      }
    }

    return $result;
  }

  /**
   * Retrieves a list of modules of a specified type, optionally filtered by vendor, application, and additional criteria.
   *
   * @param string $type The type of module to retrieve (e.g., 'Payment', 'Shipping').
   * @param string|null $filter_vendor_app Optional parameter to filter modules by a specific vendor or a combination of vendor and application, using the format 'Vendor\App'.
   * @param array|null $filter Optional array of additional criteria to apply when filtering modules.
   * @return array Returns an array of modules matching the specified type and filters. If no matching modules are found, an empty array is returned.
   */
  public static function getModules(string $type, ?string $filter_vendor_app = null, ?array $filter = null): array
  {
    $result = [];

    if (!Registry::exists('ModuleType' . $type)) {
      $class = 'ClicShopping\OM\Modules\\' . $type;

      if (!class_exists($class)) {
        trigger_error('ClicShopping\OM\Apps::getModules(): ' . $type . ' module class not found in ClicShopping\OM\Modules\\');
        return $result;
      }

      Registry::set('ModuleType' . $type, new $class());
    }

    $CLICSHOPPING_Type = Registry::get('ModuleType' . $type);

    $filter_vendor = $filter_app = null;

    if (isset($filter_vendor_app)) {
      if (str_contains($filter_vendor_app, '\\')) {
        [$filter_vendor, $filter_app] = explode('\\', $filter_vendor_app, 2);
      } else {
        $filter_vendor = $filter_vendor_app;
      }
    }

    $vendor_directory = CLICSHOPPING::BASE_DIR . 'Apps';

    if (is_dir($vendor_directory)) {
      if ($vdir = new DirectoryIterator($vendor_directory)) {
        $tempResults = [];
        foreach ($vdir as $vendor) {
          if (!$vendor->isDot() && $vendor->isDir() && (!isset($filter_vendor) || ($vendor->getFilename() == $filter_vendor))) {
            if ($adir = new DirectoryIterator($vendor->getPath() . DIRECTORY_SEPARATOR . $vendor->getFilename())) {
              foreach ($adir as $app) {
                if (!$app->isDot() && $app->isDir() && (!isset($filter_app) || ($app->getFilename() == $filter_app)) && static::exists($vendor->getFilename() . '\\' . $app->getFilename()) && (($json = static::getInfo($vendor->getFilename() . '\\' . $app->getFilename())) !== false)) {
                  if (isset($json['modules'][$type])) {
                    $modules = $json['modules'][$type];

                    if (isset($filter)) {
                      $modules = $CLICSHOPPING_Type->filter($modules, $filter);
                    }

                    foreach ($modules as $key => $data) {
                      $tempResults[] = $CLICSHOPPING_Type->getInfo($vendor->getFilename() . '\\' . $app->getFilename(), $key, $data);
                    }

                    $result = array_merge(...$tempResults);
                  }
                }
              }
            }
          }
        }
      }
    }

    return $result;
  }

  /**
   * Checks if a given app exists and is a valid subclass of ClicShopping\OM\Domains\AppAbstract.
   *
   * @param string $app The fully qualified name of the app in the format "Vendor\App".
   * @return bool Returns true if the app exists and is a subclass of ClicShopping\OM\Domains\AppAbstract, otherwise false.
   */
  public static function exists(string $app): bool
  {
    if (str_contains($app, '\\')) {
      [$vendor, $app] = explode('\\', $app, 2);

      if (class_exists('ClicShopping\Apps\\' . $vendor . '\\' . $app . '\\' . $app)) {
        if (is_subclass_of('ClicShopping\Apps\\' . $vendor . '\\' . $app . '\\' . $app, 'ClicShopping\OM\Domains\AppAbstract')) {
          return true;
        } else {
          trigger_error('ClicShopping\OM\Apps::exists(): ' . $vendor . '\\' . $app . ' - App is not a subclass of ClicShopping\OM\Domains\AppAbstract and cannot be loaded.');
        }
      }
    } else {
      trigger_error('ClicShopping\OM\Apps::exists(): ' . $app . ' - Invalid format, must be: Vendor\App.');
    }

    return false;
  }

  /**
   * Retrieves the class of the specified module based on its type.
   *
   * @param string $module The name of the module.
   * @param string|null $type The type of the module. Can be null.
   * @return mixed Returns the class of the module if found, or false if the module class does not exist.
   */
  public static function getModuleClass(string $module, ?string $type)
  {
    if (!Registry::exists('ModuleType' . $type)) {
      $class = 'ClicShopping\OM\Modules\\' . $type;

      if (!class_exists($class)) {
        trigger_error('ClicShopping\OM\Apps::getModuleClass(): ' . $type . ' module class not found in ClicShopping\OM\Modules\\');

        return false;
      }

      Registry::set('ModuleType' . $type, new $class());
    }

    $CLICSHOPPING_Type = Registry::get('ModuleType' . $type);

    return $CLICSHOPPING_Type->getClass($module);
  }

  /**
   * Retrieves metadata information for a specific application.
   *
   * @param string $app The fully qualified name of the application in the format "Vendor\App".
   * @return array|false Returns an array containing the application's metadata if found and valid,
   *                     or false if the metadata cannot be retrieved or if the format is invalid.
   */
  public static function getInfo(string $app)
  {
    if (str_contains($app, '\\')) {
      [$vendor, $app] = explode('\\', $app, 2);

      $vendorDir = CLICSHOPPING::BASE_DIR . 'Apps/' . basename($vendor);
      $appDir = basename($app);
      $metafile = $vendorDir . DIRECTORY_SEPARATOR . $appDir . '/clicshopping.json';

      if (is_file($metafile) && (($json = json_decode(file_get_contents($metafile), true)) !== null)) {
        return $json;
      }

      // Fallback: case-insensitive app directory lookup
      if (is_dir($vendorDir)) {
        if ($dir = new DirectoryIterator($vendorDir)) {
          foreach ($dir as $entry) {
            if ($entry->isDot() || !$entry->isDir()) {
              continue;
            }
            if (strtolower($entry->getFilename()) === strtolower($appDir)) {
              $metafile = $vendorDir . DIRECTORY_SEPARATOR . $entry->getFilename() . '/clicshopping.json';
              if (is_file($metafile) && (($json = json_decode(file_get_contents($metafile), true)) !== null)) {
                return $json;
              }
            }
          }
        }
      }

      trigger_error('ClicShopping\OM\Apps::getInfo(): ' . $vendor . '\\' . $app . ' - Could not read App information in ' . $metafile . '.');
    } else {
      trigger_error('ClicShopping\OM\Apps::getInfo(): ' . $app . ' - Invalid format, must be: Vendor\App.');
    }

    return false;
  }

  /**
   * Processes a given route array and transforms it into an associative array of query parameters.
   *
   * @param array $route The route array, typically containing query parameters as key-value pairs.
   * @return array The processed associative array of query parameters extracted from the input route.
   */
  public static function getRouteValue(array $route): array
  {
    $query = $route; //$_GET

// replace parameter(s)
    $query['?'] = '&';

// rebuild url
    $query_result = http_build_query($query);
    $query_result = str_replace('%3F', '&', $query_result);
    $split_complete = [];

    $split_parameters = explode('&', $query_result);

    foreach ($split_parameters as $value) {
      $final_split = explode('=', $value);

      if (!isset($final_split[1])) {
        $final_split[1] = null;
      }

      $split_complete[$final_split[0]] = $final_split[1];
    }

    $result = $split_complete;

    return $result;
  }

  /**
   * Resolves and retrieves the final route destination based on the input route and optional filtering criteria.
   *
   * @param array|string|null $route The initial route or list of routes, can be null to use global request parameters.
   * @param string|null $filter_vendor_app Optional filter to narrow down the specific vendor and app (using "vendor\app" format).
   * @return array The resolved route destination after processing and applying filters.
   */
  public static function getRouteDestination(mixed $route = null, string|null $filter_vendor_app = null)
  {
    if (empty($route)) {

      $route = array_keys(static::getRouteValue($_GET));
    }

    $result = $routes = [];

    if (empty($route)) {
      return $result;
    }

    $filter_vendor = $filter_app = null;

    if (isset($filter_vendor_app)) {
      if (str_contains($filter_vendor_app, '\\')) {
        [$filter_vendor, $filter_app] = explode('\\', $filter_vendor_app, 2);
      } else {
        $filter_vendor = $filter_vendor_app;
      }
    }

    $vendor_directory = CLICSHOPPING::BASE_DIR . 'Apps';

    if (is_dir($vendor_directory)) {
      if ($vdir = new DirectoryIterator($vendor_directory)) {
        foreach ($vdir as $vendor) {
          if (!$vendor->isDot() && $vendor->isDir() && (!isset($filter_vendor) || ($vendor->getFilename() == $filter_vendor))) {
            if ($adir = new DirectoryIterator($vendor->getPath() . DIRECTORY_SEPARATOR . $vendor->getFilename())) {

              foreach ($adir as $app) {
                if (!$app->isDot() && $app->isDir() && (!isset($filter_app) || ($app->getFilename() == $filter_app)) && static::exists($vendor->getFilename() . '\\' . $app->getFilename()) && (($json = static::getInfo($vendor->getFilename() . '\\' . $app->getFilename())) !== false)) {
                  if (isset($json['routes'][CLICSHOPPING::getSite()])) {
                    $routes[$json['vendor'] . '\\' . $json['app']] = $json['routes'][CLICSHOPPING::getSite()];
                  }
                }
              }
            }
          }
        }
      }
    }

    return call_user_func(['ClicShopping\Sites\\' . CLICSHOPPING::getSite() . '\\' . CLICSHOPPING::getSite(),
      'resolveRoute'
    ],
      $route, $routes
    );
  }
}
