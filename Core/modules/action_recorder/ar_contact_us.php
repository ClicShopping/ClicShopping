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
 * Class used to manage action recording for the "Contact Us" feature.
 * Ensures that certain actions, such as sending emails, are restricted
 * based on predefined limits to prevent misuse.
 */
class ar_contact_us
{
  public string $code;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public $minutes = 15;
  public $attempts = 3;
  public $identifier;
  public $enabled = true;
  public string $group;

  /**
   * Constructor method for initializing the class properties.
   *
   * @return void
   */
  public function __construct()
  {
    $this->code = get_class($this);
    /**
     *
     */
      $this->group = basename(__DIR__);

    $this->title = CLICSHOPPING::getDef('module_action_recorder_contact_us_title');
    $this->description = CLICSHOPPING::getDef('module_action_recorder_contact_us_description');

    if ($this->check()) {
      if (\defined('MODULE_ACTION_RECORDER_CONTACT_US_EMAIL_MINUTES')) {
        $this->minutes = (int)MODULE_ACTION_RECORDER_CONTACT_US_EMAIL_MINUTES;
      }
    }
  }

  /**
   *
   * Sets the identifier property to the IP address obtained via the HTTP::getIpAddress method.
   *
   * @return void
   */
  public function setIdentifier()
  {
    $this->identifier = HTTP::getIpAddress();
  }

  /**
   * Checks whether the specified user or identifier can perform the action
   * based on recent records and predefined limits.
   *
   * @param int|null $user_id The ID of the user to check, or null if only the identifier is used.
   * @return bool Returns true if the action can be performed, false otherwise.
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
   * Deletes expired entries from the action recorder table based on the module and a time interval.
   *
   * This method identifies and removes entries from the `:table_action_recorder` table
   * where the module matches the specified module code and the date added is older than
   * the defined limit in minutes. The number of affected rows is returned after execution.
   *
   * @return int The number of rows deleted from the table.
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
   * Checks if the constant 'MODULE_ACTION_RECORDER_CONTACT_US_EMAIL_MINUTES' is defined.
   *
   * @return bool True if the constant is defined, false otherwise.
   */
  public function check()
  {
    return \defined('MODULE_ACTION_RECORDER_CONTACT_US_EMAIL_MINUTES');
  }

  /**
   * Installs the configuration settings related to the contact us email action recorder module.
   *
   * @return void
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Minimum Minutes Per E-Mail for contact us',
        'configuration_key' => 'MODULE_ACTION_RECORDER_CONTACT_US_EMAIL_MINUTES',
        'configuration_value' => '15',
        'configuration_description' => 'Minimum number of minutes to allow 1 e-mail to be sent (eg, 15 for 1 e-mail every 15 minutes)',
        'configuration_group_id' => '6',
        'sort_order' => '0',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Removes configuration entries from the database table where the configuration keys match
   * the keys returned by the keys() method.
   *
   * Executes a DELETE SQL statement to remove matching entries from the specified table.
   *
   * @return int The number of rows affected by the DELETE statement.
   */
  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  /**
   * Retrieves the configuration keys related to the module.
   *
   * @return array An array of configuration keys.
   */
  public function keys()
  {
    return array('MODULE_ACTION_RECORDER_CONTACT_US_EMAIL_MINUTES');
  }
}
