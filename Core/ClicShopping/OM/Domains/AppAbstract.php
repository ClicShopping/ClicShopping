<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Domains;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Interfaces\AppInterface;
use DirectoryIterator;
use ReflectionClass;

use function call_user_func;
use function call_user_func_array;
use function defined;
use function func_get_args;
use function is_null;

/**
 * This abstract class serves as a foundational framework for applications within the system.
 * It provides core functionalities such as metadata management, database and language bindings,
 * and mechanisms for managing application-specific modules and configurations.
 */
abstract class AppAbstract implements AppInterface
{
  public string $code;
  public $title;
  public string $vendor;
  public string $version;
  public array $modules = [];

  public mixed $db;
  public mixed $lang;

  abstract protected function init();

  /**
   * Initializes the class by setting initial information, retrieving
   * database and language instances from the registry, and performing
   * any necessary initialization steps.
   *
   * @return void
   */
  final public function __construct()
  {
    $this->setInfo();

    $this->db = Registry::get('Db');
    $this->lang = Registry::get('Language');
    $this->init();
  }

  /**
   * Constructs a formatted link string using the provided arguments and predefined parameters.
   *
   * @return string The generated link string.
   */
  final public function link(): string
  {
    $args = func_get_args();

    $parameters = 'A&' . $this->vendor . '\\' . $this->code;

    if (isset($args[0])) {
      $args[0] = $parameters .= '&' . $args[0];
    } else {
      $args[0] = $parameters;
    }

    array_unshift($args, 'index.php');

    return forward_static_call_array([
      'ClicShopping\OM\CLICSHOPPING',
      'link'
    ], $args);
  }

  /**
   * Redirects to a specified location with appended parameters.
   *
   * @return string The resulting redirection URL.
   */
  final public function redirect(): string
  {
    $args = func_get_args();

    $parameters = 'A&' . $this->vendor . '\\' . $this->code;

    if (isset($args[0])) {
      $args[0] = $parameters .= '&' . $args[0];
    } else {
      $args[0] = $parameters;
    }

    array_unshift($args, 'index.php');

    return forward_static_call_array([
      'ClicShopping\OM\CLICSHOPPING',
      'redirect'
    ], $args);
  }

  /**
   *
   * @return string
   */
  final public function getCode(): string
  {
    return $this->code;
  }

  /**
   *
   * @return string The vendor associated with this instance.
   */
  final public function getVendor(): string
  {
    return $this->vendor;
  }

  /**
   *
   * @return string Returns the title of the instance.
   */
  final public function getTitle(): string
  {
    return $this->title;
  }

  /**
   *
   * @return string The version string of the current instance.
   */
  final public function getVersion(): string
  {
    return $this->version;
  }

  /**
   * Retrieves the list of modules.
   *
   * @return array The array of modules.
   */
  final public function getModules()
  {
    return $this->modules;
  }

  /**
   * Checks if a specific module of a given type exists.
   *
   * @param string $module The name of the module to check for.
   * @param string $type The type of the module to check for.
   * @return bool Returns true if the module exists, false otherwise.
   */
  final public function hasModule(string $module, string $type): bool
  {
    return isset($this->modules[$type]) && in_array($module, $this->modules[$type]);
  }

  /**
   * Sets the app information by reflecting the current class and loading data
   * from a metafile. Assigns the app's code, vendor, title, version, and modules
   * if available. Triggers an error if the metafile cannot be located or parsed.
   *
   * @return bool Returns false if the metafile cannot be read or is invalid,
   *              otherwise no explicit value will be returned.
   */
  private function setInfo()
  {
    $r = new ReflectionClass($this);

    $this->code = $r->getShortName();
    $this->vendor = \array_slice(explode('\\', $r->getNamespaceName()), -2, 1)[0];

    $metafile = CLICSHOPPING::BASE_DIR . 'Apps/' . $this->vendor . DIRECTORY_SEPARATOR . $this->code . '/clicshopping.json';

    if (!is_file($metafile) || (($json = json_decode(file_get_contents($metafile), true)) === null)) {
      trigger_error('ClicShopping\OM\Domains\AppAbstract::setInfo(): ' . $this->vendor . '\\' . $this->code . ' - Could not read App information in ' . $metafile . '.');

      return false;
    }

    $this->title = $json['title'];
    $this->version = $json['version'];

    if (!empty($json['modules'])) {
      $this->modules = $json['modules'];
    }
  }

  /**
   *
   * @return string The definition string retrieved using the provided arguments.
   */
  final public function getDef(): string
  {
    $args = func_get_args();

    if (!isset($args[0])) {
      $args[0] = null;
    }

    if (!isset($args[1])) {
      $args[1] = null;
    }

    if (!isset($args[2])) {
      $args[2] = $this->vendor . '-' . $this->code;
    }

    return ($this->lang->getDef(...))(...$args);
  }

  /**
   * Checks whether a definitions file exists for the specified group and optionally a language code.
   *
   * @param string $group The group name for which the definition file is checked.
   * @param string|null $language_code The optional language code to check, defaults to the system's current language if null.
   *
   * @return bool Returns true if the definition file exists, otherwise false.
   */
  final public function definitionsExist(string $group, ?string $language_code = null)
  {
    $language_code = isset($language_code) && $this->lang->exists($language_code) ? $language_code : $this->lang->get('code');

    $pathname = CLICSHOPPING::BASE_DIR . 'Apps/' . $this->vendor . DIRECTORY_SEPARATOR . $this->code . '/languages/' . $this->lang->get('directory', $language_code) . DIRECTORY_SEPARATOR . $group . '.txt';

    if (is_file($pathname)) {
      return true;
    }

    if ($language_code != DEFAULT_LANGUAGE) {
      return call_user_func([$this, __FUNCTION__], $group, DEFAULT_LANGUAGE);
    }

    return false;
  }

  /**
   * Loads the language definitions for the given group and optional language code.
   *
   * @param string $group The group of definitions to load.
   * @param string|null $language_code The optional language code to load definitions for. Defaults to the current language.
   * @return void
   */
  final public function loadDefinitions(string $group, ?string $language_code = null): void
  {
    $language_code = isset($language_code) && $this->lang->exists($language_code) ? $language_code : $this->lang->get('code');

    if ($language_code != DEFAULT_LANGUAGE) {
      $this->loadDefinitions($group, DEFAULT_LANGUAGE);
    }

    $pathname = CLICSHOPPING::BASE_DIR . 'Apps/' . $this->vendor . DIRECTORY_SEPARATOR . $this->code . '/languages/' . $this->lang->get('directory', $language_code) . DIRECTORY_SEPARATOR . $group . '.txt';

    if (!is_file($pathname)) {
      $language_code = DEFAULT_LANGUAGE;
      $pathname = CLICSHOPPING::BASE_DIR . 'Apps/' . $this->vendor . DIRECTORY_SEPARATOR . $this->code . '/languages/' . $this->lang->get('directory', $language_code) . DIRECTORY_SEPARATOR . $group . '.txt';
    }

    $group = 'Apps/' . $this->vendor . DIRECTORY_SEPARATOR . $this->code . DIRECTORY_SEPARATOR . $group;

    $defs = $this->lang->getDefinitions($group, $language_code, $pathname);

    $this->lang->injectDefinitions($defs, $this->vendor . '-' . $this->code);
  }

  /**
   * Saves a configuration parameter to the database. If the parameter does not already exist,
   * it is created with additional metadata such as title and description. If the parameter already
   * exists, its value is updated.
   *
   * @param string $key The configuration key. It should be unique and is used to identify the parameter.
   * @param mixed $value The value to be associated with the specified configuration key.
   * @param string|null $title Optional. The title of the configuration parameter. If not provided, a default value is set.
   * @param string|null $description Optional. The description of the configuration parameter. If not provided, a default value is set.
   * @param string|null $set_func Optional. The function used to generate a set value or additional related data.
   *
   * @return void
   */
  final public function saveCfgParam(string $key, mixed $value, ?string $title = null, ?string $description = null, ?string $set_func = null): void
  {
    if (is_null($value)) {
      $value = '';
    }

    if (!defined($key)) {
      if (!isset($title)) {
        $title = 'Parameter [' . $this->getTitle() . ']';
      }

      if (!isset($description)) {
        $description = 'Parameter [' . $this->getTitle() . ']';
      }

      $data = [
        'configuration_title' => $title,
        'configuration_key' => $key,
        'configuration_value' => $value,
        'configuration_description' => $description,
        'configuration_group_id' => '6',
        'sort_order' => '0',
        'date_added' => 'now()'
      ];

      if (isset($set_func)) {
        $data['set_function'] = $set_func;
      }

      $this->db->save('configuration', $data);

      define($key, $value);
    } else {
      $this->db->save('configuration', [
        'configuration_value' => $value
      ], [
        'configuration_key' => $key
      ]);
    }
  }

  /**
   *
   * @param string $key The configuration key that identifies the parameter to be deleted.
   * @return void
   */
  final public function deleteCfgParam(string $key): void
  {
    $this->db->delete('configuration', [
      'configuration_key' => $key
    ]);
  }

  /**
   * Processes and organizes configuration applications found in a given directory.
   *
   * @param array $result A reference to the array where the resulting configuration applications are stored.
   * @param string $directory The directory to scan for configuration application files.
   * @param string $name_space_config The namespace under which the configuration classes are defined.
   * @param string $trigger_message The trigger error message to display for invalid classes.
   * @return void This method does not return a value; it modifies the $result array by reference.
   */

  final public function getConfigApps(array $result, string $directory, string $name_space_config, string $trigger_message): void
  {
    if ($dir = new DirectoryIterator($directory)) {
      foreach ($dir as $file) {
        if (!$file->isDot() && $file->isDir() && is_file($file->getPathname() . DIRECTORY_SEPARATOR . $file->getFilename() . '.php')) {
          $class = '' . $name_space_config . '\\' . $file->getFilename() . '\\' . $file->getFilename();

          if (is_subclass_of($class, '' . $name_space_config . '\ConfigAbstract')) {
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
            trigger_error('' . $trigger_message . '' . $name_space_config . '\\' . $file->getFilename() . '\\' . $file->getFilename() . ' is not a subclass of ' . $name_space_config . '\ConfigAbstract and cannot be loaded.');
          }
        }
      }

      ksort($result, SORT_NUMERIC);
    }
  }
}
