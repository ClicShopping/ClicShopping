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
 * Class ar_create_account
 *
 * Implements the action recorder module for account creation. It helps track
 * and limit the number of account creation attempts based on user-defined
 * settings. The module allows configuration of time intervals, number of
 * attempts, and expiration of old log entries.
 */
class ar_create_account
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
   * Initializes the module by setting its code, group, title, and description.
   * Optionally sets the time limit for the email-based action recorder if the
   * module is already configured and the relevant constant is defined.
   *
   * @return void
   */
  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);

    $this->title = CLICSHOPPING::getDef('module_action_recorder_create_account_title');
    $this->description = CLICSHOPPING::getDef('module_action_recorder_create_account_description');

    if ($this->check()) {
      if (\defined('MODULE_ACTION_RECORDER_CREATE_ACCOUNT_EMAIL_MINUTES')) {
        $this->minutes = (int)MODULE_ACTION_RECORDER_CREATE_ACCOUNT_EMAIL_MINUTES;
      }
    }
  }

  /**
   *
   * @return void
   */
  public function setIdentifier()
  {
    $this->identifier = HTTP::getIpAddress();
  }

  /**
   * Determines whether a user can perform an action based on historical data.
   *
   * @param int|null $user_id The ID of the user attempting the action. If null, the check is performed based on the identifier only.
   * @return bool Returns true if the user can perform the action; otherwise, false if the limit or conditions are reached.
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
   * Removes expired entries from the action recorder table based on the specified module
   * and the time interval defined by the limit in minutes.
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
   * Checks if the constant 'MODULE_ACTION_RECORDER_CREATE_ACCOUNT_EMAIL_MINUTES' is defined.
   *
   * @return bool Returns true if the constant is defined, otherwise false.
   */
  public function check()
  {
    return \defined('MODULE_ACTION_RECORDER_CREATE_ACCOUNT_EMAIL_MINUTES');
  }

  /**
   * Installs the module configuration into the database.
   *
   * @return void
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'What do you want for the minimum minutes per e-mail for one person for create account ?',
        'configuration_key' => 'MODULE_ACTION_RECORDER_CREATE_ACCOUNT_EMAIL_MINUTES',
        'configuration_value' => '90',
        'configuration_description' => 'Minimum number of minutes to allow 1 e-mail to be sent (eg, 15 for 1 e-mail every 15 minutes)',
        'configuration_group_id' => '6',
        'sort_order' => '0',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Removes configuration entries from the database where the configuration keys
   * match the keys returned by the `keys()` method.
   *
   * @return int|false The number of rows affected by the delete operation, or false on failure.
   */
  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  /**
   * Retrieves the configuration keys used by the module.
   *
   * @return array Returns an array containing the configuration keys.
   */
  public function keys()
  {
    return array('MODULE_ACTION_RECORDER_CREATE_ACCOUNT_EMAIL_MINUTES');
  }
}
