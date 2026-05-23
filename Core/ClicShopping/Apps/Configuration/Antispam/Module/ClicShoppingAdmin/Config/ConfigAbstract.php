<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Antispam\Module\ClicShoppingAdmin\Config;

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
   * Initializes the object by setting up the application instance, determining the class code,
   * loading necessary definitions, and performing initialization tasks.
   *
   * @return void
   */
  final public function __construct()
  {
    $this->app = Registry::get('Antispam');

    $this->code = (new \ReflectionClass($this))->getShortName();
    $this->app->loadDefinitions('Module/ClicShoppingAdmin/Config/' . $this->code . '/' . $this->code);
    $this->init();
  }

  /**
   * Installs configuration parameters for the module. It processes each parameter,
   * calculates necessary configuration object class names, and saves the
   * respective configuration settings into the application.
   *
   * @return void
   */
  public function install()
  {
    $cut_length = \strlen('CLICSHOPPING_APP_ANTISPAM_' . $this->code . '_');

    foreach ($this->getParameters() as $key) {
      $p = mb_strtolower(substr($key, $cut_length));

      $class = 'ClicShopping\Apps\Configuration\Antispam\Module\ClicShoppingAdmin\Config\\' . $this->code . '\Params\\' . $p;

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
    $Qdelete->bindValue(':configuration_key', 'CLICSHOPPING_APP_ANTISPAM_' . $this->code . '_%');
    $Qdelete->execute();

    return $Qdelete->rowCount();
  }

  /**
   * Retrieves a list of configuration parameter identifiers defined in a specific directory.
   *
   * @return array Returns an array of configuration parameter identifiers. Each identifier is determined
   *               based on PHP files located in the specified directory and their adherence to the expected
   *               class hierarchy.
   */
  public function getParameters()
  {
    $result = [];

    $directory = CLICSHOPPING::BASE_DIR . 'Apps/Configuration/Antispam/Module/ClicShoppingAdmin/Config/' . $this->code . '/Params';

    if ($dir = new \DirectoryIterator($directory)) {
      foreach ($dir as $file) {
        if (!$file->isDot() && !$file->isDir() && ($file->getExtension() == 'php')) {
          $class = 'ClicShopping\Apps\Configuration\Antispam\Module\ClicShoppingAdmin\Config\\' . $this->code . '\\Params\\' . $file->getBasename('.php');

          if (is_subclass_of($class, 'ClicShopping\Apps\Configuration\Antispam\Module\ClicShoppingAdmin\Config\ConfigParamAbstract')) {
            if ($this->code == 'GE') {
              $result[] = 'CLICSHOPPING_APP_ANTISPAM_' . mb_strtoupper($file->getBasename('.php'));
            } else {
              $result[] = 'CLICSHOPPING_APP_ANTISPAM_' . $this->code . '_' . mb_strtoupper($file->getBasename('.php'));
            }
          } else {
            trigger_error('ClicShopping\Apps\Configuration\Antispam\Module\ClicShoppingAdmin\Config\\ConfigAbstract::getParameters(): ClicShopping\Apps\Configuration\Antispam\Module\ClicShoppingAdmin\Config\\' . $this->code . '\\Params\\' . $file->getBasename('.php') . ' is not a subclass of ClicShopping\Apps\Configuration\Antispam\Module\ClicShoppingAdmin\Config\ConfigParamAbstract and cannot be loaded.');
          }
        }
      }
    }

    return $result;
  }

  /**
   * Retrieves and processes the input parameters for the module configuration.
   *
   * This method extracts the necessary parameters based on the module's code,
   * initializes the configuration parameter objects, saves default configurations if required,
   * and generates the processed and sorted list of input parameters.
   *
   * @return array The sorted array of formatted input parameters.
   */
  public function getInputParameters()
  {
    $result = [];

    if ($this->code == 'GE') {
      $cut = 'CLICSHOPPING_APP_ANTISPAM_';
    } else {
      $cut = 'CLICSHOPPING_APP_ANTISPAM_' . $this->code . '_';
    }

    $cut_length = \strlen($cut);

    foreach ($this->getParameters() as $key) {
      $p = mb_strtolower(substr($key, $cut_length));

      $class = 'ClicShopping\Apps\Configuration\Antispam\Module\ClicShoppingAdmin\Config\\' . $this->code . '\Params\\' . $p;

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
