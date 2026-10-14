<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\AdministratorMenu\Module\ClicShoppingAdmin\Config;

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

  /**
   * Initializes the implementation of the derived class.
   *
   * This method is intended to be implemented by subclasses to perform any necessary setup
   * or initialization logic specific to the subclass. Being abstract, it enforces that
   * all concrete subclasses provide their own implementation.
   */
  abstract protected function init();

  /**
   * Constructor method for initializing the class.
   *
   * Retrieves the AdministratorMenu from the Registry, sets the code property to the class's short name,
   * and initiates any additional setup through the init method.
   *
   * @return void
   */
  final public function __construct()
  {
    $this->app = Registry::get('AdministratorMenu');

    $this->code = (new \ReflectionClass($this))->getShortName();

    $this->init();
  }

  /**
   * Installs configuration parameters for the application in the database.
   *
   * For each parameter retrieved via the `getParameters` method, this method:
   * - Extracts the specific parameter name by trimming the predefined prefix and converting the key to lowercase.
   * - Dynamically creates an instance of the parameter configuration class.
   * - Saves the parameter and its associated data (default value, title, description, and setter function, if provided) using the application's configuration saving mechanism.
   *
   * @return void
   */
  public function install()
  {
    $cut_length = \strlen('CLICSHOPPING_APP_ADMINISTRATOR_MENU_' . $this->code . '_');

    foreach ($this->getParameters() as $key) {
      $p = mb_strtolower(substr($key, $cut_length));

      $class = 'ClicShopping\Apps\Tools\AdministratorMenu\Module\ClicShoppingAdmin\Config\\' . $this->code . '\Params\\' . $p;

      $cfg = new $class($this->code);

      $this->app->saveCfgParam($key, $cfg->default, $cfg->title ?? null, $cfg->description ?? null, $cfg->set_func ?? null);
    }
  }

  /**
   * Removes configuration entries related to the application from the database.
   *
   * Deletes all rows in the `:table_configuration` table where the `configuration_key`
   * matches the pattern 'CLICSHOPPING_APP_ADMINISTRATOR_MENU_' followed by the application's code and any additional characters.
   *
   * @return int The number of rows affected by the deletion query.
   */
  public function uninstall()
  {
    $Qdelete = $this->app->db->prepare('delete from :table_configuration
                                          where configuration_key
                                          like :configuration_key
                                          ');
    $Qdelete->bindValue(':configuration_key', 'CLICSHOPPING_APP_ADMINISTRATOR_MENU_' . $this->code . '_%');
    $Qdelete->execute();

    return $Qdelete->rowCount();
  }

  public function getParameters()
  {
    $result = [];

    $directory = CLICSHOPPING::BASE_DIR . 'Apps/Tools/AdministratorMenu/Module/ClicShoppingAdmin/Config/' . $this->code . '/Params';

    if ($dir = new \DirectoryIterator($directory)) {
      foreach ($dir as $file) {
        if (!$file->isDot() && !$file->isDir() && ($file->getExtension() == 'php')) {
          $class = 'ClicShopping\Apps\Tools\AdministratorMenu\Module\ClicShoppingAdmin\Config\\' . $this->code . '\\Params\\' . $file->getBasename('.php');

          if (is_subclass_of($class, 'ClicShopping\Apps\Tools\AdministratorMenu\Module\ClicShoppingAdmin\Config\ConfigParamAbstract')) {
            $result[] = 'CLICSHOPPING_APP_ADMINISTRATOR_MENU_' . $this->code . '_' . mb_strtoupper($file->getBasename('.php'));
          } else {
            trigger_error('ClicShopping\Apps\Tools\AdministratorMenu\Module\ClicShoppingAdmin\Config\\ConfigAbstract::getParameters(): ClicShopping\Apps\Tools\AdministratorMenu\Module\ClicShoppingAdmin\Config\\' . $this->code . '\\Params\\' . $file->getBasename('.php') . ' is not a subclass of ClicShopping\Apps\Tools\AdministratorMenu\Module\ClicShoppingAdmin\Config\ConfigParamAbstract and cannot be loaded.');
          }
        }
      }
    }

    return $result;
  }

  public function getInputParameters()
  {
    $result = [];

    $cut = 'CLICSHOPPING_APP_ADMINISTRATOR_MENU_' . $this->code . '_';

    $cut_length = \strlen($cut);

    foreach ($this->getParameters() as $key) {
      $p = mb_strtolower(substr($key, $cut_length));

      $class = 'ClicShopping\Apps\Tools\AdministratorMenu\Module\ClicShoppingAdmin\Config\\' . $this->code . '\Params\\' . $p;

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
