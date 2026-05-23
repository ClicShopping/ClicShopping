<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Interfaces;

/**
 * Interface for ClicShopping application components
 * 
 * Defines the contract for all app-level functionality including metadata management,
 * navigation, language support, and configuration management.
 */
interface AppInterface
{
  /**
   * Constructs a formatted link string using the provided arguments and predefined parameters.
   *
   * @return string The generated link string.
   */
  public function link(): string;

  /**
   * Redirects to a specified location with appended parameters.
   *
   * @return string The resulting redirection URL.
   */
  public function redirect(): string;

  /**
   * Get the application code identifier
   * 
   * @return string The application code
   */
  public function getCode(): string;

  /**
   * Get the vendor name
   * 
   * @return string The vendor associated with this instance.
   */
  public function getVendor(): string;

  /**
   * Get the application title
   * 
   * @return string Returns the title of the instance.
   */
  public function getTitle(): string;

  /**
   * Get the application version
   * 
   * @return string The version string of the current instance.
   */
  public function getVersion(): string;

  /**
   * Retrieves the list of modules.
   *
   * @return array The array of modules.
   */
  public function getModules();

  /**
   * Checks if a specific module of a given type exists.
   *
   * @param string $module The name of the module to check for.
   * @param string $type The type of the module to check for.
   * @return bool Returns true if the module exists, false otherwise.
   */
  public function hasModule(string $module, string $type): bool;

  /**
   * Get a language definition
   * 
   * @return string The definition string retrieved using the provided arguments.
   */
  public function getDef(): string;

  /**
   * Checks whether a definitions file exists for the specified group and optionally a language code.
   *
   * @param string $group The group name for which the definition file is checked.
   * @param string|null $language_code The optional language code to check, defaults to the system's current language if null.
   * @return bool Returns true if the definition file exists, otherwise false.
   */
  public function definitionsExist(string $group, ?string $language_code = null);

  /**
   * Loads the language definitions for the given group and optional language code.
   *
   * @param string $group The group of definitions to load.
   * @param string|null $language_code The optional language code to load definitions for. Defaults to the current language.
   * @return void
   */
  public function loadDefinitions(string $group, ?string $language_code = null): void;

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
   * @return void
   */
  public function saveCfgParam(string $key, mixed $value, ?string $title = null, ?string $description = null, ?string $set_func = null): void;

  /**
   * Deletes a configuration parameter from the database.
   * 
   * @param string $key The configuration key that identifies the parameter to be deleted.
   * @return void
   */
  public function deleteCfgParam(string $key): void;

  /**
   * Processes and organizes configuration applications found in a given directory.
   *
   * @param array $result A reference to the array where the resulting configuration applications are stored.
   * @param string $directory The directory to scan for configuration application files.
   * @param string $name_space_config The namespace under which the configuration classes are defined.
   * @param string $trigger_message The trigger error message to display for invalid classes.
   * @return void This method does not return a value; it modifies the $result array by reference.
   */
  public function getConfigApps(array $result, string $directory, string $name_space_config, string $trigger_message): void;
}
