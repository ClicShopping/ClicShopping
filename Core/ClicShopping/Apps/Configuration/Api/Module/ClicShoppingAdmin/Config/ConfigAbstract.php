<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Api\Module\ClicShoppingAdmin\Config;

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
   * Initializes the class instance by retrieving the application object,
   * obtaining the class short name, and calling the initialization method.
   *
   * @return void
   */
  final public function __construct()
  {
    $this->app = Registry::get('Api');

    $this->code = (new \ReflectionClass($this))->getShortName();

    $this->init();
  }

  /**
   * Installs configuration parameters specific to the module by initializing
   * and saving them using their respective default values, titles, descriptions,
   * and configuration functions.
   *
   * @return void
   */
  public function install()
  {
    $cut_length = \strlen('CLICSHOPPING_APP_API_' . $this->code . '_');

    foreach ($this->getParameters() as $key) {
      $p = mb_strtolower(substr($key, $cut_length));

      $class = 'ClicShopping\Apps\Configuration\Api\Module\ClicShoppingAdmin\Config\\' . $this->code . '\Params\\' . $p;

      $cfg = new $class($this->code);

      $this->app->saveCfgParam($key, $cfg->default, $cfg->title ?? null, $cfg->description ?? null, $cfg->set_func ?? null);
    }
  }

  /**
   *
   */
  public function uninstall()
  {
    $Qdelete = $this->app->db->prepare('delete from :table_configuration
                                          where configuration_key
                                          like :configuration_key
                                          ');
    $Qdelete->bindValue(':configuration_key', 'CLICSHOPPING_APP_API_' . $this->code . '_%');
    $Qdelete->execute();

    return $Qdelete->rowCount();
  }

  /**
   * Retrieves a list of configuration parameters for the current module.
   *
   * This method scans the specified directory for parameter files, validates that
   * they are subclasses of ConfigParamAbstract, and constructs an array of
   * parameter identifiers.
   *
   * @return array An array of parameter identifiers for the current module.
   */
  public function getParameters()
  {
    $result = [];

    $directory = CLICSHOPPING::BASE_DIR . 'Apps/Configuration/Api/Module/ClicShoppingAdmin/Config/' . $this->code . '/Params';

    if ($dir = new \DirectoryIterator($directory)) {
      foreach ($dir as $file) {
        if (!$file->isDot() && !$file->isDir() && ($file->getExtension() == 'php')) {
          $class = 'ClicShopping\Apps\Configuration\Api\Module\ClicShoppingAdmin\Config\\' . $this->code . '\\Params\\' . $file->getBasename('.php');

          if (is_subclass_of($class, 'ClicShopping\Apps\Configuration\Api\Module\ClicShoppingAdmin\Config\ConfigParamAbstract')) {
            $result[] = 'CLICSHOPPING_APP_API_' . $this->code . '_' . mb_strtoupper($file->getBasename('.php'));
          } else {
            trigger_error('ClicShopping\Apps\Configuration\Api\Module\ClicShoppingAdmin\Config\\ConfigAbstract::getParameters(): ClicShopping\Apps\Configuration\Api\Module\ClicShoppingAdmin\Config\\' . $this->code . '\\Params\\' . $file->getBasename('.php') . ' is not a subclass of ClicShopping\Apps\Configuration\Api\Module\ClicShoppingAdmin\Config\ConfigParamAbstract and cannot be loaded.');
          }
        }
      }
    }

    return $result;
  }

  /**
   * Retrieves and processes input parameters based on configuration settings.
   *
   * This method constructs and returns an array of configuration parameters for
   * the current API module. It dynamically loads and initializes configuration
   * parameter classes, ensures their default settings are saved if not already defined,
   * and organizes the parameters according to their sort order.
   *
   * @return array The sorted array of processed input parameters.
   */
  public function getInputParameters()
  {
    $result = [];

    $cut = 'CLICSHOPPING_APP_API_' . $this->code . '_';

    $cut_length = \strlen($cut);

    foreach ($this->getParameters() as $key) {
      $p = mb_strtolower(substr($key, $cut_length));

      $class = 'ClicShopping\Apps\Configuration\Api\Module\ClicShoppingAdmin\Config\\' . $this->code . '\Params\\' . $p;

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
