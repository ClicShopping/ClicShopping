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
 * Class ar_tell_a_friend
 *
 * Handles the operations related to the tell-a-friend action recorder module.
 * This class monitors user activities around sending "tell-a-friend" emails and enforces restrictions based on specific configurations.
 */
class ar_tell_a_friend
{
  public string $code;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public $minutes = 15;
  public $attempts = 1;
  public $identifier;
  public $enabled = true;
  public $group;

  /**
   * Constructor method for initializing the class properties.
   *
   * @return void
   */
  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);

    $this->title = CLICSHOPPING::getDef('module_action_recorder_tell_a_friend_title');
    $this->description = CLICSHOPPING::getDef('module_action_recorder_tell_a_friend_description');

    if ($this->check()) {
      if (\defined('MODULE_ACTION_RECORDER_TELL_A_FRIEND_EMAIL_MINUTES')) {
        $this->minutes = (int)MODULE_ACTION_RECORDER_TELL_A_FRIEND_EMAIL_MINUTES;
        $this->attempts = 3; // nbr de possiblite d'envoi d'email
      }
    }
  }

  /**
   * Sets the identifier property to the current IP address obtained via HTTP request.
   *
   * @return void
   */
  public function setIdentifier()
  {
    $this->identifier = HTTP::getIpAddress();
  }

  /**
   * Determines if an action can be performed based on user ID, identifier, and defined time limits.
   *
   * @param int|null $user_id The ID of the user attempting the action. Can be null.
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

    $sql_query .= ' and date_added >= date_sub(now(),
                      interval :limit_minutes minute)
                      and success = 1
                      limit 1
                    ';

    $Qcheck = $CLICSHOPPING_Db->prepare($sql_query);
    $Qcheck->bindValue(':module', $this->code);

    if (!empty($user_id)) {
      $Qcheck->bindInt(':user_id', $user_id);
    }

    $Qcheck->bindValue(':identifier', $this->identifier);
    $Qcheck->bindInt(':limit_minutes', $this->minutes);
    $Qcheck->execute();

    if ($Qcheck->fetch() !== false) {
      return false;
    }

    return true;
  }

  /**
   * Deletes expired entries from the action recorder table based on the module code and a time interval.
   * The time interval is determined by the value of the "minutes" property.
   *
   * @return int The number of rows deleted from the action recorder table.
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
   * Checks if the constant 'MODULE_ACTION_RECORDER_TELL_A_FRIEND_EMAIL_MINUTES' is defined.
   *
   * @return bool Returns true if the constant is defined, otherwise false.
   */
  public function check()
  {
    return \defined('MODULE_ACTION_RECORDER_TELL_A_FRIEND_EMAIL_MINUTES');
  }

  /**
   * Installs the configuration settings for the "tell a friend" email action recorder module.
   *
   * @return void
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Minimum Minutes Per E-Mail for tell a friend',
        'configuration_key' => 'MODULE_ACTION_RECORDER_TELL_A_FRIEND_EMAIL_MINUTES',
        'configuration_value' => '15',
        'configuration_description' => 'Minimum number of minutes to allow 1 e-mail to be sent (eg, 15 for 1 e-mail every 15 minutes)',
        'configuration_group_id' => '6',
        'sort_order' => '0',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Removes entries from the configuration table in the database where the configuration_key matches
   * the keys provided by the `keys` method.
   *
   * @return bool|int Returns the number of affected rows on success, or false on failure.
   */
  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  /**
   * Retrieves an array of configuration keys related to the Tell A Friend email action recorder module.
   *
   * @return array An array containing the configuration keys.
   */
  public function keys()
  {
    return array('MODULE_ACTION_RECORDER_TELL_A_FRIEND_EMAIL_MINUTES');
  }
}
