<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\ClicShoppingAdmin;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use function defined;
use function is_null;

/**
 * Class ActionRecorderAdmin
 *
 * This class extends the Shop ActionRecorder functionality and acts as the admin side representation of the action recorder module within the ClicShoppingAdmin site.
 * It handles initialization, validation checks to ensure modules are installed, and sets user attributes for tracking.
 */
class ActionRecorderAdmin extends \ClicShopping\Apps\Tools\ActionsRecorder\Classes\Shop\ActionRecorder
{
  private mixed $lang;

  /**
   * Constructor method for initializing the module, user ID, and user name.
   *
   * @param string $module The name of the module to be initialized.
   * @param string|null $user_id Optional user ID, must be numeric if provided.
   * @param string|null $user_name Optional user name.
   * @return void
   */
  public function __construct(string $module, ?string $user_id = null, ?string $user_name = null)
  {
    $this->lang = Registry::get('Language');

    $module = HTML::sanitize(str_replace(' ', '', $module));

    $this->_module = $module;

    $this->isInstalled();

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
   * Checks if the specified module is installed and its respective class and file exist.
   *
   * @return bool Returns true if the module is installed and the corresponding class and file are available; otherwise, returns false.
   */
  public function isInstalled()
  {
    $module = HTML::sanitize(str_replace(' ', '', $this->_module));

    if (defined('MODULE_ACTION_RECORDER_INSTALLED') && !is_null(MODULE_ACTION_RECORDER_INSTALLED)) {
      if (!is_null($module) && \in_array($module . '.' . substr(CLICSHOPPING::getIndex(), (strrpos(CLICSHOPPING::getIndex(), '.') + 1)), explode(';', MODULE_ACTION_RECORDER_INSTALLED))) {

        if (!class_exists($module)) {
          if (is_file(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/modules/action_recorder/' . $module . '.' . substr(CLICSHOPPING::getIndex(), (strrpos(CLICSHOPPING::getIndex(), '.') + 1)))) {

            $this->lang->loadDefinitions('Shop/Module/ActionRecorder/' . $module);

            include_once(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/modules/action_recorder/' . $module . '.' . substr(CLICSHOPPING::getIndex(), (strrpos(CLICSHOPPING::getIndex(), '.') + 1)));
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
  }
}
