<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\ReturnOrders\Module\ClicShoppingAdmin\Config;

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
   * Initializes the class by setting application registry, retrieving the class name,
   * loading necessary definitions, and performing initial setup.
   *
   * @return void
   */
  final public function __construct()
  {
    $this->app = Registry::get('ReturnOrders');

    $this->code = (new \ReflectionClass($this))->getShortName();

    $this->app->loadDefinitions('module/' . $this->code . '/' . $this->code);

    $this->init();
  }

  /**
   * Installs the configuration parameters for the module by iterating through the defined parameters,
   * dynamically initializing their configuration classes, and saving their associated configuration values
   * to the application's registry.
   *
   * @return void
   */
  public function install()
  {
    $cut_length = \strlen('CLICSHOPPING_APP_RETURN_ORDERS_' . $this->code . '_');

    foreach ($this->getParameters() as $key) {
      $p = mb_strtolower(substr($key, $cut_length));

      $class = 'ClicShopping\Apps\Orders\ReturnOrders\Module\ClicShoppingAdmin\Config\\' . $this->code . '\Params\\' . $p;

      $cfg = new $class($this->code);

      $this->app->saveCfgParam($key, $cfg->default, $cfg->title ?? null, $cfg->description ?? null, $cfg->set_func ?? null);
    }
  }

  /**
   * Removes configuration settings related to the current module from the database.
   *
   * This method deletes all entries in the configuration table that match the module's specific
   * configuration key pattern. After executing the deletion query, it returns the number of rows
   * affected by the operation.
   *
   * @return int The number of rows deleted from the configuration table.
   */
  public function uninstall()
  {
    $Qdelete = $this->app->db->prepare('delete from :table_configuration
                                          where configuration_key
                                          like :configuration_key
                                          ');
    $Qdelete->bindValue(':configuration_key', 'CLICSHOPPING_APP_RETURN_ORDERS_' . $this->code . '_%');
    $Qdelete->execute();

    return $Qdelete->rowCount();
  }

  /**
   * Retrieves a list of configuration parameter constants for the current module.
   *
   * This method scans the specified directory for PHP files representing configuration parameter classes.
   * It validates whether each class is a subclass of ConfigParamAbstract and, if valid, constructs a constant name
   * for the parameter. If any file does not represent a valid subclass, an error is triggered.
   *
   * @return array Returns an array of constant names representing the configuration parameters for the module.
   */
  public function getParameters()
  {
    $result = [];

    $directory = CLICSHOPPING::BASE_DIR . 'Apps/Orders/ReturnOrders/Module/ClicShoppingAdmin/Config/' . $this->code . '/Params';

    if ($dir = new \DirectoryIterator($directory)) {
      foreach ($dir as $file) {
        if (!$file->isDot() && !$file->isDir() && ($file->getExtension() == 'php')) {
          $class = 'ClicShopping\Apps\Orders\ReturnOrders\Module\ClicShoppingAdmin\Config\\' . $this->code . '\\Params\\' . $file->getBasename('.php');

          if (is_subclass_of($class, 'ClicShopping\Apps\Orders\ReturnOrders\Module\ClicShoppingAdmin\Config\ConfigParamAbstract')) {
            $result[] = 'CLICSHOPPING_APP_RETURN_ORDERS_' . $this->code . '_' . mb_strtoupper($file->getBasename('.php'));
          } else {
            trigger_error('ClicShopping\Apps\Orders\ReturnOrders\Module\ClicShoppingAdmin\Config\\ConfigAbstract::getParameters(): ClicShopping\Apps\Orders\ReturnOrders\Module\ClicShoppingAdmin\Config\\' . $this->code . '\\Params\\' . $file->getBasename('.php') . ' is not a subclass of ClicShopping\Apps\Orders\ReturnOrders\Module\ClicShoppingAdmin\Config\ConfigParamAbstract and cannot be loaded.');
          }
        }
      }
    }

    return $result;
  }

  /**
   * Retrieves and processes input parameters for the module, dynamically generating
   * and sorting configuration fields based on predefined module parameters.
   *
   * @return array An array of processed and sorted configuration parameters.
   */
  public function getInputParameters()
  {
    $result = [];

    $cut = 'CLICSHOPPING_APP_RETURN_ORDERS_' . $this->code . '_';

    $cut_length = \strlen($cut);

    foreach ($this->getParameters() as $key) {
      $p = mb_strtolower(substr($key, $cut_length));

      $class = 'ClicShopping\Apps\Orders\ReturnOrders\Module\ClicShoppingAdmin\Config\\' . $this->code . '\Params\\' . $p;

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
