<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\OM\Session;

/**
 * Class Redis
 *
 * Implements session storage using Redis as the backend.
 * This class provides methods to open, close, read, write, destroy, and garbage collect sessions
 * using the phpredis PHP extension. It also allows checking for Redis availability and
 * verifying the existence of a session.
 *
 * @package ClicShopping\OM\Session
 */
class Redis extends \ClicShopping\OM\Domains\SessionAbstract implements \SessionHandlerInterface
{
  private ?\Redis $_conn = null;
  private string $orig_module_name;
  private int $_life_time;

  /**
   * Redis session handler constructor.
   *
   * Registers this handler with PHP's session system if the Redis extension is available.
   *
   * @param string|null $name Optional session name.
   */
  public function __construct(?string $name = null)
  {
    parent::__construct($name);

    $this->_life_time = (int)ini_get('session.gc_maxlifetime'); // session timeout

    if (class_exists('\Redis')) {
      $this->orig_module_name = session_module_name();

      session_set_save_handler(
        $this->open(...),
        $this->close(...),
        $this->read(...),
        $this->write(...),
        $this->destroy(...),
        $this->gc(...)
      );
    }
  }

  /**
   * Checks if the Redis extension is available and a server is reachable.
   *
   * @return bool True if Redis is available and operational, false otherwise.
   */
  public function check(): bool
  {
    if (!extension_loaded('redis')) {
      return false;
    }

    if (!class_exists('\Redis')) {
      return false;
    }

    try {
      $redis = new \Redis();
      $redis->connect('localhost', 6379, 1);

      if (defined('USE_REDIS') && USE_REDIS == 'False') {
        $redis->flushdb();
        $redis->close();

        return false;
      }

      $ping = $redis->ping();

      return $ping === true || strtoupper((string)$ping) === '+PONG' || strtoupper((string)$ping) === 'PONG';
    } catch (\Exception $e) {
      return false;
    }
  }

  /**
   * Opens a Redis session.
   *
   * @param string $save_path The path where to store/retrieve the session.
   * @param string $name The session name.
   * @return bool True on success, false on failure.
   */
  public function open(string $save_path, string $name): bool
  {
    try {
      $this->_conn = new \Redis();
      $this->_conn->connect('localhost', 6379, 1);
      return true;
    } catch (\Exception $e) {
      session_module_name($this->orig_module_name);
      return false;
    }
  }

  /**
   * Closes the Redis session.
   *
   * @return bool True on success, false on failure.
   */
  public function close(): bool
  {
    if (!$this->_conn instanceof \Redis) {
      return true;
    }

    return $this->_conn->close();
  }

  /**
   * Reads session data from Redis.
   *
   * @param string $session_id The session id.
   * @return string|false The session data or false on failure.
   */
  public function read(string $session_id): string|false
  {
    if (!$this->_conn instanceof \Redis) {
      return false;
    }

    $id = 'sess_' . $session_id;
    $data = $this->_conn->get($id);

    return is_string($data) ? $data : '';
  }

  /**
   * Writes session data to Redis.
   *
   * @param string $session_id The session id.
   * @param string $session_data The session data.
   * @return bool True on success, false on failure.
   */
  public function write(string $session_id, string $session_data): bool
  {
    if (!$this->_conn instanceof \Redis) {
      return false;
    }

    $id = 'sess_' . $session_id;
    return $this->_conn->setex($id, $this->_life_time, $session_data);
  }

  /**
   * Destroys a session in Redis.
   *
   * @param string $session_id The session id.
   * @return bool True on success, false on failure.
   */
  public function destroy(string $session_id): bool
  {
    if (!$this->_conn instanceof \Redis) {
      return false;
    }

    $id = 'sess_' . $session_id;
    return (bool)$this->_conn->del($id);
  }

  /**
   * Cleans up old sessions (no-op for Redis).
   *
   * @param int $maxlifetime Sessions that have not updated for the last maxlifetime seconds will be removed.
   * @return bool Always returns true.
   */
  public function gc(int $maxlifetime): bool
  {
    return true;
  }

  /**
   * Checks if a session exists in Redis.
   *
   * @param string $session_id The session id.
   * @return bool True if the session exists, false otherwise.
   */
  public function exists(string $session_id): bool
  {
    if (!$this->_conn instanceof \Redis) {
      return false;
    }

    $id = 'sess_' . $session_id;
    return (bool)$this->_conn->exists($id);
  }
}


