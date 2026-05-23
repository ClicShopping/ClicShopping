<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Module\ClicShoppingAdmin\Config;

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
   * Initializes the class by setting its application instance, determining its code
   * via reflection, loading required module definitions, and performing necessary initialization.
   *
   * @return void
   */
  final public function __construct()
  {
    $this->app = Registry::get('Products');

    $this->code = (new \ReflectionClass($this))->getShortName();

    $this->app->loadDefinitions('module/' . $this->code . '/' . $this->code);

    $this->init();
  }

  /**
   * Installs the configuration for the current module by iterating through its parameters
   * and saving their default settings.
   *
   * @return void
   */
  public function install()
  {
    $cut_length = \strlen('CLICSHOPPING_APP_CATALOG_PRODUCTS_' . $this->code . '_');

    foreach ($this->getParameters() as $key) {
      $p = mb_strtolower(substr($key, $cut_length));

      $class = 'ClicShopping\Apps\Catalog\Products\Module\ClicShoppingAdmin\Config\\' . $this->code . '\Params\\' . $p;

      $cfg = new $class($this->code);

      $this->app->saveCfgParam($key, $cfg->default, $cfg->title ?? null, $cfg->description ?? null, $cfg->set_func ?? null);
    }
  }

  /**
   * Uninstalls the current module by deleting configuration entries associated with it
   * from the configuration table. The configuration keys are matched using a specific
   * pattern tied to the module's code.
   *
   * @return int The number of rows affected by the delete operation.
   */
  public function uninstall()
  {
    $Qdelete = $this->app->db->prepare('delete from :table_configuration
                                          where configuration_key
                                          like :configuration_key
                                          ');
    $Qdelete->bindValue(':configuration_key', 'CLICSHOPPING_APP_CATALOG_PRODUCTS_' . $this->code . '_%');
    $Qdelete->execute();

    return $Qdelete->rowCount();
  }

  /**
   * Retrieves the parameters from a specific configuration directory that match the required subclass type.
   *
   * @return array An array of parameter identifiers. Each identifier matches a specific parameter
   *               class found in the configuration directory and adheres to the expected naming conventions.
   */
  public function getParameters()
  {
    $result = [];

    $directory = CLICSHOPPING::BASE_DIR . 'Apps/Catalog/Products/Module/ClicShoppingAdmin/Config/' . $this->code . '/Params';

    if ($dir = new \DirectoryIterator($directory)) {
      foreach ($dir as $file) {
        if (!$file->isDot() && !$file->isDir() && ($file->getExtension() == 'php')) {
          $class = 'ClicShopping\Apps\Catalog\Products\Module\ClicShoppingAdmin\Config\\' . $this->code . '\\Params\\' . $file->getBasename('.php');

          if (is_subclass_of($class, 'ClicShopping\Apps\Catalog\Products\Module\ClicShoppingAdmin\Config\ConfigParamAbstract')) {
            if ($this->code == 'PD') {
              $result[] = 'CLICSHOPPING_APP_CATALOG_PRODUCTS_' . mb_strtoupper($file->getBasename('.php'));
            } else {
              $result[] = 'CLICSHOPPING_APP_CATALOG_PRODUCTS_' . $this->code . '_' . mb_strtoupper($file->getBasename('.php'));
            }
          } else {
            trigger_error('ClicShopping\Apps\Catalog\Products\Module\ClicShoppingAdmin\Config\\ConfigAbstract::getParameters(): ClicShopping\Apps\Catalog\Products\Module\ClicShoppingAdmin\Config\\' . $this->code . '\\Params\\' . $file->getBasename('.php') . ' is not a subclass of ClicShopping\Apps\Catalog\Products\Module\ClicShoppingAdmin\Config\ConfigParamAbstract and cannot be loaded.');
          }
        }
      }
    }

    return $result;
  }

  /**
   * Retrieves and processes input parameters specific to the configured module.
   *
   * The method determines the appropriate parameters based on the module code,
   * processes default values, saves configurations as necessary, and orders the
   * resulting set fields in numeric order.
   *
   * @return array An ordered array containing the configured and processed input parameters.
   */
  public function getInputParameters()
  {
    $result = [];

    if ($this->code == 'PD') {
      $cut = 'CLICSHOPPING_APP_CATALOG_PRODUCTS_';
    } else {
      $cut = 'CLICSHOPPING_APP_CATALOG_PRODUCTS_' . $this->code . '_';
    }

    $cut_length = \strlen($cut);

    foreach ($this->getParameters() as $key) {
      $p = mb_strtolower(substr($key, $cut_length));

      $class = 'ClicShopping\Apps\Catalog\Products\Module\ClicShoppingAdmin\Config\\' . $this->code . '\Params\\' . $p;

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
