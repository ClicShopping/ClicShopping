<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

use  ClicShopping\Apps\Configuration\Cache\Classes\ClicShoppingAdmin\CacheAdmin;

use ArrayIterator;
use CachingIterator;
use Exception;
use PDO;
use PDOStatement;
use function call_user_func_array;
use function count;
use function func_get_args;
use function is_array;
use function is_null;
use function is_string;
use function strlen;

/**
 * ClicShopping\OM\Db is a class extending PDO to manage database interactions
 * with additional features for query preparation, execution, and table prefixing.
 */
class Db extends PDO
{
  protected bool $connected = false;
  protected string $server;
  protected string $username;
  protected string $password;
  protected string $database;
  protected string $table_prefix;
  protected int|null $port;
  protected array|null $driver_options = [];
  protected array|null $options = [];
  protected $cache_key;
  protected bool $use_cache = false;
  protected int $cache_expire = 3600; // 1 hour default
  protected mixed $statement;
  protected mixed $query;

  // Ne pas définir le type ici pour éviter le conflit avec la classe enfant
  protected $memcached;

  /**
   * Set the cache name for the query
   *
   * @param string $cache_name The name to identify this cache
   * @param int|null $expire Cache expiration time in seconds
   * @return $this
   */
  public function setCache(string $cache_name, ?int $expire = null): static
  {
    $this->cache_key = $cache_name;
    $this->use_cache = true;

    if ($expire !== null) {
      $this->cache_expire = $expire;
    }

    return $this;
  }

  /**
   * Execute the prepared statement
   */
  public function execute()
  {
    if ($this->use_cache && method_exists($this, 'getCache')) {
      // Try to get from cache first
      $cached_result = $this->getCache($this->query, $this->cache_key);
      if ($cached_result !== false) {
        $this->statement = new \ArrayObject($cached_result);
        return true;
      }
    }

    $result = parent::execute();

    // If caching is enabled and execution was successful, cache the results
    if ($result && $this->use_cache && method_exists($this, 'saveCache')) {
      $data = [];
      while ($row = $this->fetch()) {
        $data[] = $row;
      }
      $this->saveCache($this->query, $this->cache_key, $data, $this->cache_expire);

      // Reset the statement to the beginning
      $this->statement = new \ArrayObject($data);
    }

    return $result;
  }

  /**
   * Initializes a database connection.
   *
   * @param string|null $server The database server address. Defaults to the application configuration if not provided.
   * @param string|null $username The database username. Defaults to the application configuration if not provided.
   * @param string|null $password The database user password. Defaults to
   */
  public static function initialize(
    $server = null,
    $username = null,
    $password = null,
    $database = null,
    $port = null,
    ?array $driver_options = null,
    ?array $options = null
  )
  {
    if (!isset($server)) {
      $server = CLICSHOPPING::getConfig('db_server');
    }

    if (!isset($username) && CLICSHOPPING::configExists('db_server_username')) {
      $username = CLICSHOPPING::getConfig('db_server_username');
    }

    if (!isset($password) && CLICSHOPPING::configExists('db_server_password')) {
      $password = CLICSHOPPING::getConfig('db_server_password');
    }

    if (!isset($database) && CLICSHOPPING::configExists('db_database')) {
      $database = CLICSHOPPING::getConfig('db_database');
    }

    if (!is_array($driver_options)) {
      $driver_options = [];
    }

    if (!isset($driver_options[PDO::ATTR_PERSISTENT]) && CLICSHOPPING::configExists('db_server_persistent_connections')) {
      if (CLICSHOPPING::getConfig('db_server_persistent_connections') === 'true') {
        $driver_options[PDO::ATTR_PERSISTENT] = true;
      }
    }

    if (!isset($driver_options[PDO::ATTR_ERRMODE])) {
      $driver_options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
    }

    if (!isset($driver_options[PDO::ATTR_DEFAULT_FETCH_MODE])) {
      $driver_options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;
    }

    if (!isset($driver_options[PDO::ATTR_STATEMENT_CLASS])) {
      $driver_options[PDO::ATTR_STATEMENT_CLASS] = ['ClicShopping\OM\DbStatement'];
    }

    if (!is_array($options)) {
      $options = [];
    }

    $object = false;

    try {
      $class = 'ClicShopping\OM\Db\MySQL';
      $object = new $class($server, $username, $password, $database, $port, $driver_options, $options);

      if (defined('USE_MEMCACHED')) {
        if (USE_MEMCACHED === 'True') {
          $object->initMemcachedConnection();
        } else {
          if (property_exists($object, 'memcached') && $object->memcached instanceof \Memcached) {
            $object->memcached->flush();
            $object->memcached->resetServerList();
            $object->memcached->quit();
          }
        }
      }

    } catch (Exception $e) {
      $message = $e->getMessage();
        // Uncomment this line if you want to log the stack trace
//      $message .= "\n" . $e->getTraceAsString(); // the trace will contain the password in plain text

      if (!isset($options['log_errors']) || ($options['log_errors'] === true)) {
        error_log('ClicShopping\OM\Db::initialize(): ' . $message);
      }

      throw new Exception($message, $e->getCode());
    }

    return $object;
  }

  /**
   * Initialize Memcached connection using CacheAdmin
   */
  protected function initMemcachedConnection(): void
  {
    try {
      $this->memcached = CacheAdmin::getMemcached();
    } catch (\Exception $e) {
      $this->memcached = null;
    }
  }

  /**
   * Get data from cache
   * @param string $query The SQL query
   * @return array|false
   */
  protected function getFromCache(string $query): array|false
  {
    if (!$this->memcached || !$this->use_cache) {
      return false;
    }

    $cache_id = 'db_' . md5($query . $this->cache_key);
    $result = $this->memcached->get($cache_id);

    if ($this->memcached->getResultCode() === \Memcached::RES_SUCCESS) {
      return $result;
    }

    return false;
  }

  /**
   * Save data to cache
   * @param string $query The SQL query
   * @param array $data Data to cache
   * @return bool
   */
  protected function saveToCache(string $query, array $data): bool
  {
    if (!$this->memcached || !$this->use_cache) {
      return false;
    }

    $cache_id = 'db_' . md5($query . $this->cache_key);
    
    return $this->memcached->set($cache_id, $data, $this->cache_expire);
  }

  /**
   * Executes an SQL statement and returns the number of affected rows or false on failure.
   *
   * @param string $statement The SQL statement to execute. The statement should not return a result set.
   *
   * @return int|false Returns the number of rows that were affected by the executed statement,
   *                   or false if the execution fails.
   */
  public function exec(string $statement): int|false
  {
    $statement = $this->autoPrefixTables($statement);

    return parent::exec($statement);
  }

  /**
   * Prepares an SQL statement for execution, with optional driver options.
   *
   * @param string $statement The SQL statement to prepare, with placeholders for bound parameters if applicable.
   * @param array|null $driver_options An optional array of driver-specific options to set for the statement.
   * @return PDOStatement|false Returns a PDOStatement object if the preparation is successful, or false on failure.
   */
  public function prepare(string $statement, ?array $driver_options = null): PDOStatement|false
  {
    $statement = $this->autoPrefixTables($statement);

    $DbStatement = parent::prepare($statement, is_array($driver_options) ? $driver_options : []);
    $DbStatement->setQueryCall('prepare');
    $DbStatement->setPDO($this);

    return $DbStatement;
  }

  /**
   * Executes a SQL query and returns the prepared statement.
   *
   * @param string $statement The SQL query to execute.
   * @return PDOStatement|false Returns the PDOStatement object if the query was successful, or false on failure.
   */
  public function query(mixed $statement, ?int $fetchMode = null, ...$fetchModeArgs): PDOStatement|false
  {
    $statement = $this->autoPrefixTables($statement);

    $DbStatement = parent::query($statement);

    if ($DbStatement !== false) {
      $DbStatement->setQueryCall('query');
      $DbStatement->setPDO($this);
    }

    return $DbStatement;
  }

  /**
   * Retrieves data from the specified table with optional conditions, ordering, limits, and caching.
   *
   * @param string|array $table The name of the table or an array of table names. If not prefixed, a default prefix is applied.
   * @param string|array $fields The fields to select from the table.
   * @param array|null $where An associative array of conditions for the query. Can include operators and compound relationships.
   * @param string|array|null $order The
   */
  public function get($table, $fields, array|null $where = null, $order = null, $limit = null, $cache = null, array|null $options = null)
  {
    if (!is_array($table)) {
      $table = [
        $table
      ];
    }

    if (!isset($options['prefix_tables']) || ($options['prefix_tables'] === true)) {
      array_walk($table, function (&$v) {
        if ((strlen($v) < 7) || (!str_starts_with($v, ':table_'))) {
          $v = ':table_' . $v;
        }
      }
      );
    }

    if (!is_array($fields)) {
      $fields = [
        $fields
      ];
    }

    if (isset($order) && !is_array($order)) {
      $order = [
        $order
      ];
    }

    if (isset($limit)) {
      if (is_array($limit) && (count($limit) === 2) && is_numeric($limit[0]) && is_numeric($limit[1])) {
        $limit = implode(', ', $limit);
      } elseif (!is_numeric($limit)) {
        $limit = null;
      }
    }

    $statement = 'select ' . implode(', ', $fields) . ' from ' . implode(', ', $table);

    if (!isset($where) && !isset($cache)) {
      if (isset($order)) {
        $statement .= ' order by ' . implode(', ', $order);
      }

      return $this->query($statement);
    }

    if (isset($where)) {
      $statement .= ' where ';

      $counter = 0;

      $it_where = new CachingIterator(new ArrayIterator($where), CachingIterator::TOSTRING_USE_CURRENT);

      foreach ($it_where as $key => $value) {
        if (is_array($value)) {
          if (isset($value['val'])) {
            $statement .= $key . ' ' . ($value['op'] ?? '=') . ' :cond_' . $counter;
          }

          if (isset($value['rel'])) {
            if (isset($value['val'])) {
              $statement .= ' and ';
            }

            if (is_array($value['rel'])) {
              $it_rel = new CachingIterator(new ArrayIterator($value['rel']), CachingIterator::TOSTRING_USE_CURRENT);

              foreach ($it_rel as $rel) {
                $statement .= $key . ' = ' . $rel;

                if ($it_rel->hasNext()) {
                  $statement .= ' and ';
                }
              }
            } else {
              $statement .= $key . ' ' . ($value['op'] ?? '=') . ' ' . $value['rel'];
            }
          }
        } else {
          $statement .= $key . ' = :cond_' . $counter;
        }

        if ($it_where->hasNext()) {
          $statement .= ' and ';
        }

        $counter++;
      }
    }

    if (isset($order)) {
      $statement .= ' order by ' . implode(', ', $order);
    }

    if (isset($limit)) {
      $statement .= ' limit ' . $limit;
    }

    $Q = $this->prepare($statement);

    if (isset($where)) {
      $counter = 0;

      foreach ($it_where as $value) {
        if (is_array($value)) {
          if (isset($value['val'])) {
            $Q->bindValue(':cond_' . $counter, $value['val']);
          }
        } else {
          $Q->bindValue(':cond_' . $counter, $value);
        }

        $counter++;
      }
    }

    if (isset($cache)) {
      if (!is_array($cache)) {
        $cache = [$cache];
      }

      ($Q->setCache(...))(...$cache);
    }

    $Q->execute();

    return $Q;
  }

  /**
   * Saves data to a specified database table. Can perform either an insert or an update operation
   * depending on whether a where condition is provided.
   *
   * @param string $table The name of the database table. If table prefixing is enabled in options,
   *                      the table name will be automatically prefixed.
   * @param array|null $data An associative array of column-value pairs to be inserted/updated.
   *                         Columns as keys, values as data to save.
   */
  public function save(string $table, array|null $data, array|null $where_condition = null, array|null $options = null)
  {
    if (empty($data)) {
      return false;
    }

    if (!isset($options['prefix_tables']) || ($options['prefix_tables'] === true)) {
      if ((strlen($table) < 7) || (!str_starts_with($table, ':table_'))) {
        $table = ':table_' . $table;
      }
    }

    // Process special vector fields
    $vector_fields = [];
    foreach ($data as $field => $value) {
      // Check if the field is meant to be a vector and starts with 'vec_'
      if (str_starts_with($field, 'vec_')) {
        $actual_field = substr($field, 4); // Get the actual field name without 'vec_' prefix
        $vector_fields[$actual_field] = $value; // Store the vector value
        unset($data[$field]); // Remove the special prefixed field
      }
    }

    if (isset($where_condition)) {
      $statement = 'update ' . $table . ' set ';

      foreach ($data as $c => $v) {
        if (is_null($v)) {
          $v = 'null';
        }

        if ($v == 'now()' || $v === 'null') {
          $statement .= $c . ' = ' . $v . ', ';
        } else {
          $statement .= $c . ' = :new_' . $c . ', ';
        }
      }

      // Add vector fields with VEC_FromText function
      foreach ($vector_fields as $c => $v) {
        $statement .= $c . ' = VEC_FromText(:vec_' . $c . '), ';
      }

      $statement = substr($statement, 0, -2) . ' where ';

      foreach (array_keys($where_condition) as $c) {
        $statement .= $c . ' = :cond_' . $c . ' and ';
      }

      $statement = substr($statement, 0, -5);

      $Q = $this->prepare($statement);

      foreach ($data as $c => $v) {
        if ($v != 'now()' && $v !== 'null' && !is_null($v)) {
          $Q->bindValue(':new_' . $c, $v);
        }
      }

      // Bind vector fields
      foreach ($vector_fields as $c => $v) {
        // Format vector as [val1,val2,...] if it's an array
        if (is_array($v)) {
          $v = '[' . implode(',', $v) . ']';
        }
        $Q->bindValue(':vec_' . $c, $v);
      }

      foreach ($where_condition as $c => $v) {
        $Q->bindValue(':cond_' . $c, $v);
      }

      $Q->execute();

      return $Q->rowCount();
    } else {
      $is_prepared = false;

      // Combine regular fields and vector fields for the column list
      $all_fields = array_merge(array_keys($data), array_keys($vector_fields));
      $statement = 'insert into ' . $table . ' (' . implode(', ', $all_fields) . ') values (';

      foreach ($data as $c => $v) {
        if (is_null($v)) {
          $v = 'null';
        }

        if ($v == 'now()' || $v === 'null') {
          $statement .= $v . ', ';
        } else {
          if ($is_prepared === false) {
            $is_prepared = true;
          }

          $statement .= ':' . $c . ', ';
        }
      }

      // Add vector fields with VEC_FromText function
      foreach ($vector_fields as $c => $v) {
        if ($is_prepared === false) {
          $is_prepared = true;
        }
        $statement .= 'VEC_FromText(:vec_' . $c . '), ';
      }

      $statement = substr($statement, 0, -2) . ')';

      if ($is_prepared === true) {
        $Q = $this->prepare($statement);

        foreach ($data as $c => $v) {
          if ($v != 'now()' && $v !== 'null' && !is_null($v)) {
            $Q->bindValue(':' . $c, $v);
          }
        }

        // Bind vector fields
        foreach ($vector_fields as $c => $v) {
          // Format vector as [val1,val2,...] if it's an array
          if (is_array($v)) {
            $v = '[' . implode(',', $v) . ']';
          }
          $Q->bindValue(':vec_' . $c, $v);
        }

        $Q->execute();

        return $Q->rowCount();
      } else {
        return $this->exec($statement);
      }
    }
  }

  /**
   * Deletes records from the specified table based on the provided where conditions.
   *
   * @param string $table The name of the table from which records should be deleted.
   * @param array $where_condition An associative array representing the WHERE conditions,
   *                                where the keys are column names and the values are the corresponding values to match.
   *                                If the array is empty, all rows in the table will be deleted.
   * @param array|null $options Optional settings. If the 'prefix_tables'
   */
  public function delete(string $table, array $where_condition = [], ?array $options = null): int
  {
    if (!isset($options['prefix_tables']) || ($options['prefix_tables'] === true)) {
      if ((strlen($table) < 7) || (!str_starts_with($table, ':table_'))) {
        $table = ':table_' . $table;
      }
    }

    $statement = 'delete from ' . $table;

    if (empty($where_condition)) {
      return $this->exec($statement);
    }

    $statement .= ' where ';

    foreach (array_keys($where_condition) as $c) {
      $statement .= $c . ' = :cond_' . $c . ' and ';
    }

    $statement = substr($statement, 0, -5);

    $Q = $this->prepare($statement);

    foreach ($where_condition as $c => $v) {
      $Q->bindValue(':cond_' . $c, $v);
    }

    $Q->execute();

    return $Q->rowCount();
  }

  /**
   * Imports SQL queries from a given SQL file into the database.
   *
   * @param string $sql_file Path to the SQL file to be imported.
   * @param string|null $table_prefix Optional table prefix to be used for renaming tables in the SQL file.
   * @return bool Returns true if all SQL queries are successfully executed, otherwise false.
   */
  public function importSQL(string $sql_file, ?string $table_prefix = null): bool
  {
    try {
      if (is_file($sql_file)) {
        $import_queries = file_get_contents($sql_file);

        if ($import_queries === false) {
          throw new Exception('CLICSHOPPING\Db::importSQL(): Cannot read SQL import file: ' . $sql_file);
        }
      } else {
        throw new Exception('CLICSHOPPING\Db::importSQL(): SQL import file does not exist: ' . $sql_file);
      }
    } catch (Exception $e) {
      trigger_error($e->getMessage());

      return false;
    }

    set_time_limit(0);

    $sql_queries = [];
    $sql_length = strlen($import_queries);
    $pos = strpos($import_queries, ';');

    for ($i = $pos; $i < $sql_length; $i++) {
// remove comments
      if (str_starts_with($import_queries, '#') || str_starts_with($import_queries, '--')) {
        $import_queries = ltrim(substr($import_queries, strpos($import_queries, "\n")));
        $sql_length = strlen($import_queries);
        $i = strpos($import_queries, ';') - 1;
        continue;
      }

      if (substr($import_queries, $i + 1, 1) == "\n") {
        $next = '';

        for ($j = ($i + 2); $j < $sql_length; $j++) {
          if (!empty(substr($import_queries, $j, 1))) {
            $next = substr($import_queries, $j, 6);

            if (str_starts_with($next, '#') || str_starts_with($next, '--')) {
// find out where the break position is so we can remove this line (#comment line)
              for ($k = $j; $k < $sql_length; $k++) {
                if (substr($import_queries, $k, 1) == "\n") {
                  break;
                }
              }

              $query = substr($import_queries, 0, $i + 1);

              $import_queries = substr($import_queries, $k);

// join the query before the comment appeared, with the rest of the dump
              $import_queries = $query . $import_queries;
              $sql_length = strlen($import_queries);
              $i = strpos($import_queries, ';') - 1;
              continue 2;
            }

            break;
          }
        }

        if (empty($next)) { // get the last insert query
          $next = 'insert';
        }

        if ((mb_strtoupper($next) == 'DROP T') ||
          (mb_strtoupper($next) == 'CREATE') ||
          (mb_strtoupper($next) == 'INSERT') ||
          (mb_strtoupper($next) == 'ALTER') ||
          (mb_strtoupper($next) == 'SET FO')) {
          $next = '';

          $sql_query = substr($import_queries, 0, $i);

          if (isset($table_prefix) && !empty($table_prefix)) {
            if (mb_strtoupper(substr($sql_query, 0, 20)) == 'DROP TABLE IF EXISTS') {
              $sql_query = 'DROP TABLE IF EXISTS ' . $table_prefix . substr($sql_query, 21);
            } elseif (mb_strtoupper(substr($sql_query, 0, 12)) == 'CREATE TABLE') {
              $sql_query = 'CREATE TABLE ' . $table_prefix . substr($sql_query, 13);
            } elseif (mb_strtoupper(substr($sql_query, 0, 11)) == 'INSERT INTO') {
              $sql_query = 'INSERT INTO ' . $table_prefix . substr($sql_query, 12);
            } elseif (mb_strtoupper(substr($sql_query, 0, 12)) == 'CREATE INDEX') {
              $sql_query = substr($sql_query, 0, stripos($sql_query, ' on ')) .
                ' on ' .
                $table_prefix .
                substr($sql_query, stripos($sql_query, ' on ') + 4);
            }
          }

          $sql_queries[] = trim($sql_query);

          $import_queries = ltrim(substr($import_queries, $i + 1));
          $sql_length = strlen($import_queries);
          $i = strpos($import_queries, ';') - 1;
        }
      }
    }

    $error = false;

    foreach ($sql_queries as $q) {
      if ($this->exec($q) === false) {
        $error = true;

        break;
      }
    }

    return !$error;
  }

  /**
   * Parses a schema definition file and generates an array representation of the database schema.
   *
   * @param string $file The path to the schema definition file. It is expected to have a structured format
   *                     where different sections (columns, indexes, foreign keys, properties) are divided
   *                     by*/
  public static function getSchemaFromFile(string $file): array
  {
    $table = substr(basename($file), 0, strrpos(basename($file), '.'));
    
    // ✅ SECURITY FIX: Trim whitespace from table name (handles filenames with spaces)
    $table = trim($table);

    // Validate table name to prevent SQL injection
    // Only allow alphanumeric characters and underscores
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
      throw new \InvalidArgumentException('Invalid table name in schema file: ' . $table . '. Only alphanumeric characters and underscores are allowed.');
    }

    $schema = [
      'name' => $table
    ];

    $is_index = $is_foreign = $is_property = false;

    foreach (file($file) as $row) {
      $row = trim($row);

      if (!empty($row)) {
        // Check section delimiters first, before comment check
        if ($row == '--') {
          $is_index = true;
          $is_foreign = $is_property = false;

          continue;
        } elseif ($row == '==') {
          $is_foreign = true;
          $is_index = $is_property = false;

          continue;
        } elseif ($row == '##') {
          $is_property = true;
          $is_index = $is_foreign = false;

          continue;
        }
        
        // Skip comment lines starting with # (but not ## which is a delimiter)
        if (str_starts_with($row, '#')) {
          continue;
        }

        $details = str_getcsv($row, ' ');

        $field_name = array_shift($details);

        if ($is_index === true) {
          $details = array_values(array_filter($details, fn($v) => $v !== null && $v !== ''));

          // Support "unique <index_name> <col> <col...>" while keeping legacy "unique <col> <col...>"
          if ($field_name === 'unique' && count($details) >= 2) {
            $index_name = array_shift($details);
            $schema['index']['unique:' . $index_name] = $details;
          } elseif ($field_name === 'fulltext' && count($details) >= 2) {
            // Support "fulltext <index_name> <col> <col...>"
            $index_name = array_shift($details);
            $schema['index']['fulltext:' . $index_name] = $details;
          } else {
            $schema['index'][$field_name] = $details;
          }

          continue;
        } elseif ($is_foreign === true) {
         foreach ($details as $d) {
            if (!str_contains($d, '(')) {
              if (!isset($schema['foreign'][$field_name]) || !is_array($schema['foreign'][$field_name])) {
                $schema['foreign'][$field_name] = [];
              }
              if (!isset($schema['foreign'][$field_name]['col']) || !is_array($schema['foreign'][$field_name]['col'])) {
                $schema['foreign'][$field_name]['col'] = [];
              }
              $schema['foreign'][$field_name]['col'][] = $d;
              continue;
            }

            if (preg_match('/(.*)\((.*)\)/', $d, $info)) {
              switch ($info[1]) {
                case 'ref_table':
                case 'on_delete':
                case 'on_update':
                case 'prefix':
                  if (!isset($schema['foreign'][$field_name]) || !is_array($schema['foreign'][$field_name])) {
                    $schema['foreign'][$field_name] = [];
                  }
                  $schema['foreign'][$field_name][$info[1]] = (string)$info[2];
                  break;

                case 'ref_col':
                  $schema['foreign'][$field_name]['ref_col'] = array_values(array_filter(explode(' ', $info[2]), fn($v) => $v !== null && $v !== ''));
                  break;
              }
            }
          }

          continue;
        } elseif ($is_property === true) {
          switch ($field_name) {
            case 'engine':
              if (!isset($schema['property']) || !is_array($schema['property'])) {
                $schema['property'] = [];
              }


              $schema['property']['engine'] = implode(' ', $details);
              break;

            case 'character_set':
              if (!isset($schema['property']) || !is_array($schema['property'])) {
                $schema['property'] = [];
              }

              $schema['property']['character_set'] = implode(' ', $details);
              break;

            case 'collate':
              if (!isset($schema['property']) || !is_array($schema['property'])) {
                $schema['property'] = [];
              }

              $schema['property']['collate'] = implode(' ', $details);
              break;

            case 'comment':
              if (!isset($schema['property']) || !is_array($schema['property'])) {
                $schema['property'] = [];
              }

              $schema['property']['comment'] = implode(' ', $details);
              break;
          }

          continue;
        }

        $field_type = array_shift($details);

        if (preg_match('/(.*)\((.*)\)/', $field_type, $type_details)) {
          if (!isset($schema['col'][(string)$field_name]) || !is_array($schema['col'][(string)$field_name])) {
            $schema['col'][(string)$field_name] = [];
          }

          $schema['col'][(string)$field_name]['type'] = $type_details[1];
          $schema['col'][(string)$field_name]['length'] = $type_details[2];
        } else {
           $schema['col'][$field_name] = (array)($schema['col'][$field_name] ?? []);
           $schema['col'][$field_name]['type'] = $field_type;
        }

        // Parse default() - look for default(value) or default (value) pattern (with optional space)
        $details_string = implode(' ', $details);
        if (preg_match('/default\s*\(([^)]+)\)/i', $details_string, $type_default)) {
          $default_value = trim($type_default[1]);
          
          // Remove quotes if present (they'll be added back in SQL generation if needed)
          $default_value = trim($default_value, "'\"");
          
          $schema['col'][$field_name]['default'] = $default_value;
          
          // Remove default() from details string
          $details_string = preg_replace('/default\s*\([^)]+\)/i', '', $details_string);
          $details = array_filter(explode(' ', $details_string), fn($v) => $v !== null && $v !== '');
          $details = array_values($details);
        }

        // Parse comment() - look for comment(value) pattern, may contain spaces
        if (preg_match('/comment\((.+)\)$/', $details_string, $type_comment)) {
          $schema['col'][$field_name]['comment'] = $type_comment[1];
          
          // Remove comment() from details string
          $details_string = preg_replace('/comment\(.+\)$/', '', $details_string);
          $details = array_filter(explode(' ', $details_string), fn($v) => $v !== null && $v !== '');
          $details = array_values($details);
        }

        $is_binary = array_search('binary', $details);

        if (is_int($is_binary)) {
          array_splice($details, $is_binary, 1);
          $schema['col'][$field_name]['binary'] = true;
        }

        $is_unsigned = array_search('unsigned', $details);

        if (is_int($is_unsigned)) {
          array_splice($details, $is_unsigned, 1);
          $schema['col'][$field_name]['unsigned'] = true;
        }

        $is_not_null = array_search('not_null', $details);

        if (is_int($is_not_null)) {
          array_splice($details, $is_not_null, 1);
          $schema['col'][$field_name]['not_null'] = true;
        }

        $is_auto_increment = array_search('auto_increment', $details);

        if (preg_match('/on_update\(([^)]+)\)/', $details_string, $on_update_match)) {
          $schema['col'][$field_name]['on_update'] = $on_update_match[1];
          $details_string = preg_replace('/on_update\([^)]+\)/', '', $details_string);
          $details = array_values(array_filter(explode(' ', $details_string)));
        }

        if (is_int($is_auto_increment)) {
          array_splice($details, $is_auto_increment, 1);
          $schema['col'][$field_name]['auto_increment'] = true;
        }

        if (!empty($details)) {
          $schema['col'][$field_name]['other'] = trim(implode(' ', $details));
        }
      }
    }

    return $schema;
  }

  /**
   * Generates an SQL "CREATE TABLE" statement from the provided schema definition.
   *
   * @param array $schema The table schema, including table name, columns, indexes, and other attributes.
   *                       - 'name' (string): The name of the table.
   *                       - 'col' (array): An associative array of column definitions, where the key is the column name and the value is an array of column properties:
   *                           - 'type' (string): Data type of the column.
   *                           - 'length' (
   */
  public static function getSqlFromSchema(array $schema, ?string $prefix = null)
  {
    // ✅ SECURITY FIX: Escape table name identifier
    $tableName = self::prepareIdentifier(($prefix ?? '') . $schema['name']);
    $sql = 'CREATE TABLE ' . $tableName . ' (' . "\n";

    $rows = [];

    foreach ($schema['col'] as $name => $fields) {
      // ✅ SECURITY FIX: Escape column name identifier
      $row = '  ' . self::prepareIdentifier($name) . ' ' . $fields['type'];

      if (isset($fields['length'])) {
        $type_lower = strtolower($fields['type']);
        
        // For ENUM and SET, length contains the full value list with quotes
        // Don't cast to int, use as-is
        if ($type_lower === 'enum' || $type_lower === 'set') {
          $row .= '(' . $fields['length'] . ')';
        } elseif (preg_match('/^\d+\s*,\s*\d+$/', (string)$fields['length'])) {
          // Numeric precision,scale (e.g. decimal(15,4)) — preserve BOTH parts.
          $row .= '(' . str_replace(' ', '', (string)$fields['length']) . ')';
        } else {
          // For numeric types, cast to int for safety
          $row .= '(' . (int)$fields['length'] . ')';
        }
      }

      if (isset($fields['binary']) && ($fields['binary'] === true)) {
        $row .= ' binary';
      }

      if (isset($fields['unsigned']) && ($fields['unsigned'] === true)) {
        $row .= ' unsigned';
      }

      if (isset($fields['default'])) {
        $default_value = $fields['default'];
        
        // Determine if default value needs quotes based on column type
        $needs_quotes = false;
        $type_lower = strtolower($fields['type']);
        
        // Types that need quotes for default values
        if (str_contains($type_lower, 'char') || 
            str_contains($type_lower, 'text') || 
            str_contains($type_lower, 'enum') || 
            str_contains($type_lower, 'set') ||
            str_contains($type_lower, 'date') ||
            str_contains($type_lower, 'time')) {
          $needs_quotes = true;
        }
        
        // Special keywords that don't need quotes
        $keywords = ['CURRENT_TIMESTAMP', 'NULL', 'NOW()', 'CURRENT_DATE', 'CURRENT_TIME'];
        if (in_array(strtoupper($default_value), $keywords)) {
          $needs_quotes = false;
        }
        
        // Add DEFAULT clause
        if ($needs_quotes && !in_array(strtoupper($default_value), $keywords)) {
          $row .= " DEFAULT '" . addslashes($default_value) . "'";
        } else {
          $row .= ' DEFAULT ' . $default_value;
        }
      }

      if (isset($fields['not_null']) && ($fields['not_null'] === true)) {
        $row .= ' NOT NULL';
      }

      if (isset($fields['auto_increment']) && ($fields['auto_increment'] === true)) {
        $row .= ' auto_increment';
      }

      if (isset($fields['on_update'])) {
        $row .= ' ON UPDATE ' . $fields['on_update'];
      }

      // Add COMMENT clause if comment exists
      if (isset($fields['comment']) && !empty($fields['comment'])) {
        $comment = self::formatComment($fields['comment']);
        if (self::validateComment($comment)) {
          $escaped_comment = self::escapeComment($comment);
          $row .= " COMMENT '" . $escaped_comment . "'";
        }
      }

      $rows[] = $row;
    }

    if (isset($schema['index'])) {
      foreach ($schema['index'] as $name => $fields) {
        $name_normalized = mb_strtolower($name);
        if ($name_normalized == 'primary') {
          $name = 'PRIMARY KEY';
        } elseif ($name_normalized == 'unique') {
          $name = 'UNIQUE KEY';
        } elseif (str_starts_with($name_normalized, 'unique:')) {
          $index_name = substr($name, strlen('unique:'));
          // Escape index name for SQL injection protection
          $name = 'UNIQUE KEY ' . self::prepareIdentifier($index_name);
        } elseif (str_starts_with($name_normalized, 'fulltext:')) {
          $index_name = substr($name, strlen('fulltext:'));
          // Escape index name for SQL injection protection
          $name = 'FULLTEXT KEY ' . self::prepareIdentifier($index_name);
        } else {
          // Escape index name for SQL injection protection
          $name = 'KEY ' . self::prepareIdentifier($name);
        }

        // Support DESC/ASC tokens in index definitions (e.g., "created_at DESC")
        $index_cols = [];
        foreach ($fields as $field) {
          $direction = mb_strtoupper($field);
          if (($direction === 'ASC' || $direction === 'DESC') && !empty($index_cols)) {
            $index_cols[count($index_cols) - 1] .= ' ' . $direction;
            continue;
          }
          
          // ✅ SECURITY FIX: Parse column name with optional prefix length
          // Format: column_name or column_name(length)
          if (preg_match('/^([a-zA-Z0-9_]+)\((\d+)\)$/', $field, $matches)) {
            // Column with prefix length: query(255)
            $col_name = $matches[1];
            $prefix_length = $matches[2];
            $index_cols[] = self::prepareIdentifier($col_name) . '(' . $prefix_length . ')';
          } else {
            // Regular column name: query
            $index_cols[] = self::prepareIdentifier($field);
          }
        }

        $row = '  ' . $name . ' (' . implode(', ', $index_cols) . ')';

        $rows[] = $row;
      }
    }

    if (isset($schema['foreign'])) {
      foreach ($schema['foreign'] as $name => $fields) {
        // Escape foreign key name for SQL injection protection
        $escaped_fk_name = self::prepareIdentifier($name);

        // Escape column names in foreign key definition
        $escaped_cols = array_map(self::prepareIdentifier(...), $fields['col']);
        $escaped_ref_cols = array_map(self::prepareIdentifier(...), $fields['ref_col']);
        
        // Escape reference table name
        $escaped_ref_table = self::prepareIdentifier($fields['ref_table']);
        
        $row = '  FOREIGN KEY ' . $escaped_fk_name . ' (' . implode(', ', $escaped_cols) . ') REFERENCES ' . (isset($prefix) && (!isset($fields['prefix']) || ($fields['prefix'] != 'false')) ? $prefix : '') . $escaped_ref_table . '(' . implode(', ', $escaped_ref_cols) . ')';

        if (isset($fields['on_update'])) {
          $row .= ' ON UPDATE ' . mb_strtoupper($fields['on_update']);
        }

        if (isset($fields['on_delete'])) {
          $row .= ' ON DELETE ' . mb_strtoupper($fields['on_delete']);
        }

        $rows[] = $row;
      }
    }

    $sql .= implode(',' . "\n", $rows) . "\n" . ')';

    if (isset($schema['property'])) {
      if (isset($schema['property']['engine'])) {
        $sql .= ' ENGINE ' . $schema['property']['engine'];
      }

      if (isset($schema['property']['character_set'])) {
        $sql .= ' CHARACTER SET ' . $schema['property']['character_set'];
      }

      if (isset($schema['property']['collate'])) {
        $sql .= ' COLLATE ' . $schema['property']['collate'];
      }

      // Add table-level COMMENT if present
      if (isset($schema['property']['comment']) && !empty($schema['property']['comment'])) {
        $comment = self::formatComment($schema['property']['comment']);
        if (self::validateComment($comment)) {
          $escaped_comment = self::escapeComment($comment);
          $sql .= " COMMENT='" . $escaped_comment . "'";
        }
      }
    }

    $sql .= ';';

    return $sql;
  }

  /**
   * Prepares and sanitizes the input by processing strings or arrays recursively.
   *
   * @param string|array $string The input string or array to be sanitized.
   * @return string|array The sanitized string or array.
   */
  public static function prepareInput(string|array $string): string|array
  {
    if (is_string($string)) {
      return HTML::sanitize($string);
    } elseif (is_array($string)) {
      foreach ($string as $k => $v) {
        $string[$k] = static::prepareInput($v);
      }

      return $string;
    } else {
      return $string;
    }
  }

  /**
   * Escapes and formats a string to be used as an identifier in a database query.
   *
   * @param string $string The input string to be formatted as an identifier.
   * @return string The formatted identifier with special characters escaped.
   */
  public static function prepareIdentifier(string $string): string
  {
    return '`' . str_replace('`', '``', $string) . '`';
  }

  /**
   * Sets the prefix to be used for table names.
   *
   * @param string $prefix The prefix to be set for table names.
   * @return void
   */
  public function setTablePrefix(string $prefix)
  {
    $this->table_prefix = $prefix;
  }


  /**
   * Automatically prefixes table names in a database query statement with the configured table prefix.
   *
   * @param string $statement The SQL query statement containing placeholders for table names.
   * @return string The SQL query statement with table name placeholders replaced by the configured prefix.
   */
  protected function autoPrefixTables(string $statement): string
  {
    $prefix = '';

    if (isset($this->table_prefix)) {
      $prefix = $this->table_prefix;
    } elseif (CLICSHOPPING::configExists('db_table_prefix')) {
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    }

    // Validation stricte du préfixe : lettres, chiffres, underscores uniquement
    if (!preg_match('/^[a-zA-Z0-9_]*$/', $prefix)) {
      throw new \InvalidArgumentException('Invalid table prefix');
    }

    // Ajout d'un underscore terminal si le préfixe est non vide et ne se termine pas déjà par un underscore
    if ($prefix !== '' && !str_ends_with($prefix, '_')) {
      $prefix .= '_';
    }

    // Substitution sûre des tokens
    return preg_replace_callback('/:table_([a-zA-Z0-9_]+)/', function ($matches) use ($prefix) {
      return $prefix . $matches[1];
    }, $statement);
  }

  /**
   * Calculates the total size of the database in megabytes.
   *
   * @return float The size of the database rounded to one decimal place in megabytes.
   */
  public static function sizeDb(): float
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qresult = $CLICSHOPPING_Db->query('SHOW table status FROM ' . CLICSHOPPING::getConfig('db_database'));

    $size = 0;

    while ($Qresult->fetch()) {
      $size += $Qresult->valueInt('Data_length');
    }

    $size_db = round(($size / 1024) / 1024, 1);

    return $size_db;
  }

  /**
   * Calculates and displays the size of the database in megabytes.
   *
   * @return float The size of the database in megabytes, rounded to one decimal place.
   */
  public static function displayDbSize(): float
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qresult = $CLICSHOPPING_Db->query('show table status from ' . CLICSHOPPING::getConfig('db_database'));

    $size = 0;

    while ($Qresult->fetch()) {
      $size += $Qresult->valueDecimal('Data_length');
    }

    $size_db = round(($size / 1024) / 1024, 1);

    return $size_db;
  }

  /**
   * Validate comment text for SQL safety and length
   *
   * @param string $comment Comment text to validate
   * @return bool True if valid, false otherwise
   */
  private static function validateComment(string $comment): bool
  {
    // Check comment length (max 1024 characters for MariaDB)
    if (strlen($comment) > 1024) {
      return false;
    }

    // Empty comments are valid (will be skipped)
    if (empty(trim($comment))) {
      return true;
    }

    // Comments are valid - escaping will handle SQL safety
    return true;
  }

  /**
   * Format comment text for consistency
   *
   * @param string $comment Raw comment text
   * @return string Formatted comment text
   */
  private static function formatComment(string $comment): string
  {
    // Trim whitespace
    $comment = trim($comment);

    // Remove redundant spaces
    $comment = preg_replace('/\s+/', ' ', $comment);

    // Capitalize first letter if not already
    if (!empty($comment)) {
      $comment = ucfirst($comment);
    }

    return $comment;
  }

  /**
   * Escape comment text for SQL safety
   *
   * @param string $comment Comment text to escape
   * @return string Escaped comment text
   */
  private static function escapeComment(string $comment): string
  {
    // Escape single quotes by doubling them (SQL standard)
    $comment = str_replace("'", "''", $comment);

    // Escape backslashes
    $comment = str_replace("\\", "\\\\", $comment);

    return $comment;
  }

  /**
   * Installs a new database schema from the given filename.
   *
   * @param string $filename The name of the file containing the database schema to install, excluding the ".txt" extension.
   * @param bool|null $migrate Optional. Indicates whether to use the migration directory for schema files. Defaults to false.
   * @return void
   */
  public function installNewDb(string $filename, ?bool $migrate = false): void
  {
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');

    $this->exec('SET FOREIGN_KEY_CHECKS = 0');

    if ($migrate === true) {
      $directory = CLICSHOPPING::BASE_DIR . 'Custom/Schema/' . CLICSHOPPING::getVersionDirectory() . DIRECTORY_SEPARATOR;
    } else {
      $directory = CLICSHOPPING::BASE_DIR . 'Custom/Schema/';
    }

    $path_file = $directory . $filename . '.txt';
    $file = $directory . $filename;

    if (is_file($path_file)) {
      $schema = $this->getSchemaFromFile($path_file);
      $sql = $this->getSqlFromSchema($schema, $prefix);

      $this->exec('DROP TABLE IF EXISTS ' . $prefix . basename($file, '.txt'));

      $this->exec($sql);
      $this->importSQL($path_file, $prefix);

      $this->exec('SET FOREIGN_KEY_CHECKS = 1');

      Cache::clear('configuration');
    }
  }

  /**
   * Update table-level comment
   *
   * @param string $table_name Table name (without prefix)
   * @param string $comment Comment text to apply
   * @param string|null $prefix Table prefix (uses default if null)
   * @return bool True on success, false on failure
   */
  public function updateTableComment(string $table_name, string $comment, ?string $prefix = null): bool
  {
    try {
      if ($prefix === null) {
        $prefix = CLICSHOPPING::getConfig('db_table_prefix', '');
      }

      $full_table_name = $prefix . $table_name;

      // Validate table exists
      $check_query = "SHOW TABLES LIKE :table";
      $stmt = $this->prepare($check_query);
      $stmt->execute(['table' => $full_table_name]);

      if ($stmt->rowCount() === 0) {
        error_log("Table does not exist: {$full_table_name}");
        return false;
      }

      // Escape comment for SQL
      $escaped_comment = $this->quote($comment);

      // Update table comment
      $sql = "ALTER TABLE {$full_table_name} COMMENT = {$escaped_comment}";
      $this->exec($sql);

      error_log("Successfully updated table comment for: {$full_table_name}");
      return true;

    } catch (Exception $e) {
      error_log("Error updating table comment for {$table_name}: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Update column comment
   *
   * @param string $table_name Table name (without prefix)
   * @param string $column_name Column name
   * @param string $comment Comment text to apply
   * @param string|null $prefix Table prefix (uses default if null)
   * @return bool True on success, false on failure
   */
  public function updateColumnComment(string $table_name, string $column_name, string $comment, ?string $prefix = null): bool
  {
    try {
      if ($prefix === null) {
        $prefix = CLICSHOPPING::getConfig('db_table_prefix', '');
      }

      $full_table_name = $prefix . $table_name;

      // Validate table exists
      $check_table_query = "SHOW TABLES LIKE :table";
      $stmt = $this->prepare($check_table_query);
      $stmt->execute(['table' => $full_table_name]);

      if ($stmt->rowCount() === 0) {
        error_log("Table does not exist: {$full_table_name}");
        return false;
      }

      // Get current column definition
      $column_query = "
        SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, CHARACTER_SET_NAME, COLLATION_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table
          AND COLUMN_NAME = :column
      ";

      $stmt = $this->prepare($column_query);
      $stmt->execute([
        'table' => $full_table_name,
        'column' => $column_name
      ]);

      $column_info = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$column_info) {
        error_log("Column does not exist: {$full_table_name}.{$column_name}");
        return false;
      }

      // Build column definition
      $column_def = $column_info['COLUMN_TYPE'];

      // Add character set if applicable
      if ($column_info['CHARACTER_SET_NAME']) {
        $column_def .= ' CHARACTER SET ' . $column_info['CHARACTER_SET_NAME'];
      }

      // Add collation if applicable
      if ($column_info['COLLATION_NAME']) {
        $column_def .= ' COLLATE ' . $column_info['COLLATION_NAME'];
      }

      // Add NULL/NOT NULL
      if ($column_info['IS_NULLABLE'] === 'NO') {
        $column_def .= ' NOT NULL';
      } else {
        $column_def .= ' NULL';
      }

      // Add DEFAULT if applicable
      // Note: INFORMATION_SCHEMA returns string 'NULL' for DEFAULT NULL columns
      if ($column_info['COLUMN_DEFAULT'] !== null && $column_info['COLUMN_DEFAULT'] !== 'NULL') {
        if ($column_info['COLUMN_DEFAULT'] === 'CURRENT_TIMESTAMP') {
          $column_def .= ' DEFAULT CURRENT_TIMESTAMP';
        } else {
          $column_def .= ' DEFAULT ' . $this->quote($column_info['COLUMN_DEFAULT']);
        }
      } elseif ($column_info['IS_NULLABLE'] === 'YES') {
        // Explicitly add DEFAULT NULL for nullable columns
        $column_def .= ' DEFAULT NULL';
      }

      // Add EXTRA (auto_increment, on update, etc.)
      if ($column_info['EXTRA']) {
        $column_def .= ' ' . $column_info['EXTRA'];
      }

      // Escape comment for SQL
      $escaped_comment = $this->quote($comment);

      // Update column with comment
      $sql = "ALTER TABLE {$full_table_name} MODIFY COLUMN {$column_name} {$column_def} COMMENT {$escaped_comment}";
      $this->exec($sql);

      error_log("Successfully updated column comment for: {$full_table_name}.{$column_name}");
      return true;

    } catch (Exception $e) {
      error_log("Error updating column comment for {$table_name}.{$column_name}: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Apply multiple column comments to a table
   *
   * @param string $table_name Table name (without prefix)
   * @param array $comments Associative array of column_name => comment
   * @param string|null $prefix Table prefix (uses default if null)
   * @return array Results array with 'success' and 'failed' keys
   */
  public function applyColumnComments(string $table_name, array $comments, ?string $prefix = null): array
  {
    $results = [
      'success' => [],
      'failed' => []
    ];

    foreach ($comments as $column_name => $comment) {
      $success = $this->updateColumnComment($table_name, $column_name, $comment, $prefix);

      if ($success) {
        $results['success'][] = $column_name;
        error_log("Applied comment to {$table_name}.{$column_name}");
      } else {
        $results['failed'][] = $column_name;
        error_log("Failed to apply comment to {$table_name}.{$column_name}");
      }
    }

    $total = count($comments);
    $success_count = count($results['success']);
    $failed_count = count($results['failed']);

    error_log("Applied comments to {$table_name}: {$success_count}/{$total} successful, {$failed_count} failed");

    return $results;
  }

  /**
   * Retrieve comments for all tables or specific tables
   *
   * @param array $table_names Optional array of table names (without prefix)
   * @param string|null $prefix Table prefix (uses default if null)
   * @return array Associative array [table_name => comment]
   */
  public function getTableComments(array $table_names = [], ?string $prefix = null): array
  {
    try {
      if ($prefix === null) {
        $prefix = CLICSHOPPING::getConfig('db_table_prefix', '');
      }

      $database = CLICSHOPPING::getConfig('db_database');

      // Build query to get table comments
      $query = "
        SELECT TABLE_NAME, TABLE_COMMENT
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = :database
      ";

      $params = [
        'database' => $database
      ];

      // If specific table names provided, filter by them
      // NOTE: Use 'tbl_' prefix instead of 'table_' to avoid conflict with autoPrefixTables
      if (!empty($table_names)) {
        $placeholders = [];
        foreach ($table_names as $index => $table_name) {
          $placeholder = 'tbl_' . $index;
          $placeholders[] = ':' . $placeholder;
          $params[$placeholder] = $prefix . $table_name;
        }
        $query .= " AND TABLE_NAME IN (" . implode(', ', $placeholders) . ")";
      } else {
        // Get all tables with the prefix
        $query .= " AND TABLE_NAME LIKE :prefix";
        $params['prefix'] = $prefix . '%';
      }

      $stmt = $this->prepare($query);
      $stmt->execute($params);

      $comments = [];
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Remove prefix from table name for the result
        $table_name = substr($row['TABLE_NAME'], strlen($prefix));
        $comments[$table_name] = $row['TABLE_COMMENT'] ?? '';
      }

      return $comments;

    } catch (Exception $e) {
      error_log("Error retrieving table comments: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Retrieve comments for all columns in a table
   *
   * @param string $table_name Table name (without prefix)
   * @param string|null $prefix Table prefix (uses default if null)
   * @return array Associative array [column_name => comment]
   */
  public function getColumnComments(string $table_name, ?string $prefix = null): array
  {
    try {
      if ($prefix === null) {
        $prefix = CLICSHOPPING::getConfig('db_table_prefix', '');
      }

      $full_table_name = $prefix . $table_name;
      $database = CLICSHOPPING::getConfig('db_database');

      // Check if table exists first
      $check_query = "SHOW TABLES LIKE :table";
      $stmt = $this->prepare($check_query);
      $stmt->execute(['table' => $full_table_name]);

      if ($stmt->rowCount() === 0) {
        error_log("Table does not exist: {$full_table_name}");
        return [];
      }

      // Get column comments
      $query = "
        SELECT COLUMN_NAME, COLUMN_COMMENT
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = :database
          AND TABLE_NAME = :table
        ORDER BY ORDINAL_POSITION
      ";

      $stmt = $this->prepare($query);
      $stmt->execute([
        'database' => $database,
        'table' => $full_table_name
      ]);

      $comments = [];
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $comments[$row['COLUMN_NAME']] = $row['COLUMN_COMMENT'] ?? '';
      }

      return $comments;

    } catch (Exception $e) {
      error_log("Error retrieving column comments for {$table_name}: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Generate comprehensive schema documentation for AI context
   *
   * @param array $table_names Optional array of table names (without prefix)
   * @param string|null $prefix Table prefix (uses default if null)
   * @return array Structured documentation with tables, columns, and relationships
   */
  public function generateSchemaDocumentation(array $table_names = [], ?string $prefix = null): array
  {
    try {
      if ($prefix === null) {
        $prefix = CLICSHOPPING::getConfig('db_table_prefix', '');
      }

      $database = CLICSHOPPING::getConfig('db_database');

      // Get table comments
      $table_comments = $this->getTableComments($table_names, $prefix);

      $documentation = [
        'tables' => [],
        'relationships' => []
      ];

      // For each table, get detailed information
      foreach ($table_comments as $table_name => $table_comment) {
        $full_table_name = $prefix . $table_name;

        // Get column information
        $column_query = "
          SELECT 
            COLUMN_NAME,
            COLUMN_TYPE,
            IS_NULLABLE,
            COLUMN_DEFAULT,
            COLUMN_COMMENT
          FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = :database
            AND TABLE_NAME = :table
          ORDER BY ORDINAL_POSITION
        ";

        $stmt = $this->prepare($column_query);
        $stmt->execute([
          'database' => $database,
          'table' => $full_table_name
        ]);

        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
          $columns[$row['COLUMN_NAME']] = [
            'type' => $row['COLUMN_TYPE'],
            'null' => $row['IS_NULLABLE'],
            'default' => $row['COLUMN_DEFAULT'],
            'comment' => $row['COLUMN_COMMENT'] ?? ''
          ];

          // Parse foreign key relationships from comments
          if (!empty($row['COLUMN_COMMENT'])) {
            $comment = $row['COLUMN_COMMENT'];
            
            // Look for "FK to table_name" or "FK to table_name.column_name" patterns
            if (preg_match('/FK to ([a-zA-Z0-9_]+)(?:\.([a-zA-Z0-9_]+))?/i', $comment, $matches)) {
              $ref_table = $matches[1];
              $ref_column = $matches[2] ?? 'id'; // Default to 'id' if not specified

              $documentation['relationships'][] = [
                'from_table' => $table_name,
                'from_column' => $row['COLUMN_NAME'],
                'to_table' => $ref_table,
                'to_column' => $ref_column,
                'type' => 'many_to_one'
              ];
            }
          }
        }

        // Get index information
        $index_query = "
          SELECT 
            INDEX_NAME,
            COLUMN_NAME,
            NON_UNIQUE,
            INDEX_TYPE
          FROM INFORMATION_SCHEMA.STATISTICS
          WHERE TABLE_SCHEMA = :database
            AND TABLE_NAME = :table
          ORDER BY INDEX_NAME, SEQ_IN_INDEX
        ";

        $stmt = $this->prepare($index_query);
        $stmt->execute([
          'database' => $database,
          'table' => $full_table_name
        ]);

        $indexes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
          $index_name = $row['INDEX_NAME'];
          
          if (!isset($indexes[$index_name])) {
            $indexes[$index_name] = [
              'columns' => [],
              'type' => $row['INDEX_TYPE'],
              'unique' => $row['NON_UNIQUE'] == 0
            ];
          }
          
          $indexes[$index_name]['columns'][] = $row['COLUMN_NAME'];
        }

        // Add table to documentation
        $documentation['tables'][$table_name] = [
          'comment' => $table_comment,
          'columns' => $columns,
          'indexes' => $indexes
        ];
      }

      return $documentation;

    } catch (Exception $e) {
      error_log("Error generating schema documentation: " . $e->getMessage());
      return [
        'tables' => [],
        'relationships' => []
      ];
    }
  }
}
