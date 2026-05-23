<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Session;

/**
 * Class Memcached
 *
 * Implements session storage using Memcached as the backend.
 * This class provides methods to open, close, read, write, destroy, and garbage collect sessions
 * using the Memcached PHP extension. It also allows checking for Memcached availability and
 * verifying the existence of a session.
 *
 *  Persistent session identifier for Memcached
 *  Kept separate from general cache identifier (clicshopping_session_memcached)
 * @package ClicShopping\OM\Session
 */

class Memcached extends \ClicShopping\OM\Domains\SessionAbstract implements \SessionHandlerInterface
{
  private const PERSISTENT_ID = 'clicshopping_session_memcached';
  private ?\Memcached $_conn = null;
  private string $orig_module_name;
  private int $_life_time;

  /**
   * Memcached session handler constructor.
   *
   * Registers this handler with PHP's session system if the Memcached extension is available.
   *
   * @param string|null $name Optional session name.
   */
  public function __construct(?string $name = null)
  {
    parent::__construct($name);

    $this->_life_time = (int)ini_get('session.gc_maxlifetime'); // session timeout

    if (class_exists('Memcached')) {
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
   * Checks if the Memcached extension is available and a server is reachable.
   *
   * @return bool True if Memcached is available and operational, false otherwise.
   */
  public function check(): bool
  {
    if (!extension_loaded('memcached')) {
      return false;
    }

    if (!class_exists('Memcached')) {
      return false;
    }

    try {
      $memcached = new \Memcached(self::PERSISTENT_ID);
      $memcached->setOptions([
        \Memcached::OPT_COMPRESSION => true,
        \Memcached::OPT_LIBKETAMA_COMPATIBLE => true,
        \Memcached::OPT_BINARY_PROTOCOL => true,
        \Memcached::OPT_TCP_NODELAY => true,
        \Memcached::OPT_CONNECT_TIMEOUT => 1000,
        \Memcached::OPT_RETRY_TIMEOUT => 2,
        \Memcached::OPT_DISTRIBUTION => \Memcached::DISTRIBUTION_CONSISTENT
      ]);

      if (defined('USE_MEMCACHED') && USE_MEMCACHED == 'False') {
        $memcached->flush();
        $memcached->resetServerList();
        $memcached->quit();

        return false;
      }

      if (count($memcached->getServerList()) === 0) {
        $memcached->addServer('localhost', 11211);
      }

      // Test connection
      $stats = $memcached->getStats();
      return !empty($stats) && $memcached->getResultCode() === \Memcached::RES_SUCCESS;

    } catch (\Exception $e) {
      return false;
    }
  }

  /**
   * Opens a Memcached session.
   *
   * @param string $save_path The path where to store/retrieve the session.
   * @param string $name The session name.
   * @return bool True on success, false on failure.
   */
  public function open(string $save_path, string $name): bool
  {
    try {
      $this->_conn = new \Memcached(self::PERSISTENT_ID);

      if (count($this->_conn->getServerList()) === 0) {
        if (!$this->_conn->addServer('localhost', 11211)) {
          throw new \Exception('Could not add Memcached server');
        }

        $this->_conn->setOptions(
          [
          \Memcached::OPT_COMPRESSION => true,
          \Memcached::OPT_LIBKETAMA_COMPATIBLE => true,
          \Memcached::OPT_BINARY_PROTOCOL => true,
          \Memcached::OPT_TCP_NODELAY => true,
          \Memcached::OPT_CONNECT_TIMEOUT => 1000, // 1 second
          \Memcached::OPT_RETRY_TIMEOUT => 2,
          \Memcached::OPT_DISTRIBUTION => \Memcached::DISTRIBUTION_CONSISTENT
          ]
        );
      }

      return true;
    } catch (\Exception $e) {
      session_module_name($this->orig_module_name);
      return false;
    }
  }

  /**
   * Closes the Memcached session.
   *
   * @return bool True on success, false on failure.
   */
  public function close(): bool
  {
    if (!$this->_conn instanceof \Memcached) {
      return true;
    }

    return !method_exists($this->_conn, 'quit') || $this->_conn->quit();
  }

  /**
   * Reads session data from Memcached.
   *
   * @param string $session_id The session id.
   * @return string|false The session data or false on failure.
   */
  public function read(string $session_id): string|false
  {
    if (!$this->_conn instanceof \Memcached) {
      return false;
    }

    $id = 'sess_' . $session_id;
    $data = $this->_conn->get($id);

    if ($this->_conn->getResultCode() === \Memcached::RES_SUCCESS) {
      return is_string($data) ? $data : '';
    }

    return '';
  }

  /**
   * Writes session data to Memcached.
   *
   * @param string $session_id The session id.
   * @param string $session_data The session data.
   * @return bool True on success, false on failure.
   */
  public function write(string $session_id, string $session_data): bool
  {
    if (!$this->_conn instanceof \Memcached) {
      return false;
    }

    $id = 'sess_' . $session_id;
    return $this->_conn->set($id, $session_data, $this->_life_time);
  }

  /**
   * Destroys a session in Memcached.
   *
   * @param string $session_id The session id.
   * @return bool True on success, false on failure.
   */
  public function destroy(string $session_id): bool
  {
    if (!$this->_conn instanceof \Memcached) {
      return false;
    }

    $id = 'sess_' . $session_id;
    return $this->_conn->delete($id);
  }

  /**
   * Cleans up old sessions (no-op for Memcached).
   *
   * @param int $maxlifetime Sessions that have not updated for the last maxlifetime seconds will be removed.
   * @return bool Always returns true.
   */
  public function gc(int $maxlifetime): bool
  {
    return true;
  }

  /**
   * Checks if a session exists in Memcached.
   *
   * @param string $session_id The session id.
   * @return bool True if the session exists, false otherwise.
   */
  public function exists(string $session_id): bool
  {
    if (!$this->_conn instanceof \Memcached) {
      return false;
    }

    $id = 'sess_' . $session_id;
    $this->_conn->get($id);
    return $this->_conn->getResultCode() === \Memcached::RES_SUCCESS;
  }
}