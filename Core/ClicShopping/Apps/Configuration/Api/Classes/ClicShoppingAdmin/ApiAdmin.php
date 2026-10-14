<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Api\Classes\ClicShoppingAdmin;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class ApiAdmin
{
  private mixed $db;

  /**
   * Constructor method for initializing the object with database connection.
   *
   * @return void
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
  }

  /**
   * Adds a new API entry to the database along with associated IPs if provided.
   *
   * @param array $data An associative array containing the following keys:
   *                    - 'username' (string): The username for the API, which will be sanitized.
   *                    - 'api_key' (string): The API key, which will be sanitized.
   *                    - 'status' (int): The status of the API.
   *                    - 'api_ip' (array): Optional array of IP addresses associated with the API, which will be sanitized.
   * @return int Returns the ID of the newly created API entry.
   */
  public function addApi(array $data): int
  {
    $sql_data_array = [
      'username' => HTML::sanitize($data['username']),
      'api_key' => HTML::sanitize($data['api_key']),
      'status' => (int)$data['status'],
      'date_added' => 'now()',
      'date_modified' => 'now()'
    ];

    $this->db->save('api', $sql_data_array);

    $api_id = $this->db->lastInsertId();

    if (isset($data['api_ip'])) {
      foreach ($data['api_ip'] as $ip) {
        if ($ip) {
          $insert_data_array = [
            'api_id' => (int)$api_id,
            'ip' => HTML::sanitize($ip)
          ];

          $this->db->save('api_ip', $insert_data_array);
        }
      }
    }

    return $api_id;
  }

  /**
   * Edits an existing API entry in the database.
   *
   * @param int $api_id The unique identifier of the API to be edited.
   * @param array $data An associative array containing the following keys:
   *                    - username: (string) The sanitized username for the API.
   *                    - api_key: (string) The sanitized API key.
   *                    - status: (int) The status of the API (active/inactive).
   *                    - api_ip: (array) An array of sanitized IP addresses.
   * @return void
   */
  public function editApi(int $api_id, array $data): void
  {
    $sql_data_array = [
      'username' => HTML::sanitize($data['username']),
      'api_key' => HTML::sanitize($data['api_key']),
      'status' => (int)$data['status'],
      'date_modified' => 'now()'
    ];

    $update_array_sql = [
      'api_id' => (int)$api_id
    ];

    $this->db->save('api', $sql_data_array, $update_array_sql);

    $delete_sql_array = [
      'api_id' => (int)$api_id
    ];

    $this->db->delete('api_ip', $delete_sql_array);


    if (isset($data['api_ip'])) {
      foreach ($data['api_ip'] as $ip) {
        if ($ip) {
          $insert_data_array = [
            'api_id' => (int)$api_id,
            'ip' => HTML::sanitize($ip)
          ];

          $this->db->save('api_id', $insert_data_array);
        }
      }
    }
  }

  /**
   * Deletes an API record from the database based on the provided API ID.
   *
   * @param int $api_id The ID of the API to be deleted.
   * @return void
   */
  public function deleteApi(int $api_id): void
  {
    $delete_sql_array = [
      'api_id' => (int)$api_id
    ];

    $this->db->delete('api', $delete_sql_array);
  }

  /**
   * Retrieves API details based on the given API ID.
   *
   * @param int $api_id The unique identifier of the API whose details are to be fetched.
   *
   * @return array Returns an associative array containing the API details including api_id, username, api_key, status, date_added, and date_modified.
   */
  public function getApi(int $api_id): array
  {
    $Qapi = $this->app->db->prepare('select api_id,
                                              username,
                                              api_key,
                                              status,
                                              date_added,
                                              date_modified
                                       from :table_api
                                       where api_id = :api_id
                                      ');
    $Qapi->bindint(':api_id', $api_id);
    $Qapi->execute();

    return $Qapi->fetchAll();
  }

  /**
   *
   * @return array Returns an array of API details including api_id, username, api_key, status, date_added, and date_modified.
   */
  public function getAllApi(): array
  {
    $Qapi = $this->app->db->prepare('select api_id,
                                              username,
                                              api_key,
                                              status,
                                              date_added,
                                              date_modified
                                       from :table_api
                                       where api_id = :api_id
                                      ');

    $Qapi->execute();

    return $Qapi->fetchAll();
  }

  /**
   * Retrieves the total number of APIs from the database.
   *
   * @return int The total count of APIs as an integer.
   */
  public function getTotalApis(): int
  {
    $Qapi = $this->app->db->prepare('select COUNT(*) as total
                                       from :table_api
                                      ');

    $Qapi->execute();

    return $Qapi->valueInt('total');
  }

  /**
   * Adds an IP address to the database associated with a specific API ID.
   *
   * @param int $api_id The ID of the API to associate with the IP address.
   * @param string $ip The IP address to add, sanitized before saving.
   * @return void
   */
  public function addIp(int $api_id, string $ip): void
  {
    $insert_sql_array = [
      'api_id' => (int)$api_id,
      'ip' => HTML::sanitize($ip)
    ];

    $this->db->save('api_ip', $insert_sql_array);
  }

  /**
   * Retrieves a list of IP addresses associated with the specified API ID.
   *
   * @param int $api_id The ID of the API for which to fetch associated IP addresses.
   *
   * @return array An array containing the IP addresses linked to the given API ID.
   */
  public function getIps(int $api_id): array
  {
    $ip_data = [];

    $Qapi = $this->app->db->prepare('select *
                                       from :table_api_ip
                                       where :api_id = :api_id
                                      ');
    $Qapi->bindint(':api_id', $api_id);

    $Qapi->execute();

    foreach ($Qapi->fetch() as $result) {
      $ip_data[] = $result['ip'];
    }

    return $ip_data;
  }

  /**
   * Adds a session to the database for the given API, session ID, and IP address.
   * If the IP address is not already associated with the API, it will be added.
   *
   * @param int $api_id Identifier of the API associated with the session.
   * @param string $session_id Unique session identifier to be added.
   * @param string $ip IP address associated with the session.
   *
   * @return int The ID of the newly created session record in the database.
   */
  public function addSession(int $api_id, string $session_id, string $ip): int
  {
    $Qapi = $this->app->db->prepare('select api_ip_id,
                                              api_id,
                                              ip
                                       from :table_api_ip
                                       where ip = :ip
                                      ');
    $Qapi->bindint(':ip', $ip);
    $Qapi->execute();

    if (!$Qapi->fetch()) {
      $insert_sql_array = [
        'api_id' => (int)$api_id,
        'ip' => HTML::sanitize($ip)
      ];

      $this->db->save('api_ip', $insert_sql_array);
    }

    $insert_sql_array = [
      'api_id' => (int)$api_id,
      'session_id' => HTML::sanitize($session_id),
      'ip' => HTML::sanitize($ip),
      'date_added' => 'now()',
      'date_modified' => 'now()'
    ];

    $this->db->save('api_session', $insert_sql_array);

    return $this->db->lastInsertId();
  }

  /**
   * Retrieves all sessions associated with a specific API ID.
   *
   * @param int $api_id The ID of the API for which sessions should be retrieved.
   * @return array An array containing details of all sessions associated with the given API ID.
   */
  public function getSessions(int $api_id): array
  {
    $Qapi = $this->app->db->prepare('select api_session_id,
                                              api_id,
                                              session_id,
                                              ip,
                                              date_added,
                                              date_modified
                                       from :table_api_session
                                       where api_id = :api_id
                                      ');
    $Qapi->bindint(':api_id', $api_id);
    $Qapi->execute();

    $result = $Qapi->fetchAll();

    return $result;
  }

  /**
   * Deletes a session from the database based on the given session ID.
   *
   * @param int $api_session_id The ID of the session to be deleted.
   * @return void
   */
  public function deleteSession(int $api_session_id): void
  {
    $delete_sql_array = [
      'api_session_id' => $api_session_id
    ];

    $this->db->delete('api_session', $delete_sql_array);
  }

  /**
   * Deletes a session record from the database based on the provided session ID.
   *
   * @param string $session_id The session ID to identify the session record to be deleted.
   * @return void
   */
  public function deleteSessionBySessionId(string $session_id): void
  {
    $delete_sql_array = [
      'session_id' => HTML::sanitize($session_id)
    ];

    $this->db->delete('api_session', $delete_sql_array);
  }
}