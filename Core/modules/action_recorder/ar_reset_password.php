<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

/**
 * Class ar_reset_password
 *
 * A module to log and manage password reset attempts using an action recorder.
 */
class ar_reset_password
{
  public string $code;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public $minutes = 5;
  public $attempts = 1;
  public $identifier;
  public $enabled = true;
  public $group;

  /**
   * Constructor method for initializing the action recorder reset password module.
   *
   * @return void
   */
  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);

    $this->title = CLICSHOPPING::getDef('module_action_recorder_reset_password_title');
    $this->description = CLICSHOPPING::getDef('module_action_recorder_reset_password_description');


    if ($this->check()) {
      if (\defined('MODULE_ACTION_RECORDER_RESET_PASSWORD_ATTEMPTS')) {
        $this->minutes = (int)MODULE_ACTION_RECORDER_RESET_PASSWORD_MINUTES;
        $this->attempts = (int)MODULE_ACTION_RECORDER_RESET_PASSWORD_ATTEMPTS;
      }
    }
  }

  /**
   * Sets the identifier property to the IP address retrieved from the HTTP request.
   *
   * @return void
   */
  public function setIdentifier()
  {
    $this->identifier = HTTP::getIpAddress();
  }

  /**
   * Checks if a user can perform an action based on predefined constraints.
   *
   * @param string $user_name The name of the user attempting the action.
   * @return bool Returns true if the user can perform the action, otherwise false.
   */
  public function canPerform($user_name)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->prepare('select id
                                            from :table_action_recorder
                                            where module = :module
                                            and user_name = :user_name
                                            and date_added >= date_sub(now(),
                                            interval :limit_minutes minute)
                                            and success = 1
                                            limit :limit_attempts
                                         ');
    $Qcheck->bindValue(':module', $this->code);
    $Qcheck->bindValue(':user_name', $user_name);
    $Qcheck->bindInt(':limit_minutes', $this->minutes);
    $Qcheck->bindInt(':limit_attempts', $this->attempts);
    $Qcheck->execute();

    if (\count($Qcheck->fetchAll()) == $this->attempts) {
      return false;
    }

    return true;
  }

  /**
   * Removes expired entries from the action recorder table based on the specified module and time limit.
   *
   * This method deletes records from the `:table_action_recorder` table where the `module` column matches
   * the current object's code and the `date_added` column is older than the defined interval in minutes.
   *
   * @return int The number of rows affected by the deletion query.
   */
  public function expireEntries()
  {
    $Qdel = Registry::get('Db')->prepare('delete
                                            from :table_action_recorder
                                            where module = :module
                                            and date_added < date_sub(now(),
                                            interval :limit_minutes minute)
                                          ');
    $Qdel->bindValue(':module', $this->code);
    $Qdel->bindInt(':limit_minutes', $this->minutes);
    $Qdel->execute();

    return $Qdel->rowCount();
  }

  /**
   * Checks if the constant MODULE_ACTION_RECORDER_RESET_PASSWORD_MINUTES is defined.
   *
   * @return bool True if the constant is defined, false otherwise.
   */
  public function check()
  {
    return \defined('MODULE_ACTION_RECORDER_RESET_PASSWORD_MINUTES');
  }

  /**
   * Installs the configuration settings for the module by adding required entries into the configuration table.
   *
   * @return void
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Allowed Minutes',
        'configuration_key' => 'MODULE_ACTION_RECORDER_RESET_PASSWORD_MINUTES',
        'configuration_value' => '5',
        'configuration_description' => 'Number of minutes to allow password resets to occur.',
        'configuration_group_id' => '6',
        'sort_order' => '0',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Allowed Attempts',
        'configuration_key' => 'MODULE_ACTION_RECORDER_RESET_PASSWORD_ATTEMPTS',
        'configuration_value' => '1',
        'configuration_description' => 'Number of password reset attempts to allow within the specified period.',
        'configuration_group_id' => '6',
        'sort_order' => '0',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Removes configuration entries from the database where the configuration_key matches any of the keys returned by the keys() method.
   *
   * @return int|bool Returns the number of affected rows on success, or false on failure.
   */
  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  /**
   * Retrieves the configuration keys related to the reset password action recorder.
   *
   * @return array The array of configuration keys.
   */
  public function keys()
  {
    return array('MODULE_ACTION_RECORDER_RESET_PASSWORD_MINUTES',
      'MODULE_ACTION_RECORDER_RESET_PASSWORD_ATTEMPTS'
    );
  }
}
