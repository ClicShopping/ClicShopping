<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Domains;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

/**
 * Abstract base class for configuration actions (Install, Delete, Process, Uninstall)
 * Provides common functionality for all configuration actions across all apps
 */
abstract class ConfigureActionsAbstract extends PagesActionsAbstract
{
  protected $app;
  protected $appName;
  protected $appKey;
  protected $messageStack;

  /**
   * Initialize common properties based on the namespace
   * @return void
   */
  protected function init()
  {
    // Extract app information from namespace
    $reflection = new \ReflectionClass($this);
    $namespace = $reflection->getNamespaceName();
    $parts = explode('\\', $namespace);
    
    if (count($parts) >= 4) {
      $this->appName = $parts[3];
      $this->appKey = $this->appName; // Registry key
    }
    
    $this->app = Registry::get($this->appKey);
    $this->messageStack = Registry::get('MessageStack');
  }

  /**
   * @return string
   * Get the current module from page data
   */
  protected function getCurrentModule(): string
  {
    return $this->page->data['current_module'] ?? '';
  }

  /**
   * Get the configuration module instance
   * @param string $module
   * @return mixed
   */
  protected function getConfigModule(string $module)
  {
    return Registry::get($this->appKey . 'AdminConfig' . $module);
  }

  /**
   * Redirect to the configure page
   * @param string $module
   * @return void
   */
  protected function redirectToConfigure(string $module): void
  {
    $this->app->redirect('Configure&module=' . $module);
  }

  /**
   * Add success message
   * @param string $message
   * @return void
   */
  protected function addSuccessMessage(string $message): void
  {
    $this->messageStack->add($message, 'success', $this->appKey);
  }
  
  /**
   * Clear administrator menu cache
   * @return void
   */
  protected function clearMenuCache(): void
  {
    Cache::clear('menu-administrator');
  }

  /**
   * Remove all entries from a specified database table
   * @param string $table_name
   * @return void
   */
  protected function removeTableNameFromDb(string $table_name): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table' . $table_name . '"');

    if ($Qcheck->fetch() !== false) {
      $Qdelete = $CLICSHOPPING_Db->prepare('delete from :table' . $table_name . '');
      $Qdelete->execute();
    }
  }
}