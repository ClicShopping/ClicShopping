<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Interfaces;

/**
 * Interface for configurable ClicShopping applications
 * 
 * Extends AppInterface with configuration management capabilities, allowing applications
 * to dynamically load and manage configuration modules. This interface is designed for
 * applications that require modular configuration systems with multiple configuration
 * modules that can be loaded and managed at runtime.
 */
interface ConfigurableAppInterface extends AppInterface
{
  /**
   * Retrieves a sorted list of configuration modules available for this application.
   * 
   * This method scans the application's configuration directory for subdirectories
   * containing configuration modules. Each valid configuration module must be a
   * subclass of ConfigAbstract and comply with the application's namespace structure.
   * The modules are sorted based on their sort order metadata.
   * 
   * @return mixed The sorted list of configuration modules. Returns an empty array if no valid modules are found.
   */
  public function getConfigModules(): mixed;

  /**
   * Retrieves configuration module information based on the provided module and information key.
   * 
   * This method loads the specified configuration module (if not already loaded) and
   * retrieves the requested information property from it. The module is registered
   * in the Registry for efficient reuse.
   * 
   * @param string $module The name of the module for which configuration information is requested.
   * @param string $info The specific information key within the module to retrieve (e.g., 'sort_order', 'title', 'description').
   * @return mixed Returns the requested configuration information or null if not available.
   */
  public function getConfigModuleInfo(string $module, string $info): mixed;

  /**
   * Retrieves the version of the API.
   * 
   * The API version is used to track compatibility between the application and
   * its configuration modules. This allows for versioned configuration systems
   * that can evolve over time while maintaining backward compatibility.
   * 
   * @return string|int The API version number.
   */
  public function getApiVersion(): string|int;

  /**
   * Gets the identifier associated with the application.
   * 
   * The identifier is a unique string that distinguishes this application from
   * others in the system. It is typically used for configuration storage,
   * registry keys, and other application-specific identification needs.
   * 
   * @return string The unique identifier associated with the application.
   */
  public function getIdentifier(): string;
}
