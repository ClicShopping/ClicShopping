<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Modules;

use ClicShopping\OM\Registry;
use ReflectionClass;

/**
 * Abstract class defining the structure and behavior for HeaderTags modules.
 * Provides a foundation for managing header tag-related functionality, including
 * initialization, output generation, installation, and configuration keys management.
 *
 * Properties:
 * - $code: The unique code identifier for the module.
 * - $title: Optional title for the module.
 * - $description: Description of the module's functionality.
 * - $sort_order: An optional integer to determine the order of execution.
 * - $enabled: A boolean indicating whether the module is enabled.
 *
 * Methods:
 * - init(): Abstract method for initializing module-specific logic.
 * - getOutput(): Abstract method to define and retrieve the output of the module.
 * - install(): Abstract method for handling the installation process of the module.
 * - keys(): Abstract method for retrieving an array of configuration keys associated with the module.
 * - __construct(): Final constructor method to initialize the module, including assigning the code
 *   identifier and database connection, then invoking the init() method.
 * - isEnabled(): Returns whether the module is currently enabled.
 * - check(): Checks if the sort order is set for the module.
 * - remove(): Deletes configuration keys associated with the module from the database.
 */
abstract class HeaderTagsAbstract implements \ClicShopping\OM\Modules\HeaderTagsInterface
{
  public string $code;
  public $title;
  public $description;
  public int|null $sort_order = null;
  public bool $enabled = false;

  private mixed $db;

  abstract protected function init();

  abstract public function getOutput();

  abstract public function install();

  abstract public function keys();

  /**
   * Constructs a new instance of the class.
   *
   * @return void
   */
  final public function __construct()
  {
    $this->code = (new ReflectionClass($this))->getShortName();

    $this->db = Registry::get('Db');

    $this->init();
  }

  /**
   * Checks if the current instance is enabled.
   *
   * @return bool Returns true if the instance is enabled, false otherwise.
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * Checks whether the `sort_order` property is set.
   *
   * @return bool Returns true if the `sort_order` property is set; otherwise, false.
   */
  public function check()
  {
    return isset($this->sort_order);
  }

  public function remove()
  {
    return $this->db->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }
}
