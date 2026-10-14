<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

/**
 * Class ObjectInfo
 *
 * ObjectInfo is a utility class that dynamically assigns properties to the object
 * based on the provided associative array. The class facilitates the dynamic population
 * of object properties at runtime.
 * 
 * PHP 9 compatible implementation using magic methods instead of dynamic properties.
 */

class ObjectInfo
{
  /**
   * Internal storage for dynamic properties
   * @var array
   */
  private array $data = [];

  /**
   * Constructor method to initialize the object with provided data.
   *
   * @param array $object_array An associative array containing object information.
   * @return void
   */
  public function __construct(array $object_array)
  {
    $this->objectInfo($object_array);
  }

  /**
   * Populates the properties of the object with the key-value pairs from the provided array.
   *
   * @param array $object_array An associative array where keys correspond to property names and values are the values to be assigned.
   * @return void
   */
  public function objectInfo(array $object_array)
  {
    foreach ($object_array as $key => $value) {
      $this->data[$key] = $value;
    }
  }

  /**
   * Magic method to get a property value
   *
   * @param string $name Property name
   * @return mixed Property value or null if not set
   */
  public function __get(string $name): mixed
  {
    return $this->data[$name] ?? null;
  }

  /**
   * Magic method to set a property value
   *
   * @param string $name Property name
   * @param mixed $value Property value
   * @return void
   */
  public function __set(string $name, mixed $value): void
  {
    $this->data[$name] = $value;
  }

  /**
   * Magic method to check if a property is set
   *
   * @param string $name Property name
   * @return bool True if property exists
   */
  public function __isset(string $name): bool
  {
    return isset($this->data[$name]);
  }

  /**
   * Magic method to unset a property
   *
   * @param string $name Property name
   * @return void
   */
  public function __unset(string $name): void
  {
    unset($this->data[$name]);
  }
}