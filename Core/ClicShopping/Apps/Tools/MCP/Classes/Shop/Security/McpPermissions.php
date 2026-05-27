<?php
/**
 * Gestion des permissions MCP basée sur la table clic_mcp
 * * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\Shop\Security;


use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;


/**
 * Classe de gestion des permissions MCP
 * Vérifie les droits d'accès basés sur les champs de la table clic_mcp
 */
class McpPermissions
{
  private mixed $db;
  private static ?array $permissionsCache = null;

  public function __construct()
  {
    $this->db = Registry::get('Db');
  }

  /**
   * Vérifie si un utilisateur MCP a une permission spécifique
   *
   * @param string $username Nom d'utilisateur MCP
   * @param string $permission Type de permission à vérifier
   * @return bool True si autorisé, false sinon
   */
  public function hasPermission(string $username, string $permission): bool
  {
    try {
      $permissions = $this->getUserPermissions($username);

      if (!$permissions) {
        McpSecurity::logSecurityEvent('Permission check failed - user not found', [
          'username' => $username,
          'permission' => $permission
        ]);
        return false;
      }

      // Vérifier si l'utilisateur est actif
      if (!$permissions['status']) {
        McpSecurity::logSecurityEvent('Permission check failed - user inactive', [
          'username' => $username,
          'permission' => $permission
        ]);
        return false;
      }

      $hasPermission = match ($permission) {
        'select_data' => (bool) $permissions['select_data'],
        'update_data' => (bool) $permissions['update_data'],
        'create_data' => (bool) $permissions['create_data'],
        'delete_data' => (bool) $permissions['delete_data'],
        'create_db' => (bool) $permissions['create_db'],
        'read_only' => (bool) $permissions['select_data'] && !$permissions['update_data'] && !$permissions['create_data'] && !$permissions['delete_data'],
        'read_write' => (bool) $permissions['select_data'] && (bool) $permissions['update_data'],
        'full_access' => (bool) $permissions['select_data'] && (bool) $permissions['update_data'] && (bool) $permissions['create_data'] && (bool) $permissions['delete_data'],
        'admin' => (bool) $permissions['create_db'],
        default => false
      };

      McpSecurity::logSecurityEvent('Permission check completed', [
        'username' => $username,
        'permission' => $permission,
        'granted' => $hasPermission
      ]);

      return $hasPermission;

    } catch (\Exception $e) {
      McpSecurity::logSecurityEvent('Permission check error', [
        'username' => $username,
        'permission' => $permission,
        'error' => $e->getMessage()
      ]);
      return false;
    }
  }

  /**
   * Récupère toutes les permissions d'un utilisateur
   *
   * @param string $username Nom d'utilisateur MCP
   * @return array|null Permissions de l'utilisateur ou null si non trouvé
   */
  public function getUserPermissions(string $username): ?array
  {
    try {
      // Vérifier le cache d'abord
      $cacheKey = 'mcp_permissions_' . $username;
      if (self::$permissionsCache && isset(self::$permissionsCache[$cacheKey])) {
        return self::$permissionsCache[$cacheKey];
      }

      $Quser = $this->db->prepare('SELECT mcp_id,
                                               username,
                                               status,
                                               select_data,
                                               update_data,
                                               create_data,
                                               delete_data,
                                               create_db,
                                               date_added,
                                               date_modified
                                        FROM :table_mcp
                                        WHERE username = :username
                                        LIMIT 1');

      $Quser->bindValue(':username', HTML::sanitize($username));
      $Quser->execute();

      if ($Quser->rowCount() === 0) {
        return null;
      }

      $permissions = $Quser->fetch();

      // Mettre en cache pour 5 minutes
      if (!self::$permissionsCache) {
        self::$permissionsCache = [];
      }
      self::$permissionsCache[$cacheKey] = $permissions;

      return $permissions;

    } catch (\Exception $e) {
      McpSecurity::logSecurityEvent('Error retrieving user permissions', [
        'username' => $username,
        'error' => $e->getMessage()
      ]);
      return null;
    }
  }

  /**
   * Vérifie les permissions pour une requête SQL
   *
   * @param string $username Nom d'utilisateur MCP
   * @param string $sqlQuery Requête SQL à analyser
   * @return bool True si autorisé, false sinon
   */
  public function canExecuteSQL(string $username, string $sqlQuery): bool
  {
    try {
      $sqlQuery = strtoupper(trim($sqlQuery));

      // Déterminer le type de requête
      $queryType = $this->getSQLQueryType($sqlQuery);

      if (!$queryType) {
        McpSecurity::logSecurityEvent('SQL permission check failed - unknown query type', [
          'username' => $username,
          'query_start' => substr($sqlQuery, 0, 50)
        ]);
        return false;
      }

      // Vérifier la permission correspondante
      $permission = match ($queryType) {
        'SELECT' => 'select_data',
        'UPDATE' => 'update_data',
        'INSERT' => 'create_data',
        'DELETE' => 'delete_data',
        'CREATE', 'DROP', 'ALTER' => 'create_db',
        default => null
      };

      if (!$permission) {
        return false;
      }

      $hasPermission = $this->hasPermission($username, $permission);

      McpSecurity::logSecurityEvent('SQL permission check', [
        'username' => $username,
        'query_type' => $queryType,
        'permission' => $permission,
        'granted' => $hasPermission
      ]);

      return $hasPermission;

    } catch (\Exception $e) {
      McpSecurity::logSecurityEvent('SQL permission check error', [
        'username' => $username,
        'error' => $e->getMessage()
      ]);
      return false;
    }
  }

  /**
   * Détermine le type d'une requête SQL
   *
   * @param string $sqlQuery Requête SQL
   * @return string|null Type de requête ou null si non reconnu
   */
  private function getSQLQueryType(string $sqlQuery): ?string
  {
    $sqlQuery = trim($sqlQuery);

    if (preg_match('/^SELECT\s+/i', $sqlQuery)) {
      return 'SELECT';
    }
    if (preg_match('/^UPDATE\s+/i', $sqlQuery)) {
      return 'UPDATE';
    }
    if (preg_match('/^INSERT\s+/i', $sqlQuery)) {
      return 'INSERT';
    }
    if (preg_match('/^DELETE\s+/i', $sqlQuery)) {
      return 'DELETE';
    }
    if (preg_match('/^CREATE\s+/i', $sqlQuery)) {
      return 'CREATE';
    }
    if (preg_match('/^DROP\s+/i', $sqlQuery)) {
      return 'DROP';
    }
    if (preg_match('/^ALTER\s+/i', $sqlQuery)) {
      return 'ALTER';
    }

    return null;
  }

  /**
   * Obtient la liste des permissions disponibles pour un utilisateur
   *
   * @param string $username Nom d'utilisateur MCP
   * @return array Liste des permissions accordées
   */
  public function getGrantedPermissions(string $username): array
  {
    $permissions = $this->getUserPermissions($username);

    if (!$permissions || !$permissions['status']) {
      return [];
    }

    $granted = [];

    if ($permissions['select_data']) {
      $granted[] = 'select_data';
    }
    if ($permissions['update_data']) {
      $granted[] = 'update_data';
    }
    if ($permissions['create_data']) {
      $granted[] = 'create_data';
    }
    if ($permissions['delete_data']) {
      $granted[] = 'delete_data';
    }
    if ($permissions['create_db']) {
      $granted[] = 'create_db';
    }

    // Permissions composées
    if ($permissions['select_data'] && !$permissions['update_data'] && !$permissions['create_data'] && !$permissions['delete_data']) {
      $granted[] = 'read_only';
    }
    if ($permissions['select_data'] && $permissions['update_data']) {
      $granted[] = 'read_write';
    }
    if ($permissions['select_data'] && $permissions['update_data'] && $permissions['create_data'] && $permissions['delete_data']) {
      $granted[] = 'full_access';
    }
    if ($permissions['create_db']) {
      $granted[] = 'admin';
    }

    return $granted;
  }

  /**
   * Vide le cache des permissions
   */
  public function clearPermissionsCache(): void
  {
    self::$permissionsCache = null;
  }

  /**
   * Generic endpoint permission evaluator.
   *
   * Endpoint-specific permission classes (e.g. CustomerOrdersPermissions,
   * AnthropicEcommercePermissions) own their action whitelists and pass them
   * in. This class stays endpoint-agnostic: no switch on context names, no need
   * to edit this file when a new endpoint is added.
   *
   * @param string $username       Authenticated MCP username.
   * @param string $action         Requested action.
   * @param string[] $readActions  Actions requiring only select_data.
   * @param array<string, string[]> $writeActions Map of action => required permission flag(s);
   *                                              the user must have select_data + ANY-OF those flags.
   *                                              Valid flag names: select_data, create_data, update_data,
   *                                              delete_data, create_db.
   * @return bool
   */
  public function evaluateEndpointAction(
    string $username,
    string $action,
    array $readActions,
    array $writeActions
  ): bool {
    $permissions = $this->getUserPermissions($username);
    if (!$permissions || (int)$permissions['status'] !== 1) {
      McpSecurity::logSecurityEvent('Endpoint permission denied - user not found or inactive', [
        'username' => $username,
        'action'   => $action,
      ]);
      return false;
    }

    if (in_array($action, $readActions, true)) {
      $allowed = (int)$permissions['select_data'] === 1;
      if (!$allowed) {
        McpSecurity::logSecurityEvent('Endpoint permission denied - missing select_data', [
          'username' => $username,
          'action'   => $action,
        ]);
      }
      return $allowed;
    }

    if (array_key_exists($action, $writeActions)) {
      if ((int)$permissions['select_data'] !== 1) {
        McpSecurity::logSecurityEvent('Endpoint permission denied - missing select_data on write', [
          'username' => $username,
          'action'   => $action,
        ]);
        return false;
      }

      foreach ($writeActions[$action] as $flag) {
        if (!empty($permissions[$flag]) && (int)$permissions[$flag] === 1) {
          return true;
        }
      }

      McpSecurity::logSecurityEvent('Endpoint permission denied - missing required write flag', [
        'username'        => $username,
        'action'          => $action,
        'required_any_of' => $writeActions[$action],
      ]);
      return false;
    }

    McpSecurity::logSecurityEvent('Endpoint permission denied - action not in whitelist', [
      'username' => $username,
      'action'   => $action,
    ]);
    return false;
  }

}