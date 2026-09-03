<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config;

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
   * Initializes the class instance by setting up the application registry and
   * retrieving the short name of the class. Also triggers the initialization process.
   *
   * @return void
   */
  final public function __construct()
  {
    $this->app = Registry::get('CompliancePolicyRules');

    $this->code = (new \ReflectionClass($this))->getShortName();
    $this->app->loadDefinitions('Module/ClicShoppingAdmin/Config/' . $this->code . '/' . $this->code);
    $this->init();
  }

  /**
   * Installs the configuration parameters for the module by iterating through
   * available parameters, processing them, and saving them using the app's configuration methods.
   *
   * @return void
   */
  public function install()
  {
    $this->installParameters();
    $this->registerInstalled();
  }

  /**
   * Adds this module to the list of installed modules, once.
   *
   * @return void
   */
  private function registerInstalled(): void
  {
    $installed = \defined('MODULE_MODULES_COMPLIANCE_POLICY_RULES_INSTALLED')
      ? explode(';', MODULE_MODULES_COMPLIANCE_POLICY_RULES_INSTALLED)
      : [];

    $entry = $this->app->vendor . '\\' . $this->app->code . '\\' . $this->code;

    // Appending without this check is what left three stale entries in the list.
    if (!\in_array($entry, $installed, true)) {
      $installed[] = $entry;
      $this->app->saveCfgParam('MODULE_MODULES_COMPLIANCE_POLICY_RULES_INSTALLED', implode(';', $installed));
    }
  }

  /**
   * Removes this module from the list of installed modules.
   *
   * @return void
   */
  private function unregisterInstalled(): void
  {
    if (!\defined('MODULE_MODULES_COMPLIANCE_POLICY_RULES_INSTALLED')) {
      return;
    }

    $installed = explode(';', MODULE_MODULES_COMPLIANCE_POLICY_RULES_INSTALLED);
    $position = array_search($this->app->vendor . '\\' . $this->app->code . '\\' . $this->code, $installed, true);

    if ($position !== false) {
      unset($installed[$position]);
      $this->app->saveCfgParam('MODULE_MODULES_COMPLIANCE_POLICY_RULES_INSTALLED', implode(';', $installed));
    }
  }

  /**
   * Writes the module's configuration parameters with their declared defaults.
   *
   * @return void
   */
  private function installParameters(): void
  {
    if ($this->code == 'CPR') {
      $cut = 'CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_';
    } else {
      $cut = 'CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_' . $this->code . '_';
    }

    $cut_length = \strlen($cut);

    foreach ($this->getParameters() as $key) {
      $p = mb_strtolower(substr($key, $cut_length));

      $class = 'ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config\\' . $this->code . '\Params\\' . $p;

      $cfg = new $class($this->code);

      $this->app->saveCfgParam($key, $cfg->default, $cfg->title ?? null, $cfg->description ?? null, $cfg->set_func ?? null);
    }
  }

  /**
   * Uninstalls the current module by removing related configuration entries from the database.
   *
   * Deletes the configuration entries this module installed.
   *
   * @return int The number of rows deleted.
   */
  public function uninstall()
  {
    $keys = $this->getParameters();

    if ($keys === []) {
      return 0;
    }

    // Delete the keys install() actually wrote. A LIKE on the prefix missed every CPR key (they
    // carry no module code) and a prefix without it would take FRE and CAD down with them.
    $this->unregisterInstalled();

    $removed = 0;

    foreach ($keys as $key) {
      $Qdelete = $this->app->db->prepare('delete from :table_configuration
                                            where configuration_key = :configuration_key
                                            ');
      $Qdelete->bindValue(':configuration_key', $key);
      $Qdelete->execute();
      $removed += $Qdelete->rowCount();
    }

    return $removed;
  }

  /**
   * Retrieves a list of parameter identifiers by scanning a specific directory
   * for valid parameter definition files. Each file is verified to ensure it
   * adheres to the expected subclass type.
   *
   * @return array Returns an array of parameter identifiers, constructed from the valid files found in the directory.
   */
  public function getParameters()
  {
    $result = [];

    $directory = CLICSHOPPING::BASE_DIR . 'Apps/Configuration/CompliancePolicyRules/Module/ClicShoppingAdmin/Config/' . $this->code . '/Params';

    if ($dir = new \DirectoryIterator($directory)) {
      foreach ($dir as $file) {
        if (!$file->isDot() && !$file->isDir() && ($file->getExtension() == 'php')) {
          $class = 'ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config\\' . $this->code . '\\Params\\' . $file->getBasename('.php');

          if (is_subclass_of($class, 'ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config\ConfigParamAbstract')) {
            if ($this->code == 'CPR') {
              $result[] = 'CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_' . mb_strtoupper($file->getBasename('.php'));
            } else {
              $result[] = 'CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_' . $this->code . '_' . mb_strtoupper($file->getBasename('.php'));
            }
          } else {
            trigger_error('ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config\\ConfigAbstract::getParameters(): ClicShopping\Apps\Configuration\Antispam\Module\ClicShoppingAdmin\Config\\' . $this->code . '\\Params\\' . $file->getBasename('.php') . ' is not a subclass of ClicShopping\Apps\Configuration\Antispam\Module\ClicShoppingAdmin\Config\ConfigParamAbstract and cannot be loaded.');
          }
        }
      }
    }

    return $result;
  }

  /**
   * Retrieves and processes input parameters for the current configuration.
   *
   * Iterates over a set of parameters specific to the configuration, loads their
   * respective parameter classes, and sets default values if not already defined.
   * Ensures that configured parameters are sorted by their sort order or index
   * before returning the final processed array.
   *
   * @return array An associative array of processed input parameters, sorted by their defined order.
   */
  public function getInputParameters()
  {
    $result = [];

    if ($this->code == 'CPR') {
      $cut = 'CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_';
    } else {
      $cut = 'CLICSHOPPING_APP_COMPLIANCE_POLICY_RULES_' . $this->code . '_';
    }

    $cut_length = \strlen($cut);

    foreach ($this->getParameters() as $key) {
      $p = mb_strtolower(substr($key, $cut_length));

      $class = 'ClicShopping\Apps\Configuration\CompliancePolicyRules\Module\ClicShoppingAdmin\Config\\' . $this->code . '\Params\\' . $p;

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
