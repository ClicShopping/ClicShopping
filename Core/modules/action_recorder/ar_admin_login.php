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
 * The ar_admin_login class represents a module used for tracking admin login attempts, limiting
 * the number of failed attempts within a specified time period. It provides mechanisms to
 * configure and enforce login policies and manage action recorder data.
 *
 * Properties:
 * - $code: The unique code or identifier for the module.
 * - $title: Title of the module.
 * - $description: Description of the module.
 * - $sort_order: The order in which the module is displayed.
 * - $minutes: The allowed time frame, in minutes, for login attempts.
 * - $attempts: The maximum number of allowed login attempts within the specified time frame.
 * - $identifier: The identifier for recording login attempts, typically the user's IP.
 * - $enabled: Denotes whether the module is active.
 * - $group: The group or directory associated with the module.
 */
class ar_admin_login
{
  public string $code;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public $minutes = 5;
  public $attempts = 3;
  public $identifier;
  public $enabled = true;
  public $group;

  /**
   * Constructor for initializing the module's properties and settings.
   *
   * @return void
   */
  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);

    $this->title = CLICSHOPPING::getDef('module_action_recorder_admin_login_title');
    $this->description = CLICSHOPPING::getDef('module_action_recorder_admin_login_description');

    if ($this->check()) {
      if (\defined('MODULE_ACTION_RECORDER_ADMIN_LOGIN_MINUTES')) {
        $this->minutes = (int)MODULE_ACTION_RECORDER_ADMIN_LOGIN_MINUTES;
        $this->attempts = (int)MODULE_ACTION_RECORDER_ADMIN_LOGIN_ATTEMPTS;
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
   * Determines whether a user or identifier can perform an action based on certain constraints.
   *
   * @param string $user_name The username to check. If empty, only the identifier is considered.
   * @return bool Returns true if the action can be performed; otherwise, false.
   */
  public function canPerform($user_name)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $sql_query = 'select id
                    from :table_action_recorder
                    where module = :module
                   ';

    if (!empty($user_name)) {
      $sql_query .= ' and (user_name = :user_name or identifier = :identifier)';
    } else {
      $sql_query .= ' and identifier = :identifier';
    }

    $sql_query .= ' and date_added >= date_sub(now(),
                      interval :limit_minutes minute)
                      and success = 0
                      limit :limit_attempts
                    ';

    $Qcheck = $CLICSHOPPING_Db->prepare($sql_query);
    $Qcheck->bindValue(':module', $this->code);

    if (!empty($user_name)) {
      $Qcheck->bindValue(':user_name', $user_name);
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
   * Executes a database query to delete outdated entries from the action recorder table.
   * The entries are filtered by module type and determined to be expired if they were
   * added before a specified time interval.
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
   * Checks whether the constant 'MODULE_ACTION_RECORDER_ADMIN_LOGIN_MINUTES' is defined.
   *
   * @return bool Returns true if the constant is defined, false otherwise.
   */
  public function check()
  {
    return \defined('MODULE_ACTION_RECORDER_ADMIN_LOGIN_MINUTES');
  }

  /**
   * Adds configuration settings related to admin login attempts and time restrictions to the database.
   *
   * @return void
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Allowed Minutes',
        'configuration_key' => 'MODULE_ACTION_RECORDER_ADMIN_LOGIN_MINUTES',
        'configuration_value' => '5',
        'configuration_description' => 'Number of minutes to allow login attempts to occur.',
        'configuration_group_id' => '6',
        'sort_order' => '0',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Allowed Attempts',
        'configuration_key' => 'MODULE_ACTION_RECORDER_ADMIN_LOGIN_ATTEMPTS',
        'configuration_value' => '3',
        'configuration_description' => 'Number of login attempts to allow within the specified period.',
        'configuration_group_id' => '6',
        'sort_order' => '0',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Removes configuration entries from the database based on the keys provided.
   *
   * This method executes a delete SQL query on the configuration table.
   * The keys to be removed are determined by the keys() method.
   *
   * @return int The number of rows affected by the delete query.
   */
  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  /**
   *
   * @return array Returns an array of configuration keys used for the module.
   */
  public function keys()
  {
    return array('MODULE_ACTION_RECORDER_ADMIN_LOGIN_MINUTES', 'MODULE_ACTION_RECORDER_ADMIN_LOGIN_ATTEMPTS');
  }
}
