<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Payment\COD\Module\ClicShoppingAdmin\Config;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
/**
 * Abstract class ConfigAbstract
 *
 * This abstract class provides a base structure for configuration management
 * related to the Money Order payment module within the ClicShopping Administrator.
 * It includes methods for the installation and uninstallation of configuration keys,
 * parameter retrieval, and input parameter handling. It also enforces the initialization
 * of properties and behavior through its abstract component.
 *
 * Properties:
 * - $app: Holds the instance of the MoneyOrder application.
 * - $code: Stores the shorthand code for the configuration.
 * - $title: Title of the configuration (unspecified type).
 * - $short_title: Brief title of the configuration.
 * - $introduction: Introduction or description for the configuration.
 * - $req_notes: Array containing required notes for the configuration.
 * - $is_installed: Indicates if the module is currently installed.
 * - $is_uninstallable: Determines if the module can be uninstalled.
 * - $is_migratable: Specifies if the configuration can be migrated.
 * - $sort_order: The sorting order for the configuration (nullable integer).
 * - $group: Represents the group categorization (unspecified type).
 *
 * Methods:
 * - __construct(): Initializes the configuration object by setting the application instance and invoking the abstract init method.
 * - install(): Installs the configuration by registering all necessary parameters to the database.
 * - uninstall(): Removes the configuration from the database and returns the number of affected rows.
 * - getParameters(): Retrieves a list of configuration parameter keys associated with the module.
 * - getInputParameters(): Returns a sorted array of configuration input parameters, ensuring that default values and defined structures are properly prepared and saved.
 *
 * This class enforces the implementation of the abstract init() method in any subclass,
 * ensuring that module-specific logic is appropriately handled.
 *
 * Errors:
 * - Triggers an error if a parameter file is not a subclass of ConfigParamAbstract during parameter retrieval.
 */
abstract class ConfigAbstract
{
  public mixed $app;

  public string $code;
  public $title;
  public string $short_title;
  public string $introduction;
  public array $req_notes = [];
  public bool $is_installed = false;
  public bool $is_uninstallable = false;
  public bool $is_migratable = false;
  public int|null $sort_order = 0;
  public $group;

  abstract protected function init();

  /**
   * Initializes the class instance by setting up the application context,
   * deriving the short name of the class, and performing any required initialization steps.
   *
   * @return void
   */
  final public function __construct()
  {
    $this->app = Registry::get('COD');

    $this->code = (new \ReflectionClass($this))->getShortName();

    $this->init();
  }

  /**
   * Installs the module by initializing configuration parameters and saving them into the application configuration.
   *
   * @return void
   */
  public function install()
  {
    $cut_length = \strlen('CLICSHOPPING_APP_COD_' . $this->code . '_');

    foreach ($this->getParameters() as $key) {
      $p = mb_strtolower(substr($key, $cut_length));

      $class = 'ClicShopping\Apps\Payment\COD\Module\ClicShoppingAdmin\Config\\' . $this->code . '\Params\\' . $p;

      $cfg = new $class($this->code);

      $this->app->saveCfgParam($key, $cfg->default, $cfg->title ?? null, $cfg->description ?? null, $cfg->set_func ?? null);
    }
  }

  /**
   * Removes configuration settings associated with the module from the database.
   *
   * This method executes a SQL DELETE statement to remove all entries in the
   * configuration table where the configuration key matches the specific pattern
   * associated with the current module. The number of rows affected by the deletion
   * is returned.
   *
   * @return int The number of rows deleted from the configuration table.
   */
  public function uninstall()
  {
    $Qdelete = $this->app->db->prepare('delete from :table_configuration
                                          where configuration_key
                                          like :configuration_key
                                          ');
    $Qdelete->bindValue(':configuration_key', 'CLICSHOPPING_APP_COD_' . $this->code . '_%');
    $Qdelete->execute();

    return $Qdelete->rowCount();
  }

  /**
   * Retrieves configuration parameters for the specified module code.
   *
   * This method scans the directory containing parameter classes for the module
   * and checks if each file within that directory is a valid parameter class
   * extending ConfigParamAbstract. The method then builds a list of parameter
   * constant names based on the valid files found.
   *
   * @return array Returns an array of parameter constant names for the module.
   */
  public function getParameters()
  {
    $result = [];

    $directory = CLICSHOPPING::BASE_DIR . 'Apps/Payment/COD/Module/ClicShoppingAdmin/Config/' . $this->code . '/Params';

    if ($dir = new \DirectoryIterator($directory)) {
      foreach ($dir as $file) {
        if (!$file->isDot() && !$file->isDir() && ($file->getExtension() == 'php')) {
          $class = 'ClicShopping\Apps\Payment\COD\Module\ClicShoppingAdmin\Config\\' . $this->code . '\\Params\\' . $file->getBasename('.php');

          if (is_subclass_of($class, 'ClicShopping\Apps\Payment\COD\Module\ClicShoppingAdmin\Config\ConfigParamAbstract')) {
            $result[] = 'CLICSHOPPING_APP_COD_' . $this->code . '_' . mb_strtoupper($file->getBasename('.php'));
          } else {
            trigger_error('ClicShopping\Apps\Payment\COD\Module\ClicShoppingAdmin\Config\\ConfigAbstract::getParameters(): ClicShopping\Apps\Payment\COD\Module\ClicShoppingAdmin\Config\\' . $this->code . '\\Params\\' . $file->getBasename('.php') . ' is not a subclass of ClicShopping\Apps\Payment\COD\Module\ClicShoppingAdmin\Config\ConfigParamAbstract and cannot be loaded.');
          }
        }
      }
    }

    return $result;
  }

  /**
   * Retrieves and processes configuration parameters for the module.
   *
   * This method scans through parameters, initializes their configuration if required,
   * and organizes them based on their sort order or default configuration settings.
   *
   * @return array Returns an array of processed input parameters, each formatted
   *               according to their configuration settings.
   */
  public function getInputParameters()
  {
    $result = [];

    $cut = 'CLICSHOPPING_APP_COD_' . $this->code . '_';

    $cut_length = \strlen($cut);

    foreach ($this->getParameters() as $key) {
      $p = mb_strtolower(substr($key, $cut_length));

      $class = 'ClicShopping\Apps\Payment\COD\Module\ClicShoppingAdmin\Config\\' . $this->code . '\Params\\' . $p;

      $cfg = new $class($this->code);

      if (!\defined($key)) {
        $this->app->saveCfgParam($key, $cfg->default, $cfg->title ?? null, $cfg->description ?? null, $cfg->set_func ?? null);
      }

      if ($cfg->app_configured !== false) {
        if (is_numeric($cfg->sort_order)) {
          $counter = (int)$cfg->sort_order;
        } else {
          $counter = \count($result);
        }

        while (true) {
          if (isset($result[$counter])) {
            $counter++;

            continue;
          }

          $set_field = $cfg->getSetField();

          if (!empty($set_field)) {
            $result[$counter] = $set_field;
          }

          break;
        }
      }
    }

    ksort($result, SORT_NUMERIC);

    return $result;
  }
}
