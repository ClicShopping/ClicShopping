<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

use function strlen;

/**
 * Class Cache
 * Manages caching operations including saving, retrieving, checking existence, and clearing cache data.
 */
class Cache
{
  protected static string $path;
  protected const SAFE_KEY_NAME_REGEX = 'a-zA-Z0-9-_';
  protected string $key;
  protected mixed $data = null;
  protected bool $compressionEnabled = false;
  protected string $namespace = '';

  // Cache en mémoire pour éviter les lectures répétées
  protected static array $memoryCache = [];
  protected static int $memoryCacheSize = 0;
  protected static int $maxMemoryCacheSize = 1048576; // 1MB

  /**
   * Constructor method for initializing the class.
   *
   * @param string $key A unique identifier key to set during the object instantiation.
   * @param string $namespace Optional namespace to avoid key collisions
   * @param bool $enableCompression Enable data compression for large datasets
   */
  public function __construct(string $key, string $namespace = '', bool $enableCompression = false)
  {
    static::setPath();

    $this->namespace = $namespace;
    $this->compressionEnabled = $enableCompression;
    $this->setKey($key);
  }

  /**
   * Sets the cache key if it matches the valid key name pattern.
   *
   * @param string $key The cache key to set. It must comply with the valid naming convention.
   * @return static Returns the current instance for method chaining
   * @throws \InvalidArgumentException If the key name is invalid
   */
  public function setKey(string $key): static
  {
    if (!static::hasSafeName($key)) {
      $error = 'ClicShopping\\OM\\Cache: Invalid key name ("' . $key . '"). Valid characters are ' . static::SAFE_KEY_NAME_REGEX;
      trigger_error($error);
      throw new \InvalidArgumentException($error);
    }

    $this->key = $key;
    return $this;
  }

  /**
   * Retrieves the key value.
   *
   * @return string The value of the key property.
   */
  public function getKey(): string
  {
    return $this->key;
  }

  /**
   * Saves the provided data to a cache file with metadata - Version simplifiée sans rename()
   *
   * @param mixed $data The data to be saved
   * @param array $metadata Optional metadata (tags, dependencies, etc.)
   * @return bool Returns true if the data was successfully written to the cache file, otherwise false.
   */
  public function save($data, array $metadata = []): bool
  {
    if (!FileSystem::isWritable(static::getPath())) {
      return false;
    }

    // Ensure namespace directory exists
    if (!$this->ensureNamespaceDirectory()) {
      return false;
    }

    $filename = $this->getFilePath();

    // Préparer les données avec métadonnées
    $cacheData = [
      'data' => $data,
      'metadata' => array_merge($metadata, [
        'created_at' => time(),
        'compressed' => $this->compressionEnabled,
        'size' => 0
      ])
    ];

    $serializedData = serialize($cacheData);

    // Compression si activée
    if ($this->compressionEnabled && function_exists('gzcompress')) {
      $compressedData = gzcompress($serializedData, 6);
      if ($compressedData !== false) {
        $serializedData = $compressedData;
        $cacheData['metadata']['compressed'] = true;
      }
    }

    // Écriture directe avec verrouillage exclusif
    // LOCK_EX empêche les écritures concurrentes
    $result = file_put_contents($filename, $serializedData, LOCK_EX);

    if ($result !== false) {
      // Forcer les permissions pour contourner le umask
      @chmod($filename, 0664);

      // Mettre à jour le cache mémoire (use full key for backward compatibility)
      $fullKey = $this->getFullKey();
      $this->updateMemoryCache($fullKey, $data);

      return true;
    }

    return false;
  }

  /**
   * Gets the full key including namespace
   *
   * @return string
   * @deprecated Use getNamespacePath() and key separately
   */
  protected function getFullKey(): string
  {
    return $this->namespace ? $this->namespace . '_' . $this->key : $this->key;
  }

  /**
   * Gets the namespace as a directory path
   *
   * @return string The namespace path with trailing slash, or empty string
   */
  protected function getNamespacePath(): string
  {
    if (empty($this->namespace)) {
      return '';
    }
    
    // Convert namespace to directory path
    // 'Rag/Intent' → 'Rag/Intent/'
    // 'context' → 'context/'
    return rtrim($this->namespace, '/') . '/';
  }

  /**
   * Ensures the namespace directory exists
   *
   * @return bool True if directory exists or was created successfully
   */
  protected function ensureNamespaceDirectory(): bool
  {
    $namespacePath = $this->getNamespacePath();
    
    if (empty($namespacePath)) {
      return true;
    }
    
    $fullPath = static::getPath() . $namespacePath;
    
    if (!is_dir($fullPath)) {
      return mkdir($fullPath, 0775, true);
    }
    
    return true;
  }

  /**
   * Gets the full file path for the cache file
   *
   * @return string The complete file path
   */
  protected function getFilePath(): string
  {
    $namespacePath = $this->getNamespacePath();
    return static::getPath() . $namespacePath . $this->key . '.cache';
  }

  /**
   * Checks if the cache file exists and optionally verifies if it has not expired.
   *
   * @param string|null $expire Optional expiration time in minutes. If provided, checks if the cache file's age is less than the given value.
   * @return bool Returns true if the cache file exists and meets the expiration criteria (if provided), otherwise false.
   */
  public function exists(?string $expire = null): bool
  {
    $fullKey = $this->getFullKey();

    // Vérifier d'abord le cache mémoire
    if (isset(static::$memoryCache[$fullKey])) {
      if (!isset($expire)) {
        return true;
      }

      $difference = floor((time() - static::$memoryCache[$fullKey]['timestamp']) / 60);
      return is_numeric($expire) && ($difference < $expire);
    }

    $filename = $this->getFilePath();

    if (is_file($filename)) {
      if (!isset($expire)) {
        return true;
      }

      $difference = floor((time() - filemtime($filename)) / 60);
      return is_numeric($expire) && ($difference < $expire);
    }

    return false;
  }

  /**
   * Retrieves the cached data associated with the current key.
   *
   * @return mixed Returns the cached data if it exists, or null if no cache is found.
   */
  public function get()
  {
    $fullKey = $this->getFullKey();

    // Vérifier le cache mémoire en premier
    if (isset(static::$memoryCache[$fullKey])) {
      return static::$memoryCache[$fullKey]['data'];
    }

    $filename = $this->getFilePath();

    if (is_file($filename)) {
      $contents = file_get_contents($filename);

      if ($contents === false) {
        return null;
      }

      try {
        // Essayer de décompresser si nécessaire
        if ($this->isCompressed($contents)) {
          $contents = gzuncompress($contents);
          if ($contents === false) {
            return null;
          }
        }

        $cacheData = unserialize($contents, ['allowed_classes' => false]);

        // Retrocompatibilité : si ce n'est pas le nouveau format avec métadonnées
        if (!is_array($cacheData) || !isset($cacheData['data'])) {
          $this->data = $cacheData;
        } else {
          $this->data = $cacheData['data'];
        }

        // Mettre à jour le cache mémoire
        $this->updateMemoryCache($fullKey, $this->data);

      } catch (\Exception $e) {
        // En cas d'erreur de désérialisation, supprimer le fichier corrompu
        unlink($filename);
        return null;
      }
    }

    return $this->data ?? null;
  }

  /**
   * Gets cache metadata
   *
   * @return array|null
   */
  public function getMetadata(): ?array
  {
    $filename = $this->getFilePath();

    if (!is_file($filename)) {
      return null;
    }

    $contents = file_get_contents($filename);

    if ($contents === false) {
      return null;
    }

    try {
      if ($this->isCompressed($contents)) {
        $contents = gzuncompress($contents);
      }

      $cacheData = unserialize($contents, ['allowed_classes' => false]);

      return is_array($cacheData) && isset($cacheData['metadata'])
        ? $cacheData['metadata']
        : ['created_at' => filemtime($filename), 'size' => filesize($filename)];

    } catch (\Exception $e) {
      return null;
    }
  }

  /**
   * Batch save operation
   *
   * @param array $items Array of ['key' => 'data'] pairs
   * @param array $metadata Common metadata for all items
   * @return array Results array with success/failure for each key
   */
  public static function saveBatch(array $items, array $metadata = []): array
  {
    $results = [];

    foreach ($items as $key => $data) {
      try {
        $cache = new static($key);
        $results[$key] = $cache->save($data, $metadata);
      } catch (\Exception $e) {
        $results[$key] = false;
      }
    }

    return $results;
  }

  /**
   * Batch get operation
   *
   * @param array $keys Array of cache keys
   * @return array Results array with data for each key
   */
  public static function getBatch(array $keys): array
  {
    $results = [];

    foreach ($keys as $key) {
      try {
        $cache = new static($key);
        $results[$key] = $cache->get();
      } catch (\Exception $e) {
        $results[$key] = null;
      }
    }

    return $results;
  }

  /**
   * Checks if the given data is compressed.
   *
   * @param string $data The data to check.
   * @return bool Returns true if the data is compressed, false otherwise.
   */
  protected function isCompressed(string $data): bool
  {
    // Vérification basique : les données gzcompressed commencent par des bytes spécifiques
    return strlen($data) > 2 && ord($data[0]) === 0x1f && ord($data[1]) === 0x8b;
  }

  /**
   * Updates the in-memory cache with the provided data.
   *
   * @param string $key The cache key.
   * @param mixed $data The data to be cached.
   * @param array $additionalInfo Optional additional information to store with the cache entry.
   * @return void
   */
  protected function updateMemoryCache(string $key, $data, array $additionalInfo = []): void
  {
    $serializedSize = strlen(serialize($data));

    // Si l'élément est trop gros ou si on dépasse la limite, ne pas mettre en cache
    if ($serializedSize > static::$maxMemoryCacheSize / 4) {
      return;
    }

    // Nettoyer le cache mémoire si nécessaire
    while (static::$memoryCacheSize + $serializedSize > static::$maxMemoryCacheSize && !empty(static::$memoryCache)) {
      $oldestKey = array_key_first(static::$memoryCache);
      static::$memoryCacheSize -= static::$memoryCache[$oldestKey]['size'];
      unset(static::$memoryCache[$oldestKey]);
    }

    static::$memoryCache[$key] = array_merge([
      'data' => $data,
      'size' => $serializedSize,
      'timestamp' => time()
    ], $additionalInfo);

    static::$memoryCacheSize += $serializedSize;
  }

  /**
   * Clears the in-memory cache.
   *
   * @return void
   */
  public static function clearMemoryCache(): void
  {
    static::$memoryCache = [];
    static::$memoryCacheSize = 0;
  }

  /**
   * Sets the maximum size for the in-memory cache.
   *
   * @param int $bytes The maximum size in bytes.
   * @return void
   */
  public static function setMaxMemoryCacheSize(int $bytes): void
  {
    static::$maxMemoryCacheSize = $bytes;
  }

  /**
   * Checks if the provided key has a safe name based on a predefined regex pattern.
   *
   * @param string $key The key to be checked.
   * @return bool Returns true if the key matches the safe name criteria, false otherwise.
   */
  public static function hasSafeName(string $key): bool
  {
    return preg_match('/^[' . static::SAFE_KEY_NAME_REGEX . ']+$/', $key) === 1;
  }

  /**
   * Retrieves the last modification time of the cache file associated with the current key.
   *
   * @return int|false The file modification time as a Unix timestamp if the file exists, or false if the file does not exist.
   */
  public function getTime()
  {
    $filename = $this->getFilePath();
    return is_file($filename) ? filemtime($filename) : false;
  }

  /**
   * Finds whether a cache file exists for the given key.
   *
   * @param string $key The cache key to search for. Must consist of valid characters (a-zA-Z0-9-_).
   * @param bool $strict If true, an exact match is required for the key. If false, a partial match is allowed.
   * @param string $namespace Optional namespace
   * @return bool Returns true if a matching cache file is found, otherwise false.
   */
  public static function find(string $key, bool $strict = true, string $namespace = ''): bool
  {
    if (!static::hasSafeName($key)) {
      trigger_error('ClicShopping\\OM\\Cache::find(): Invalid key name (\'' . $key . '\'). Valid characters are a-zA-Z0-9-_');
      return false;
    }

    // Get namespace path
    $namespacePath = '';
    if (!empty($namespace)) {
      $namespacePath = rtrim($namespace, '/') . '/';
    }

    $searchPath = static::getPath() . $namespacePath;
    $filename = $searchPath . $key . '.cache';

    if (is_file($filename)) {
      return true;
    }

    if ($strict === false) {
      $key_length = strlen($key);
      
      if (!is_dir($searchPath)) {
        return false;
      }
      
      $d = dir($searchPath);

      while (($entry = $d->read()) !== false) {
        if ((strlen($entry) >= $key_length) && (substr($entry, 0, $key_length) == $key)) {
          $d->close();
          return true;
        }
      }
    }

    return false;
  }

  /**
   * Sets the path to the cache directory.
   *
   * @return void
   */
  public static function setPath()
  {
    static::$path = CLICSHOPPING::BASE_DIR . 'Work/Cache/';
  }

  /**
   * Retrieves the path. If the path is not set, it initializes the path by calling setPath().
   *
   * @return string The current stored path.
   */
  public static function getPath()
  {
    if (!isset(static::$path)) {
      static::setPath();
    }

    return static::$path;
  }

  /**
   * Clears cached files associated with the specified key.
   *
   * @param string $key The key identifying cached files to be cleared. Only safe key names are allowed.
   * @param string $namespace Optional namespace
   * @return bool Returns true if the cache path is writable and the operation is performed; false otherwise.
   */
  public static function clear(string $key, string $namespace = ''): bool
  {
    $key = basename($key);

    if (!static::hasSafeName($key)) {
      trigger_error('ClicShopping\\Cache::clear(): Invalid key name ("' . $key . '"). Valid characters are ' . static::SAFE_KEY_NAME_REGEX);
      return false;
    }

    if (!FileSystem::isWritable(static::getPath())) {
      return false;
    }

    // Get namespace path
    $namespacePath = '';
    if (!empty($namespace)) {
      $namespacePath = rtrim($namespace, '/') . '/';
    }

    $searchPath = static::getPath() . $namespacePath;
    
    if (!is_dir($searchPath)) {
      return true; // Nothing to clear
    }

    $key_length = strlen($key);

    // Supprimer du cache mémoire (use old format for backward compatibility)
    $fullKey = $namespace ? $namespace . '_' . $key : $key;
    unset(static::$memoryCache[$fullKey]);

    $DLcache = new DirectoryListing($searchPath);
    $DLcache->setIncludeDirectories(false);

    foreach ($DLcache->getFiles() as $file) {
      if ((strlen($file['name']) >= $key_length) && (substr($file['name'], 0, $key_length) == $key)) {
        unlink($searchPath . $file['name']);
      }
    }

    return true;
  }

  /**
   * Clears all cache files in the specified directory.
   *
   * @return void
   */
  public static function clearAll(): void
  {
    static::clearMemoryCache();

    if (FileSystem::isWritable(static::getPath())) {
      foreach (glob(static::getPath() . '*.cache', GLOB_NOSORT) as $c) {
        unlink($c);
      }
    }
  }

  /**
   * Retrieves statistics about the cache, including total files, total size, and memory cache details.
   *
   * @return array An associative array containing cache statistics.
   */
  public static function getStats(): array
  {
    $path = static::getPath();
    $files = glob($path . '*.cache', GLOB_NOSORT);
    $totalSize = 0;
    $totalFiles = count($files);

    foreach ($files as $file) {
      $totalSize += filesize($file);
    }

    return [
      'total_files' => $totalFiles,
      'total_size' => $totalSize,
      'total_size_formatted' => static::formatBytes($totalSize),
      'memory_cache_items' => count(static::$memoryCache),
      'memory_cache_size' => static::$memoryCacheSize,
      'memory_cache_size_formatted' => static::formatBytes(static::$memoryCacheSize),
      'cache_path' => $path
    ];
  }

  /**
   * Format bytes to human readable format
   *
   * @param int $bytes The number of bytes
   * @param int $precision The decimal precision
   * @return string Formatted bytes string
   */
  protected static function formatBytes(int $bytes, int $precision = 2): string
  {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
      $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
  }

  /**
   * @param string $fullKey
   * @return mixed
   */
  public static function memoryCache(string $fullKey): mixed
  {
    return static::$memoryCache[$fullKey] ?? null;
  }

  /**
   * Purge expired cache files
   *
   * @param int $maxAgeMinutes Maximum age in minutes
   * @return int Number of purged files
   */
  public static function purgeExpired(int $maxAgeMinutes): int
  {
    $purged = 0;
    $cutoffTime = time() - ($maxAgeMinutes * 60);

    foreach (glob(static::getPath() . '*.cache', GLOB_NOSORT) as $file) {
      if (filemtime($file) < $cutoffTime) {
        unlink($file);
        $purged++;
      }
    }

    return $purged;
  }
}