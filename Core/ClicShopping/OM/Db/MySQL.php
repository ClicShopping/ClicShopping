<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\OM\Db;

use PDO;
use ClicShopping\Apps\Configuration\Cache\Classes\ClicShoppingAdmin\CacheAdmin;

/**
 * Represents a MySQL database connection and driver with specific implementations
 * and options for connecting to a MySQL database using PDO.
 *
 * Extends the ClicShopping\OM\Db class to provide MySQL-specific functionality and behavior.
 */

class MySQL extends \ClicShopping\OM\Db
{
  protected bool $connected;
  protected string $table_prefix;
  protected bool $use_memcached = false;
  protected $memcached = null;
  protected string $memcached_prefix = 'db_';

  /**
   * Constructor method for initializing database connection parameters.
   *
   * @param string $server The database server hostname or IP address.
   * @param string $username The username for database authentication.
   * @param string $password The password for database authentication.
   * @param string $database The name of the database to connect to.
   * @param int $port The port number for the database connection.
   * @param array $driver_options An array of driver-specific options.
   * @param array $options Additional options for the database connection.
   *
   * @return void
   */
  public function __construct(string $server, string $username, string $password, string $database, int|null $port, array|null $driver_options, array|null $options)
  {
    $this->server = $server;
    $this->username = $username;
    $this->password = $password;
    $this->database = $database;
    $this->port = $port;
    $this->driver_options = $driver_options;
    $this->options = $options;

    // Initialize Memcached if available and enabled
    if (defined('USE_MEMCACHED') && USE_MEMCACHED == 'True') {
      $this->initMemcached();
    }

    if (!isset($this->driver_options[\PDO::MYSQL_ATTR_INIT_COMMAND])) {
      $this->driver_options[\PDO::MYSQL_ATTR_INIT_COMMAND] = 'set session sql_mode="STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION"';
    }

    $this->establishConnection();
  }

  /**
   * Establishes a connection to the database using the provided configuration settings.
   * Separate name to avoid any potential conflicts with PDO internals.
   */
  public function establishConnection(): void
  {
    $dsn_array = [];

    if (!empty($this->database)) {
      $dsn_array[] = 'dbname=' . $this->database;
    }

    if ((str_contains($this->server, '/')) || (str_contains($this->server, '\\'))) {
      $dsn_array[] = 'unix_socket=' . $this->server;
    } else {
      $dsn_array[] = 'host=' . $this->server;

      if (!empty($this->port)) {
        $dsn_array[] = 'port=' . $this->port;
      }
    }

    $dsn_array[] = 'charset=utf8mb4';

    $dsn = 'mysql:' . implode(';', $dsn_array);

    $this->connected = true;

    parent::__construct($dsn, $this->username, $this->password, $this->driver_options);
  }

  /**
   * Initialize Memcached connection
   */
  protected function initMemcached(): void
  {
    if (class_exists('Memcached')) {
      try {
        $this->memcached = CacheAdmin::getMemcached();
        if ($this->memcached !== false) {
          $this->use_memcached = true;
        }
      } catch (\Exception $e) {
        $this->use_memcached = false;
        $this->memcached = null;
      }
    }
  }

  /**
   * Get cached query result
   *
   * @param string $query SQL query
   * @param string $cache_name Cache identifier
   * @return array|false Cached result or false if not found
   */
  protected function getCache(string $query, string $cache_name): array|false
  {
    if (!$this->use_memcached || !$this->memcached) {
      return false;
    }

    $cache_key = $this->memcached_prefix . md5($query . $cache_name);
    $result = $this->memcached->get($cache_key);

    if ($this->memcached->getResultCode() === \Memcached::RES_SUCCESS) {
      return $result;
    }

    return false;
  }

  /**
   * Save query result to cache
   *
   * @param string $query SQL query
   * @param string $cache_name Cache identifier
   * @param array $data Data to cache
   * @param int $ttl Cache TTL in seconds
   * @return bool Success or failure
   */
  protected function saveCache(string $query, string $cache_name, array $data, int $ttl = 3600): bool
  {
    if (!$this->use_memcached || !$this->memcached) {
      return false;
    }

    $cache_key = $this->memcached_prefix . md5($query . $cache_name);
    return $this->memcached->set($cache_key, $data, $ttl);
  }

  /**
   * Delete cached query result
   *
   * @param string $cache_name Cache identifier
   * @return bool Success or failure
   */
  public function deleteCache(string $cache_name): bool
  {
    if (!$this->use_memcached || !$this->memcached) {
      return false;
    }

    // On peut utiliser un wildcard pour supprimer tous les caches liés à ce nom
    $cache_key = $this->memcached_prefix . '*' . $cache_name . '*';
    return $this->memcached->delete($cache_key);
  }
}
