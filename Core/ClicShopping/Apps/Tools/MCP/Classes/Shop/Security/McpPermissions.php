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
   * Vérifie si un utilisateur peut accéder à un contexte spécifique
   * Basé sur les permissions réelles de la table clic_mcp
   *
   * @param string $username Nom d'utilisateur MCP
   * @param string $context Contexte d'accès (ragbi, customer_products, admin)
   * @return bool True si autorisé, false sinon
   */
  public function canAccessContext(string $username, string $context): bool
  {
    try {
      // Récupérer les permissions réelles de la base de données
      $permissions = $this->getUserPermissions($username);

      if (!$permissions || !$permissions['status']) {
        McpSecurity::logSecurityEvent('Context access denied - user not found or inactive', [
          'username' => $username,
          'context' => $context
        ]);
        return false;
      }

      $canAccess = match ($context) {
        'ragbi' => $this->checkRagBIAccess($permissions),
        'customer_products' => $this->checkCustomerProductsAccess($permissions),
        'admin' => $this->checkAdminAccess($permissions),
        default => false
      };

      McpSecurity::logSecurityEvent('Context access check', [
        'username' => $username,
        'context' => $context,
        'granted' => $canAccess,
        'permissions' => [
          'select_data' => (bool) $permissions['select_data'],
          'update_data' => (bool) $permissions['update_data'],
          'create_data' => (bool) $permissions['create_data'],
          'delete_data' => (bool) $permissions['delete_data'],
          'create_db' => (bool) $permissions['create_db']
        ]
      ]);

      return $canAccess;

    } catch (\Exception $e) {
      McpSecurity::logSecurityEvent('Context access check error', [
        'username' => $username,
        'context' => $context,
        'error' => $e->getMessage()
      ]);
      return false;
    }
  }

  /**
   * Vérifie l'accès RAG-BI (nécessite au minimum select_data)
   *
   * @param array $permissions Permissions de l'utilisateur
   * @return bool True si autorisé
   */
  private function checkRagBIAccess(array $permissions): bool
  {
    // RAG-BI nécessite au minimum la lecture des données
    return (bool) $permissions['select_data'];
  }

  /**
   * Vérifie l'accès Customer Products (flexible selon les actions)
   *
   * @param array $permissions Permissions de l'utilisateur
   * @return bool True si autorisé
   */
  private function checkCustomerProductsAccess(array $permissions): bool
  {
    // Customer Products nécessite au minimum la lecture
    // L'écriture est vérifiée au niveau des actions spécifiques
    return (bool) $permissions['select_data'];
  }

  /**
   * Vérifie l'accès Admin (nécessite create_db ou tous les droits)
   *
   * @param array $permissions Permissions de l'utilisateur
   * @return bool True si autorisé
   */
  private function checkAdminAccess(array $permissions): bool
  {
    // Admin nécessite soit create_db, soit tous les autres droits
    return (bool) $permissions['create_db'] ||
      ((bool) $permissions['select_data'] &&
        (bool) $permissions['update_data'] &&
        (bool) $permissions['create_data'] &&
        (bool) $permissions['delete_data']);
  }

  /**
   * Alias de canPerformAction pour une vérification de permission plus sémantique
   * dans les Endpoints (CustomersProducts.php).
   *
   * @param string $username Nom d'utilisateur MCP
   * @param string $context Contexte (ragbi, CustomerProducts, admin)
   * @param string $action Action demandée
   * @return bool True si autorisé, false sinon
   */
  public function hasPermissionForEndpoint(string $username, string $context, string $action): bool
  {
    // La classe CustomersProducts utilise 'CustomerProducts' comme contexte.
    // Assurons-nous qu'elle corresponde à 'customer_products' en interne.
    $internalContext = strtolower($context);
    if ($internalContext === 'customerproducts') {
      $internalContext = 'customer_products';
    }

    return $this->canPerformAction($username, $internalContext, $action);
  }

  /**
   * Vérifie si un utilisateur peut effectuer une action spécifique dans un contexte
   *
   * @param string $username Nom d'utilisateur MCP
   * @param string $context Contexte (ragbi, customer_products, admin)
   * @param string $action Action demandée
   * @return bool True si autorisé, false sinon
   */
  public function canPerformAction(string $username, string $context, string $action): bool
  {
    // Vérifier d'abord l'accès au contexte
    if (!$this->canAccessContext($username, $context)) {
      return false;
    }

    $permissions = $this->getUserPermissions($username);
    if (!$permissions) {
      return false;
    }

    return match ($context) {
      'ragbi' => $this->checkRagBIAction($permissions, $action),
      'customer_products' => $this->checkCustomerProductsAction($permissions, $action),
      'admin' => $this->checkAdminAction($permissions, $action),
      default => false
    };
  }



  /**
   * Vérifie les actions autorisées pour RAG-BI
   */
  private function checkRagBIAction(array $permissions, string $action): bool
  {
    return match ($action) {
      'query', 'analyze', 'search', 'report' => (bool) $permissions['select_data'],
      default => false
    };
  }


  /**
   * Vérifie les actions autorisées pour Customer Products
   */
  private function checkCustomerProductsAction(array $permissions, string $action): bool
  {
    return match ($action) {
      'products', 'product', 'categories', 'search', 'stats', 'recommendations' => (bool) $permissions['select_data'],
      'update_product', 'update_stock', 'update_price' => (bool) $permissions['update_data'],
      'create_product', 'add_product' => (bool) $permissions['create_data'],
      'delete_product', 'remove_product' => (bool) $permissions['delete_data'],
      default => (bool) $permissions['select_data'] // Par défaut, lecture seule
    };
  }

  /**
   * Vérifie les actions autorisées pour Admin
   */
  private function checkAdminAction(array $permissions, string $action): bool
  {
    return match ($action) {
      'view', 'list', 'search' => (bool) $permissions['select_data'],
      'update', 'modify', 'edit' => (bool) $permissions['update_data'],
      'create', 'add', 'insert' => (bool) $permissions['create_data'],
      'delete', 'remove', 'drop' => (bool) $permissions['delete_data'],
      'create_db', 'manage_db' => (bool) $permissions['create_db'],
      default => false
    };
  }
}