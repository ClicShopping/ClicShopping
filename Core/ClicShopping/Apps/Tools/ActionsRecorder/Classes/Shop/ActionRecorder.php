<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\ActionsRecorder\Classes\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use function defined;
use function in_array;
use function is_null;

class ActionRecorder
{
  public $_module;
  public $_user_id;
  public $_user_name;

  /**
   * Constructor method to initialize the action recorder module.
   *
   * @param string $module The name of the module to be loaded.
   * @param int|null $user_id The ID of the user associated with the action, optional.
   * @param string|null $user_name The name of the user associated with the action, optional.
   * @return void|false Returns void on successful initialization, or false if the module cannot be loaded.
   */
  public function __construct(string $module, ?int $user_id = null, ?string $user_name = null)
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Language = Registry::get('Language');

    $module = HTML::sanitize(str_replace(' ', '', $module));

    if (defined('MODULE_ACTION_RECORDER_INSTALLED') && !is_null(MODULE_ACTION_RECORDER_INSTALLED)) {
      if (!is_null($module) && in_array($module . '.' . substr(CLICSHOPPING::getIndex(), (strrpos(CLICSHOPPING::getIndex(), '.') + 1)), explode(';', MODULE_ACTION_RECORDER_INSTALLED))) {
        if (!class_exists($module)) {
          if (is_file($CLICSHOPPING_Template->getModuleDirectory() . '/action_recorder/' . $module . '.' . substr(CLICSHOPPING::getIndex(), (strrpos(CLICSHOPPING::getIndex(), '.') + 1)))) {
            $CLICSHOPPING_Language->loadDefinitions('modules/action_recorder/' . $module);
            include($CLICSHOPPING_Template->getModuleDirectory() . '/action_recorder/' . $module . '.' . substr(CLICSHOPPING::getIndex(), (strrpos(CLICSHOPPING::getIndex(), '.') + 1)));
          } else {
            return false;
          }
        }
      } else {
        return false;
      }
    } else {
      return false;
    }

    $this->_module = $module;

    if (!empty($user_id) && is_numeric($user_id)) {
      $this->_user_id = $user_id;
    }

    if (!empty($user_name)) {
      $this->_user_name = $user_name;
    }

    $GLOBALS[$this->_module] = new $module();
    $GLOBALS[$this->_module]->setIdentifier();
  }

  /**
   * Retrieves the current module instance.
   *
   * This method returns the module object that is associated with the current
   * instance of the class. The module is identified by the `_module` property.
   *
   * @return object The module instance stored in the global variable.
   */
  public function getModule()
  {
    return $GLOBALS[$this->_module];
  }

  /**
   * Determines if the specified module can perform an action based on user ID and user name.
   *
   * @return bool Returns true if the module can perform the action, false otherwise.
   */
  public function canPerform()
  {
    if (!is_null($this->_module)) {
      return $this->getModule()->canPerform($this->_user_id, $this->_user_name);
    }

    return false;
  }

  /**
   * Retrieves the title of the module.
   *
   * @return string|null The title of the module if it exists, or null if the module is not set.
   */
  public function getTitle()
  {
    if (!is_null($this->_module)) {
      return $this->getModule()->title;
    }
  }

  /**
   * Retrieves the identifier of the current module.
   *
   * @return mixed Returns the identifier of the module if the module is set; otherwise, returns null.
   */
  public function getIdentifier()
  {
    if (!is_null($this->_module)) {
      return $this->getModule()->identifier;
    }
  }

  /**
   * Records an action performed by a module with success status, user details, and timestamp.
   *
   * @param bool $success Indicates whether the action was successful. Defaults to true.
   * @return void
   */
  public function record(bool $success = true)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if (!is_null($this->_module)) {
      if ($success === true) {
        $success = 1;
      } else {
        $success = 0;
      }

      $sql_array = [
        'module' => $this->_module,
        'user_id' => (int)$this->_user_id,
        'user_name' => $this->_user_name,
        'identifier' => $this->getIdentifier(),
        'success' => $success,
        'date_added' => 'now()'
      ];

      $CLICSHOPPING_Db->save('action_recorder', $sql_array);
    }
  }

  /**
   * Triggers the expiration of entries in the associated module.
   *
   * @return bool|null Returns the result of the expiration operation from the module,
   *                   or null if no module is associated.
   */
  public function expireEntries()
  {
    if (!is_null($this->_module)) {
      return $this->getModule()->expireEntries();
    }
  }
}
