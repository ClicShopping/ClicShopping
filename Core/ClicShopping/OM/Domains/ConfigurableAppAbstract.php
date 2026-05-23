<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Domains;

use ClicShopping\OM\Domains\AppAbstract;
use ClicShopping\OM\Interfaces\ConfigurableAppInterface;
use ClicShopping\OM\Registry;

abstract class ConfigurableAppAbstract extends AppAbstract implements ConfigurableAppInterface
{
  protected $api_version = 1;
  protected string $identifier = '';
  /**
   * Retrieves a sorted list of configuration modules available in a specific directory.
   *
   * This method scans a predefined directory for subdirectories containing configuration modules.
   * Each valid configuration module must be a subclass of ConfigAbstract
   * and comply with a specific namespace structure. The modules are sorted based on their
   * sort order retrieved from their metadata or in the order they were processed.
   *
   * @return mixed The sorted list of configuration modules. If no valid modules are found, an empty array is returned.
   */
  public function getConfigModules(): mixed
  {
    static $result;

    if (!isset($result)) {
      $result = [];

      $directory = $this->getConfigDirectory();
      $name_space_config = $this->getConfigNamespace();
      $trigger_message = get_class($this) . '::getConfigModules(): ';

      if ($dir = new \DirectoryIterator($directory)) {
        foreach ($dir as $file) {
          if (!$file->isDot() && $file->isDir() && is_file($file->getPathname() . DIRECTORY_SEPARATOR . $file->getFilename() . '.php')) {
            $class = $name_space_config . '\\' . $file->getFilename() . '\\' . $file->getFilename();

            if (is_subclass_of($class, $name_space_config . '\ConfigAbstract')) {
              $sort_order = $this->getConfigModuleInfo($file->getFilename(), 'sort_order');
              if ($sort_order > 0) {
                $counter = $sort_order;
              } else {
                $counter = count($result);
              }

              while (true) {
                if (isset($result[$counter])) {
                  $counter++;
                  continue;
                }

                $result[$counter] = $file->getFilename();
                break;
              }
            } else {
              trigger_error($trigger_message . $class . ' is not a subclass of ' . $name_space_config . '\ConfigAbstract and cannot be loaded.');
            }
          }
        }
        ksort($result, SORT_NUMERIC);
      }
    }

    return $result;
  }

  /**
   * Retrieves configuration module information based on the provided module and information key.
   *
   * @param string $module The name of the module for which configuration information is requested.
   * @param string $info The specific information key within the module to retrieve.
   * @return mixed Returns the requested configuration information or null if not available.
   */
  public function getConfigModuleInfo(string $module, string $info): mixed
  {
    $registry_key = $this->getConfigRegistryKey($module);
    
    if (!Registry::exists($registry_key)) {
      $class = $this->getConfigNamespace() . '\\' . $module . '\\' . $module;
      Registry::set($registry_key, new $class);
    }

    return Registry::get($registry_key)->$info;
  }

  /**
   * Get the configuration directory path for this app
   *
   * @return string
   */
  protected function getConfigDirectory(): string
  {
    // Default implementation - can be overridden by child classes
    $reflection = new \ReflectionClass($this);
    $appPath = dirname($reflection->getFileName());
    return $appPath . '/Module/ClicShoppingAdmin/Config';
  }

  /**
   * Get the configuration namespace for this app
   *
   * @return string
   */
  protected function getConfigNamespace(): string
  {
    // Default implementation - can be overridden by child classes
    $reflection = new \ReflectionClass($this);
    $namespace = $reflection->getNamespaceName();
    return $namespace . '\Module\ClicShoppingAdmin\Config';
  }

  /**
   * Get the registry key for configuration modules
   *
   * @param string $module
   * @return string
   */
  protected function getConfigRegistryKey(string $module): string
  {
    // Default implementation - can be overridden by child classes
    $reflection = new \ReflectionClass($this);
    $className = $reflection->getShortName();
    return $className . 'AdminConfig' . $module;
  }

  /**
   * Retrieves the version of the API.
   *
   * @return string|int The API version.
   */
  public function getApiVersion(): string|int
  {
    return $this->api_version;
  }

  /**
   * Gets the identifier associated with the object.
   *
   * @return string The identifier associated with the object.
   */
  public function getIdentifier(): string
  {
    return $this->identifier;
  }
}