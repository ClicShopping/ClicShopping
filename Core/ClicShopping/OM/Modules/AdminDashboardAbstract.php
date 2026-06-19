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
use ClicShopping\OM\Interfaces\AdminDashboardInterface;

/**
 * AdminDashboardAbstract defines the structure and behavior for all Admin Dashboard modules.
 * This class provides a foundation for creating customizable administrative dashboard modules
 * and enforces the implementation of specific methods in derived classes.
 */
abstract class AdminDashboardAbstract implements AdminDashboardInterface
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
   * Constructor method for initializing the class.
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
   * Checks if the feature or functionality is enabled.
   *
   * @return bool Returns true if enabled, false otherwise.
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * Checks if the sort_order property is set.
   *
   * @return bool Returns true if the sort_order property is set, otherwise false.
   */
  public function check()
  {
    return isset($this->sort_order);
  }

  /**
   * Executes a delete query on the database to remove configuration entries
   * where the configuration keys match the keys returned by the keys() method.
   *
   * @return int|false Returns the number of rows affected by the delete query
   *                   or false on failure.
   */
  public function remove()
  {
    return $this->db->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }
}
