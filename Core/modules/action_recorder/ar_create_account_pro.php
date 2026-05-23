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
 * Class ar_create_account_pro
 *
 * Handles actions and rule enforcement for creating professional accounts.
 * This class ensures rate limiting and tracks user actions to prevent abuse.
 */
class ar_create_account_pro
{
  public string $code;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public $minutes = 90;
  public $attempts = 4;
  public $identifier;
  public $enabled = true;
  public $group;

  /**
   * Constructor method for initializing the class properties and configuration.
   * Sets the class code, group, title, and description. Checks for existing
   * configuration to set additional parameters like email time intervals.
   *
   * @return void
   */
  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);

    $this->title = CLICSHOPPING::getDef('module_action_recorder_create_account_pro_title');
    $this->description = CLICSHOPPING::getDef('module_action_recorder_create_account_pro_description');

    if ($this->check()) {
      if (\defined('MODULE_ACTION_RECORDER_CREATE_ACCOUNT_PRO_EMAIL_MINUTES')) {
        $this->minutes = (int)MODULE_ACTION_RECORDER_CREATE_ACCOUNT_PRO_EMAIL_MINUTES;
      }
    }
  }

  /**
   * Sets the identifier property of the class with the current IP address
   * retrieved from HTTP::getIpAddress().
   *
   * @return void
   */
  public function setIdentifier()
  {
    $this->identifier = HTTP::getIpAddress();
  }

  /**
   * Determines whether the action can be performed based on certain conditions, such as user ID, identifier,
   * time constraints, and allowed attempts.
   *
   * @param int|null $user_id The ID of the user attempting the action. Can be null.
   * @return bool True if the action can be performed, false otherwise.
   */
  public function canPerform($user_id)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $sql_query = 'select id
                    from :table_action_recorder
                    where module = :module
                   ';

    if (!empty($user_id)) {
      $sql_query .= ' and (user_id = :user_id or identifier = :identifier)';
    } else {
      $sql_query .= ' and identifier = :identifier';
    }

    $sql_query .= ' and date_added >= date_sub(now(), interval :limit_minutes minute)
                      and success = 1
                      limit :limit_attempts
                      ';

    $Qcheck = $CLICSHOPPING_Db->prepare($sql_query);
    $Qcheck->bindValue(':module', $this->code);

    if (!empty($user_id)) {
      $Qcheck->bindInt(':user_id', $user_id);
    }

    $Qcheck->bindValue(':identifier', $this->identifier);
    $Qcheck->bindInt(':limit_minutes', $this->minutes);
    $Qcheck->bindInt(':limit_attempts', $this->attempts);
    $Qcheck->execute();

    if (\count($Qcheck->fetchAll()) == $this->attempts) {
      return false;
    }

    return true;
  }

  /**
   * Removes entries from the action recorder table that are older than a specified time limit.
   *
   * This method deletes records corresponding to the current module where the
   * added date is older than the defined time limit in minutes. The time limit
   * is determined by the "minutes" property of the current object.
   *
   * @return int The number of rows that were deleted.
   */
  public function expireEntries()
  {
    $Qdel = Registry::get('Db')->prepare('delete
                                            from :table_action_recorder
                                            where module = :module
                                            and date_added < date_sub(now(), interval :limit_minutes minute)
                                          ');
    $Qdel->bindValue(':module', $this->code);
    $Qdel->bindInt(':limit_minutes', $this->minutes);
    $Qdel->execute();

    return $Qdel->rowCount();
  }

  /**
   * Checks if the constant 'MODULE_ACTION_RECORDER_CREATE_ACCOUNT_PRO_EMAIL_MINUTES' is defined.
   *
   * @return bool Returns true if the constant is defined, false otherwise.
   */
  public function check()
  {
    return \defined('MODULE_ACTION_RECORDER_CREATE_ACCOUNT_PRO_EMAIL_MINUTES');
  }

  /**
   * Installs the configuration settings for the minimum minutes per e-mail restriction
   * when creating a professional account.
   *
   * @return void
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'What do you want for the minimum minutes per e-mail for one person for create professional account?',
        'configuration_key' => 'MODULE_ACTION_RECORDER_CREATE_ACCOUNT_PRO_EMAIL_MINUTES',
        'configuration_value' => '90',
        'configuration_description' => 'Minimum number of minutes to allow 1 e-mail to be sent (eg, 15 for 1 e-mail every 15 minutes)',
        'configuration_group_id' => '6',
        'sort_order' => '0',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Removes configuration entries from the database where the configuration key matches
   * any of the keys provided by the keys() method.
   *
   * @return int|false The number of rows affected by the delete operation, or false on failure.
   */
  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  /**
   * Retrieves the list of configuration keys related to the module.
   *
   * @return array Returns an array of configuration key strings.
   */
  public function keys()
  {
    return array('MODULE_ACTION_RECORDER_CREATE_ACCOUNT_PRO_EMAIL_MINUTES');
  }
}
