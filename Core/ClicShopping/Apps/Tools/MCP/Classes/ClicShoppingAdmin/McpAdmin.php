<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class McpAdmin
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
   * Adds a new MCP entry to the database along with associated IPs if provided.
   *
   * @param array $data An associative array containing the following keys:
   *                    - 'username' (string): The username for the MCP, which will be sanitized.
   *                    - 'mcp_key' (string): The MCP key, which will be sanitized.
   *                    - 'status' (int): The status of the MCP.
   *                    - 'mcp_ip' (array): Optional array of IP addresses associated with the MCP, which will be sanitized.
   * @return int Returns the ID of the newly created MCP entry.
   * @throws \InvalidArgumentException If required data is missing or invalid.
   */
  public function addMcp(array $data): int
  {
    // Validate required fields
    if (empty($data['username']) || empty($data['mcp_key'])) {
      throw new \InvalidArgumentException('Username and MCP key are required');
    }

    // Validate username format
    if (!preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $data['username'])) {
      throw new \InvalidArgumentException('Username must be 3-50 characters and contain only letters, numbers, underscore, and dash');
    }

    $sql_data_array = [
      'username' => HTML::sanitize($data['username']),
      'mcp_key' => HTML::sanitize($data['mcp_key']),
      'status' => (int) ($data['status'] ?? 0),
      'date_added' => 'now()',
      'date_modified' => 'now()'
    ];

    $this->db->save('mcp', $sql_data_array);

    $mcp_id = $this->db->lastInsertId();

    if (isset($data['mcp_ip']) && is_array($data['mcp_ip'])) {
      foreach ($data['mcp_ip'] as $ip) {
        if ($ip && $this->validateIpAddress($ip)) {
          $insert_data_array = [
            'mcp_id' => (int) $mcp_id,
            'ip' => HTML::sanitize($ip)
          ];

          $this->db->save('mcp_ip', $insert_data_array);
        }
      }
    }

    return $mcp_id;
  }

  /**
   * Edits an existing MCP entry in the database.
   *
   * @param int $mcp_id The unique identifier of the MCP to be edited.
   * @param array $data An associative array containing the following keys:
   *                    - username: (string) The sanitized username for the MCP.
   *                    - mcp_key: (string) The sanitized MCP key.
   *                    - status: (int) The status of the MCP (active/inactive).
   *                    - mcp_ip: (array) An array of sanitized IP addresses.
   * @return void
   * @throws \InvalidArgumentException If required data is missing or invalid.
   */
  public function editMcp(int $mcp_id, array $data): void
  {
    // Validate required fields
    if (empty($data['username']) || empty($data['mcp_key'])) {
      throw new \InvalidArgumentException('Username and MCP key are required');
    }

    // Validate username format
    if (!preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $data['username'])) {
      throw new \InvalidArgumentException('Username must be 3-50 characters and contain only letters, numbers, underscore, and dash');
    }

    // Check if username already exists (excluding current record)
    if ($this->usernameExists($data['username'], $mcp_id)) {
      throw new \InvalidArgumentException('Username already exists');
    }

    $sql_data_array = [
      'username' => HTML::sanitize($data['username']),
      'mcp_key' => HTML::sanitize($data['mcp_key']),
      'status' => (int) ($data['status'] ?? 0),
      'date_modified' => 'now()'
    ];

    $update_array_sql = [
      'mcp_id' => (int) $mcp_id
    ];

    $this->db->save('mcp', $sql_data_array, $update_array_sql);

    // Delete existing IPs
    $delete_sql_array = [
      'mcp_id' => (int) $mcp_id
    ];

    $this->db->delete('mcp_ip', $delete_sql_array);

    // Add new IPs with validation
    if (isset($data['mcp_ip']) && is_array($data['mcp_ip'])) {
      foreach ($data['mcp_ip'] as $ip) {
        if ($ip && $this->validateIpAddress($ip)) {
          $insert_data_array = [
            'mcp_id' => (int) $mcp_id,
            'ip' => HTML::sanitize($ip)
          ];

          $this->db->save('mcp_ip', $insert_data_array);
        }
      }
    }

    // Log the activity
    $this->logActivity($mcp_id, 'edit', 'MCP configuration updated');
  }

  /**
   * Deletes an MCP record from the database based on the provided MCP ID.
   *
   * @param int $mcp_id The ID of the MCP to be deleted.
   * @return void
   */
  public function deleteMcp(int $mcp_id): void
  {
    $delete_sql_array = [
      'mcp_id' => (int) $mcp_id
    ];

    $this->db->delete('mcp', $delete_sql_array);
  }

  /**
   * Retrieves MCP details based on the given MCP ID.
   *
   * @param int $mcp_id The unique identifier of the MCP whose details are to be fetched.
   *
   * @return array Returns an associative array containing the MCP details including mcp_id, username, mcp_key, status, date_added, and date_modified.
   */
  public function getMcp(int $mcp_id): array
  {
    $Qapi = $this->db->prepare('select mcp_id,
                                              username,
                                              mcp_key,
                                              status,
                                              date_added,
                                              date_modified
                                       from :table_mcp
                                       where mcp_id = :mcp_id
                                      ');
    $Qapi->bindInt(':mcp_id', $mcp_id);
    $Qapi->execute();

    return $Qapi->fetch();
  }

  /**
   *
   * @return array Returns an array of MCP details including mcp_id, username, mcp_key, status, date_added, and date_modified.
   */
  public function getAllMcp(): array
  {
    $Qapi = $this->db->prepare('select mcp_id,
                                              username,
                                              mcp_key,
                                              status,
                                              date_added,
                                              date_modified
                                       from :table_mcp
                                      ');

    $Qapi->execute();

    return $Qapi->fetchAll();
  }

  /**
   * Retrieves the total number of MCPs from the database.
   *
   * @return int The total count of MCPs as an integer.
   */
  public function getTotalMcps(): int
  {
    $Qapi = $this->db->prepare('select COUNT(*) as total
                                       from :table_mcp
                                      ');

    $Qapi->execute();

    return $Qapi->valueInt('total');
  }

  /**
   * Adds an IP address to the database associated with a specific MCP ID.
   *
   * @param int $mcp_id The ID of the MCP to associate with the IP address.
   * @param string $ip The IP address to add, sanitized before saving.
   * @return void
   */
  public function addIp(int $mcp_id, string $ip): void
  {
    $insert_sql_array = [
      'mcp_id' => (int) $mcp_id,
      'ip' => HTML::sanitize($ip)
    ];

    $this->db->save('mcp_ip', $insert_sql_array);
  }

  /**
   * Retrieves a list of IP addresses associated with the specified MCP ID.
   *
   * @param int $mcp_id The ID of the MCP for which to fetch associated IP addresses.
   *
   * @return array An array containing the IP addresses linked to the given MCP ID.
   */
  public function getIps(int $mcp_id): array
  {
    $ip_data = [];

    $Qapi = $this->db->prepare('select *
                                       from :table_mcp_ip
                                       where mcp_id = :mcp_id
                                      ');
    $Qapi->bindInt(':mcp_id', $mcp_id);
    $Qapi->execute();

    while ($result = $Qapi->fetch()) {
      $ip_data[] = $result['ip'];
    }

    return $ip_data;
  }

  /**
   * Adds a session to the database for the given MCP, session ID, and IP address.
   * If the IP address is not already associated with the MCP, it will be added.
   *
   * @param int $mcp_id Identifier of the MCP associated with the session.
   * @param string $session_id Unique session identifier to be added.
   * @param string $ip IP address associated with the session.
   *
   * @return int The ID of the newly created session record in the database.
   */
  public function addSession(int $mcp_id, string $session_id, string $ip): int
  {
    $Qapi = $this->db->prepare('select mcp_ip_id,
                                              mcp_id,
                                              ip
                                       from :table_mcp_ip
                                       where ip = :ip
                                      ');
    $Qapi->bindValue(':ip', $ip);
    $Qapi->execute();

    if (!$Qapi->fetch()) {
      $insert_sql_array = [
        'mcp_id' => (int) $mcp_id,
        'ip' => HTML::sanitize($ip)
      ];

      $this->db->save('mcp_ip', $insert_sql_array);
    }

    $insert_sql_array = [
      'mcp_id' => (int) $mcp_id,
      'session_id' => HTML::sanitize($session_id),
      'ip' => HTML::sanitize($ip),
      'date_added' => 'now()',
      'date_modified' => 'now()'
    ];

    $this->db->save('mcp_session', $insert_sql_array);

    return $this->db->lastInsertId();
  }

  /**
   * Retrieves all sessions associated with a specific MCP ID.
   *
   * @param int $mcp_id The ID of the MCP for which sessions should be retrieved.
   * @return array An array containing details of all sessions associated with the given MCP ID.
   */
  public function getSessions(int $mcp_id): array
  {
    $Qapi = $this->db->prepare('select mcp_session_id,
                                              mcp_id,
                                              session_id,
                                              ip,
                                              date_added,
                                              date_modified
                                       from :table_mcp_session
                                       where mcp_id = :mcp_id
                                      ');
    $Qapi->bindInt(':mcp_id', $mcp_id);
    $Qapi->execute();

    $result = $Qapi->fetchAll();

    return $result;
  }

  /**
   * Deletes a session from the database based on the given session ID.
   *
   * @param int $mcp_session_id The ID of the session to be deleted.
   * @return void
   */
  public function deleteSession(int $mcp_session_id): void
  {
    $delete_sql_array = [
      'mcp_session_id' => $mcp_session_id
    ];

    $this->db->delete('mcp_session', $delete_sql_array);
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

    $this->db->delete('mcp_session', $delete_sql_array);
  }

  /**
   * Validates an IP address format.
   *
   * @param string $ip The IP address to validate.
   * @return bool True if valid, false otherwise.
   */
  private function validateIpAddress(string $ip): bool
  {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
  }

  /**
   * Validates MCP key format and strength.
   *
   * @param string $key The MCP key to validate.
   * @return bool True if valid, false otherwise.
   */
  private function validateMcpKey(string $key): bool
  {
    // MCP key should be at least 32 characters long and contain alphanumeric characters
    return strlen($key) >= 32 && preg_match('/^[a-zA-Z0-9+\/=]+$/', $key);
  }

  /**
   * Checks if a username already exists in the database.
   *
   * @param string $username The username to check.
   * @param int|null $excludeId Optional MCP ID to exclude from the check (for updates).
   * @return bool True if username exists, false otherwise.
   */
  public function usernameExists(string $username, ?int $excludeId = null): bool
  {
    $sql = 'SELECT COUNT(*) as count FROM :table_mcp WHERE username = :username';
    $params = [':username' => $username];

    if ($excludeId !== null) {
      $sql .= ' AND mcp_id != :exclude_id';
      $params[':exclude_id'] = $excludeId;
    }

    $Qcheck = $this->db->prepare($sql);
    foreach ($params as $key => $value) {
      $Qcheck->bindValue($key, $value);
    }
    $Qcheck->execute();

    return $Qcheck->valueInt('count') > 0;
  }

  /**
   * Generates a secure MCP key.
   *
   * @return string A cryptographically secure MCP key.
   */
  public function generateSecureMcpKey(): string
  {
    return base64_encode(random_bytes(48)); // 64 character base64 string
  }

  /**
   * Logs MCP activity for security auditing.
   *
   * @param int $mcpId The MCP ID.
   * @param string $action The action performed.
   * @param string $details Additional details.
   * @return void
   */
  private function logActivity(int $mcpId, string $action, string $details = ''): void
  {
    $logData = [
      'mcp_id' => $mcpId,
      'action' => $action,
      'details' => $details,
      'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
      'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
      'timestamp' => date('Y-m-d H:i:s')
    ];

    // In a production environment, you would save this to a dedicated audit log table
    error_log('MCP Activity: ' . json_encode($logData));
  }
}