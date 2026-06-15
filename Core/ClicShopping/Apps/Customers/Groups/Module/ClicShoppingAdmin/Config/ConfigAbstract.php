<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups\Module\ClicShoppingAdmin\Config;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

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

  abstract protected function init();

  /**
   * Initializes the class by retrieving the application instance from the Registry,
   * setting the code property, loading language definitions, and performing initialization tasks.
   *
   * @return void
   */
  final public function __construct()
  {
    $this->app = Registry::get('Groups');

    $this->code = (new \ReflectionClass($this))->getShortName();

    $this->app->loadDefinitions('module/' . $this->code . '/' . $this->code);

    $this->init();
  }

  /**
   * Installs the configuration parameters for the module.
   * Processes each parameter associated with the current module, transforming and saving their default values, title, description, and set function as defined by their respective classes.
   *
   * @return void
   */
  public function install()
  {
    $cut_length = \strlen('CLICSHOPPING_APP_CUSTOMERS_GROUPS_' . $this->code . '_');

    foreach ($this->getParameters() as $key) {
      $p = mb_strtolower(substr($key, $cut_length));

      $class = 'ClicShopping\Apps\Customers\Groups\Module\ClicShoppingAdmin\Config\\' . $this->code . '\Params\\' . $p;

      $cfg = new $class($this->code);

      $this->app->saveCfgParam($key, $cfg->default, $cfg->title ?? null, $cfg->description ?? null, $cfg->set_func ?? null);
    }
  }

  /**
   * Uninstalls the module by removing specific configuration entries from the database.
   *
   * Deletes all rows in the configuration table where the key matches a specific pattern
   * associated with this module, using its unique code as part of the key.
   *
   * @return int Returns the number of rows deleted from the database.
   */
  public function uninstall()
  {
    $Qdelete = $this->app->db->prepare('delete from :table_configuration
                                          where configuration_key
                                          like :configuration_key
                                          ');
    $Qdelete->bindValue(':configuration_key', 'CLICSHOPPING_APP_CUSTOMERS_GROUPS_' . $this->code . '_%');
    $Qdelete->execute();

    return $Qdelete->rowCount();
  }

  /**
   * Retrieves a list of configuration parameters for the specified module.
   *
   * The method scans the module's "Params" directory for PHP files representing
   * configuration parameters. Each valid parameter is returned as a string
   * following a specific format, ensuring it is a subclass of `ConfigParamAbstract`.
   * If invalid parameter files are encountered, an error is triggered.
   *
   * @return array An array of configuration parameter identifiers in string format.
   */
  public function getParameters()
  {
    $result = [];

    $directory = CLICSHOPPING::BASE_DIR . 'Apps/Customers/Groups/Module/ClicShoppingAdmin/Config/' . $this->code . '/Params';

    if ($dir = new \DirectoryIterator($directory)) {
      foreach ($dir as $file) {
        if (!$file->isDot() && !$file->isDir() && ($file->getExtension() == 'php')) {
          $class = 'ClicShopping\Apps\Customers\Groups\Module\ClicShoppingAdmin\Config\\' . $this->code . '\\Params\\' . $file->getBasename('.php');

          if (is_subclass_of($class, 'ClicShopping\Apps\Customers\Groups\Module\ClicShoppingAdmin\Config\ConfigParamAbstract')) {
            $result[] = 'CLICSHOPPING_APP_CUSTOMERS_GROUPS_' . $this->code . '_' . mb_strtoupper($file->getBasename('.php'));
          } else {
            trigger_error('ClicShopping\Apps\Customers\Groups\Module\ClicShoppingAdmin\Config\\ConfigAbstract::getParameters(): ClicShopping\Apps\Customers\Groups\Module\ClicShoppingAdmin\Config\\' . $this->code . '\\Params\\' . $file->getBasename('.php') . ' is not a subclass of ClicShopping\Apps\Customers\Groups\Module\ClicShoppingAdmin\Config\ConfigParamAbstract and cannot be loaded.');
          }
        }
      }
    }

    return $result;
  }

  /**
   * Retrieves and processes input parameters for the application configuration.
   *
   * This method iterates through application-specific configuration parameters,
   * applies necessary transformations, initializes associated configuration objects,
   * and retrieves their settings. It maintains a sorted list of parameters for use
   * in the application setup or execution.
   *
   * @return array An array of processed input parameters, sorted and ready for use in the application.
   */
  public function getInputParameters()
  {
    $result = [];

    $cut = 'CLICSHOPPING_APP_CUSTOMERS_GROUPS_' . $this->code . '_';

    $cut_length = \strlen($cut);

    foreach ($this->getParameters() as $key) {
      $p = mb_strtolower(substr($key, $cut_length));

      $class = 'ClicShopping\Apps\Customers\Groups\Module\ClicShoppingAdmin\Config\\' . $this->code . '\Params\\' . $p;

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
