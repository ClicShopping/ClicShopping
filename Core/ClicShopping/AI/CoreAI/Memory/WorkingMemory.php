<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Memory;


use ClicShopping\AI\Security\SecurityLogger;

/**
 * WorkingMemory Class
 *
 * Provides temporary, hierarchical working memory for runtime sessions:
 * - Stores intermediate results for plans or workflows
 * - Manages temporary variables and arrays
 * - Allows scoped data sharing between execution steps
 * - Supports tracking of changes (history)
 * - Clears automatically at the end of execution or on demand
 * - Supports dynamic scopes and global fallbacks
 *
 * Features:
 * - Scoped key management to avoid collisions
 * - Increment and push/pop operations for numeric or array values
 * - Import/export of memory as JSON, including metadata and history
 * - Debug logging of memory operations
 * - Configurable limits for maximum entries and maximum value size
 */


class WorkingMemory
{
  private SecurityLogger $securityLogger;
  private bool $debug;

  // Working memory data
  private array $storage = [];
  private array $metadata = [];
  private array $scopes = [];
  private string $currentScope = 'global';

  // History tracking
  private array $history = [];
  private bool $trackHistory = true;

  // Limits
  private int $maxStorageSize = 1000; // Max number of entries
  private int $maxValueSize = 10000; // Max value size in characters


  public function __construct()
  {
    $this->securityLogger = new SecurityLogger();
    // Use an explicit boolean check on the defined constant for clearer intent
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

    // Initialize the global scope
    $this->scopes['global'] = [];

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "WorkingMemory initialized",
        'info'
      );
    }
  }

  /**
   * Stores a value in the working memory for the current scope.
   *
   * @param string $key Key
   * @param mixed $value Value
   * @param array $meta Optional metadata
   * @return bool Success
   */
  public function set(string $key, $value, array $meta = []): bool
  {
    // Check limits
    if (count($this->storage) >= $this->maxStorageSize) {
      $this->securityLogger->logSecurityEvent(
        "WorkingMemory storage limit reached. Could not set key: {$key}",
        'warning'
      );
      return false;
    }

    $serialized = serialize($value);
    if (strlen($serialized) > $this->maxValueSize) {
      $this->securityLogger->logSecurityEvent(
        "Value too large for key: {$key} (Size: " . strlen($serialized) . ")",
        'warning'
      );
      return false;
    }

    $scopedKey = $this->getScopedKey($key);
    $timestamp = microtime(true);

    if ($this->trackHistory) {
      $this->history[] = [
        'action' => 'set',
        'key' => $scopedKey,
        'value' => $this->formatValueForHistory($value), // Use a history-friendly format
        'timestamp' => $timestamp,
        'scope' => $this->currentScope,
      ];
    }

    $this->storage[$scopedKey] = $value;

    // Check if key exists to update 'created_at' only on first set
    $isNewKey = !isset($this->metadata[$scopedKey]['created_at']);

    $this->metadata[$scopedKey] = array_merge([
      'created_at' => $isNewKey ? $timestamp : $this->metadata[$scopedKey]['created_at'],
      'updated_at' => $timestamp,
      'scope' => $this->currentScope,
      'type' => gettype($value),
    ], $meta);

    // Ensure the scope array is initialized (though it should be by createScope/enterScope)
    $this->scopes[$this->currentScope] ??= [];

    if (!in_array($scopedKey, $this->scopes[$this->currentScope], true)) {
      $this->scopes[$this->currentScope][] = $scopedKey;
    }

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent("WorkingMemory set: {$scopedKey} = " . $this->formatValueForLog($value), 'info');
    }

    return true;
  }

  /**
   * Retrieves a value from the memory.
   * It checks the current scope first, then falls back to the 'global' scope.
   *
   * @param string $key Key
   * @param mixed $default Default value if not found
   * @return mixed Value or default
   */
  public function get(string $key, $default = null)
  {
    $scopedKey = $this->getScopedKey($key);

    // 1. Check current scope
    if (isset($this->storage[$scopedKey])) {
      $this->metadata[$scopedKey]['last_accessed'] = microtime(true);
      return $this->storage[$scopedKey];
    }

    // 2. Check global scope if not already in it
    if ($this->currentScope !== 'global') {
      $globalKey = 'global::' . $key;
      if (isset($this->storage[$globalKey])) {
        $this->metadata[$globalKey]['last_accessed'] = microtime(true);
        return $this->storage[$globalKey];
      }
    }

    return $default;
  }

  /**
   * Checks if a key exists in the current or global scope.
   *
   * @param string $key Key to check
   * @return bool True if it exists
   */
  public function has(string $key): bool
  {
    $scopedKey = $this->getScopedKey($key);

    // 1. Check current scope
    if (isset($this->storage[$scopedKey])) {
      return true;
    }

    // 2. Check global scope if not already in it
    if ($this->currentScope !== 'global') {
      $globalKey = 'global::' . $key;
      return isset($this->storage[$globalKey]);
    }

    return false;
  }

  /**
   * Deletes a value from the current scope.
   *
   * @param string $key Key to delete
   * @return bool Success
   */
  public function delete(string $key): bool
  {
    $scopedKey = $this->getScopedKey($key);

    if (!isset($this->storage[$scopedKey])) {
      return false;
    }

    if ($this->trackHistory) {
      $this->history[] = [
        'action' => 'delete',
        'key' => $scopedKey,
        'value' => $this->formatValueForHistory($this->storage[$scopedKey]),
        'timestamp' => microtime(true),
        'scope' => $this->currentScope,
      ];
    }

    unset($this->storage[$scopedKey]);
    unset($this->metadata[$scopedKey]);

    // Remove from the scope's key list
    $scopeKeys = $this->scopes[$this->currentScope] ?? [];
    $index = array_search($scopedKey, $scopeKeys);

    if ($index !== false) {
      array_splice($this->scopes[$this->currentScope], $index, 1);
    }

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent( "WorkingMemory delete: {$scopedKey}", 'info');
    }

    return true;
  }

  /**
   * Increments a numeric value. If the key doesn't exist, it is initialized to 0.
   *
   * @param string $key Key
   * @param int|float $amount Amount to add
   * @return bool|int|float New value or false if the existing value is not numeric
   */
  public function increment(string $key, $amount = 1)
  {
    $current = $this->get($key, 0);

    // Use is_numeric for robustness against strings that look like numbers
    if (!is_numeric($current)) {
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent("WorkingMemory increment failed: Key {$key} value is not numeric.", 'warning');
      }
      return false;
    }

    $newValue = $current + $amount;
    $this->set($key, $newValue);

    return $newValue;
  }

  /**
   * Appends an element to an array value. Initializes to an empty array if not set.
   *
   * @param string $key Key
   * @param mixed $value Value to add
   * @return bool Success
   */
  public function push(string $key, $value): bool
  {
    $current = $this->get($key, []);

    // Ensure it's an array before pushing
    if (!is_array($current)) {
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent("WorkingMemory push failed: Key {$key} value is not an array.", 'warning');
      }
      return false;
    }

    $current[] = $value;
    return $this->set($key, $current);
  }

  /**
   * Removes and returns the last element of an array value.
   *
   * @param string $key Key
   * @return mixed Element removed or null if key does not exist or is not an array
   */
  public function pop(string $key)
  {
    $current = $this->get($key); // Get without default to distinguish between non-existent and empty array

    if (!is_array($current) || empty($current)) {
      return null;
    }

    $value = array_pop($current);
    // Use an explicit set to save the modified array back to memory
    $this->set($key, $current);

    return $value;
  }

  /**
   * Creates a new scope (context).
   *
   * @param string $scopeName Scope name
   * @return bool Success (false if scope already exists)
   */
  public function createScope(string $scopeName): bool
  {
    if (isset($this->scopes[$scopeName])) {
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent("WorkingMemory scope creation failed: Scope {$scopeName} already exists.", 'warning');
      }
      return false;
    }

    $this->scopes[$scopeName] = [];

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "WorkingMemory scope created: {$scopeName}",
        'info'
      );
    }

    return true;
  }

  /**
   * Changes the current scope. Creates the scope if it doesn't exist.
   *
   * @param string $scopeName Scope name
   * @return bool Success (always true, as it creates the scope if needed)
   */
  public function enterScope(string $scopeName): bool
  {
    if (!isset($this->scopes[$scopeName])) {
      $this->createScope($scopeName);
    }

    $this->currentScope = $scopeName;

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "WorkingMemory entered scope: {$scopeName}",
        'info'
      );
    }

    return true;
  }

  /**
   * Returns to the 'global' scope.
   */
  public function exitScope(): void
  {
    if ($this->currentScope !== 'global') {
      $this->currentScope = 'global';

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "WorkingMemory exited scope, returned to global",
          'info'
        );
      }
    }
  }

  /**
   * Deletes a scope and all its data. Cannot delete the 'global' scope.
   *
   * @param string $scopeName Scope name
   * @return bool Success (false if scope is 'global' or doesn't exist)
   */
  public function deleteScope(string $scopeName): bool
  {
    if ($scopeName === 'global') {
      return false; // Cannot delete the global scope
    }

    if (!isset($this->scopes[$scopeName])) {
      return false;
    }

    // Delete all data associated with the scope
    foreach ($this->scopes[$scopeName] as $scopedKey) {
      unset($this->storage[$scopedKey]);
      unset($this->metadata[$scopedKey]);
    }

    unset($this->scopes[$scopeName]);

    // If we were in this scope, revert to global
    if ($this->currentScope === $scopeName) {
      $this->currentScope = 'global';
    }

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "WorkingMemory scope deleted: {$scopeName}",
        'info'
      );
    }

    return true;
  }

  /**
   * Gets all keys within a specific scope.
   *
   * @param string|null $scopeName Scope name (null = current scope)
   * @return array List of unscoped keys
   */
  public function getKeys(?string $scopeName = null): array
  {
    $scope = $scopeName ?? $this->currentScope;

    if (!isset($this->scopes[$scope])) {
      return [];
    }

    // Remove the scope prefix for external use
    $keys = [];
    foreach ($this->scopes[$scope] as $scopedKey) {
      $keys[] = $this->unscopeKey($scopedKey);
    }

    return $keys;
  }

  /**
   * Gets all data (key-value pairs) for a specific scope.
   *
   * @param string|null $scopeName Scope name (null = current scope)
   * @return array Scope data
   */
  public function getScopeData(?string $scopeName = null): array
  {
    $scope = $scopeName ?? $this->currentScope;
    $data = [];

    // Temporarily enter the scope to correctly retrieve data via get()
    $previousScope = $this->currentScope;
    $this->currentScope = $scope;

    foreach ($this->getKeys($scope) as $key) {
      // Use get() which will automatically retrieve from the now-current scope
      $data[$key] = $this->get($key);
    }

    // Restore the previous scope
    $this->currentScope = $previousScope;

    return $data;
  }

  /**
   * Copies data from one scope to another.
   *
   * @param string $fromScope Source scope
   * @param string $toScope Destination scope
   * @param array $keys Keys to copy (empty array = copy all keys from source scope)
   * @return bool Success
   */
  public function copyScope(string $fromScope, string $toScope, array $keys = []): bool
  {
    if (!isset($this->scopes[$fromScope])) {
      return false;
    }

    if (!isset($this->scopes[$toScope])) {
      $this->createScope($toScope);
    }

    $previousScope = $this->currentScope;

    // If no keys specified, get all keys from the source scope
    if (empty($keys)) {
      // Use getKeys to find all relevant keys in the source scope
      $keys = $this->getKeys($fromScope);
    }

    foreach ($keys as $key) {
      // 1. Get the value from the source scope
      $this->currentScope = $fromScope;
      $value = $this->get($key);

      // 2. Set the value in the destination scope
      $this->currentScope = $toScope;
      $this->set($key, $value);
    }

    // Restore the previous scope
    $this->currentScope = $previousScope;

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "WorkingMemory copied " . count($keys) . " keys from {$fromScope} to {$toScope}",
        'info'
      );
    }

    return true;
  }

  /**
   * Clears all memory: storage, metadata, scopes (retains 'global'), and history.
   */
  public function clear(): void
  {
    $this->storage = [];
    $this->metadata = [];
    $this->scopes = ['global' => []]; // Reset all scopes except 'global'
    $this->currentScope = 'global';
    $this->history = [];

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "WorkingMemory cleared",
        'info'
      );
    }
  }

  /**
   * Clears only the data within the current scope. Does nothing if the current scope is 'global'.
   */
  public function clearCurrentScope(): void
  {
    if ($this->currentScope === 'global') {
      return; // Cannot clear the global scope; use specific deletion for global keys if needed
    }

    $scopeName = $this->currentScope;

    // Delete the scope, which deletes all data associated with it
    $this->deleteScope($scopeName);

    // Recreate the scope to keep the scope name registered and current
    $this->createScope($scopeName);
    $this->currentScope = $scopeName; // Re-enter the scope

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "WorkingMemory cleared current scope: {$scopeName}",
        'info'
      );
    }
  }

  /**
   * Gets the history of modifications.
   *
   * @param int $limit Max number of entries (0 = all)
   * @return array History
   */
  public function getHistory(int $limit = 0): array
  {
    if ($limit > 0) {
      return array_slice($this->history, -$limit); // Get the latest entries
    }

    return $this->history;
  }

  /**
   * Gets the metadata for a key in the current scope.
   *
   * @param string $key Key
   * @return array|null Metadata or null if key is not found
   */
  public function getMetadata(string $key): ?array
  {
    $scopedKey = $this->getScopedKey($key);

    return $this->metadata[$scopedKey] ?? null;
  }

  /**
   * Gets memory usage statistics.
   *
   * @return array Statistics
   */
  public function getStats(): array
  {
    $totalSize = 0;
    foreach ($this->storage as $value) {
      $totalSize += strlen(serialize($value));
    }

    $scopeStats = [];
    foreach ($this->scopes as $scopeName => $keys) {
      $scopeStats[$scopeName] = count($keys);
    }

    return [
      'total_entries' => count($this->storage),
      'total_size_bytes' => $totalSize,
      'total_size_kb' => round($totalSize / 1024, 2),
      'scopes' => $scopeStats,
      'current_scope' => $this->currentScope,
      'history_entries' => count($this->history),
      'max_storage_size' => $this->maxStorageSize,
      'usage_percentage' => round((count($this->storage) / $this->maxStorageSize) * 100, 2),
    ];
  }

  /**
   * Exports the memory data to a JSON string.
   *
   * @param bool $includeMetadata Include metadata and history
   * @return string JSON representation of the memory
   */
  public function export(bool $includeMetadata = false): string
  {
    $data = [
      'storage' => $this->storage,
      'scopes' => $this->scopes,
      'current_scope' => $this->currentScope,
    ];

    if ($includeMetadata) {
      $data['metadata'] = $this->metadata;
      // Note: History values are already simplified in the set method, which is good for export size
      $data['history'] = $this->history;
    }

    return json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR); // Added JSON_THROW_ON_ERROR for robust error handling
  }

  /**
   * Imports data from a JSON string, overwriting current memory.
   *
   * @param string $json JSON to import
   * @return bool Success
   */
  public function import(string $json): bool
  {
    try {
      $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

      if (!is_array($data)) {
        return false;
      }

      // Explicitly check for keys to ensure a valid import structure
      if (isset($data['storage']) && is_array($data['storage'])) {
        $this->storage = $data['storage'];
      }

      if (isset($data['scopes']) && is_array($data['scopes'])) {
        $this->scopes = $data['scopes'];
      }

      if (isset($data['current_scope']) && is_string($data['current_scope'])) {
        $this->currentScope = $data['current_scope'];
      }

      // Optional data
      $this->metadata = $data['metadata'] ?? $this->metadata;
      $this->history = $data['history'] ?? $this->history;

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent("WorkingMemory imported successfully", 'info');
      }

      return true;

    } catch (\JsonException $e) { // Catch specific JSON decoding errors
      $this->securityLogger->logSecurityEvent(
        "WorkingMemory import failed (JSON error): " . $e->getMessage(),
        'error'
      );
      return false;
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "WorkingMemory import failed (General error): " . $e->getMessage(),
        'error'
      );
      return false;
    }
  }

  /**
   * Generates a scoped key for internal use.
   *
   * @param string $key Original key
   * @return string Scoped key
   */
  private function getScopedKey(string $key): string
  {
    return $this->currentScope . '::' . $key;
  }

  /**
   * Removes the scope prefix from a scoped key.
   *
   * @param string $scopedKey Scoped key
   * @return string Unscoped key
   */
  private function unscopeKey(string $scopedKey): string
  {
    // Use strpos for better performance than explode/list if we only need to check for the separator
    if (str_starts_with($scopedKey, $this->currentScope . '::')) {
      return substr($scopedKey, strlen($this->currentScope) + 2);
    }

    // Fallback for global scope keys accessed from another scope
    $parts = explode('::', $scopedKey, 2);
    return $parts[1] ?? $scopedKey;
  }

  /**
   * Formats a value for debug logging.
   *
   * @param mixed $value Value
   * @return string Textual representation
   */
  private function formatValueForLog(mixed $value): string
  {
    if (is_array($value)) {
      return 'Array(' . count($value) . ')';
    } elseif (is_object($value)) {
      return 'Object(' . get_class($value) . ')';
    } elseif (is_string($value)) {
      // Show first 50 chars of string
      return '"' . substr($value, 0, 50) . (strlen($value) > 50 ? '...' : '') . '"';
    } elseif (is_bool($value)) {
      return $value ? 'true' : 'false';
    } else {
      return (string)$value;
    }
  }

  /**
   * Formats a value for history tracking (avoids storing massive objects/strings).
   *
   * @param mixed $value Value
   * @return string|int|float|array Textual or simplified representation
   */
  private function formatValueForHistory($value)
  {
    if (is_array($value)) {
      return 'Array(' . count($value) . ')';
    } elseif (is_object($value)) {
      return 'Object(' . get_class($value) . ')';
    } elseif (is_string($value)) {
      return 'String(' . strlen($value) . ')';
    } else {
      return $value;
    }
  }

  /**
   * Magic method for easy read access: $memory->key.
   * Returns the value from the current scope or null if not found.
   */
  public function __get(string $key)
  {
    return $this->get($key);
  }

  /**
   * Magic method for easy write access: $memory->key = $value.
   * Sets the value in the current scope.
   */
  public function __set(string $key, $value): void
  {
    $this->set($key, $value);
  }

  /**
   * Magic method for checking existence: isset($memory->key).
   * Returns true if the key exists in the current or global scope.
   */
  public function __isset(string $key): bool
  {
    return $this->has($key);
  }

  /**
   * Magic method for deletion: unset($memory->key).
   * Deletes the key from the current scope.
   */
  public function __unset(string $key): void
  {
    $this->delete($key);
  }
}
