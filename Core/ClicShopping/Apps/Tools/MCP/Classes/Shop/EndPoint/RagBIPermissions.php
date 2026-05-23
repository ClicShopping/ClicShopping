<?php
/**
 * Classe spécialisée pour les permissions RAG-BI
 * Implémente un contrôle strict des accès aux données pour les analyses
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\Shop\EndPoint;

use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\McpPermissions;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\McpSecurity;
use ClicShopping\OM\Registry;

/**
 * Classe de gestion des permissions spécifiques au RAG-BI
 * Contrôle strict des accès aux données sensibles pour les analyses
 */

class RagBIPermissions
{
    private mixed $db;
    private mixed $mcpPermissions;
    
    // Tables autorisées pour RAG-BI (lecture seule)
    private const ALLOWED_TABLES = [
        'clic_products',
        'clic_products_description',
        'clic_categories',
        'clic_categories_description',
        'clic_orders',
        'clic_orders_products',
        'clic_customers',
        'clic_products_attributes',
        'clic_manufacturers',
        'clic_reviews',
        'clic_specials',
        'clic_products_notifications'
    ];

    // Tables interdites (données sensibles)
    private const FORBIDDEN_TABLES = [
        'clic_administrators',
        'clic_mcp',
        'clic_mcp_session',
        'clic_customers_info', // Données personnelles sensibles
        'clic_address_book',   // Adresses personnelles
        'clic_sessions',       // Sessions utilisateurs
        'clic_configuration'   // Configuration système
    ];

    // Actions autorisées pour RAG-BI
    private const ALLOWED_ACTIONS = [
        'analyze_sales',
        'product_analytics',
        'customer_insights',
        'inventory_report',
        'performance_metrics',
        'trend_analysis',
        'category_analysis',
        'revenue_analysis',
        'search_analytics',
        'export_report'
    ];

    public function __construct()
    {
        $this->db = Registry::get('Db');
        
        if (!Registry::exists('McpPermissions')) {
            Registry::set('McpPermissions', new McpPermissions());
        }
        $this->mcpPermissions = Registry::get('McpPermissions');
    }

    /**
     * Vérifie si une action RAG-BI est autorisée
     *
     * @param string $username Nom d'utilisateur MCP
     * @param string $action Action demandée
     * @return bool True si autorisé, false sinon
     */
    public function canPerformRagBIAction(string $username, string $action): bool
    {
        if (!$this->canAccessRagBI($username)) {
            return false;
        }

        if (!in_array($action, self::ALLOWED_ACTIONS, true)) {
            McpSecurity::logSecurityEvent('RAG-BI action denied - action not in whitelist', [
                'username' => $username,
                'action' => $action,
                'allowed_actions' => self::ALLOWED_ACTIONS
            ]);
            return false;
        }

        return true;
    }

    /**
     * Vérifie si un utilisateur peut accéder au système RAG-BI
     *
     * @param string $username Nom d'utilisateur MCP
     * @return bool True si autorisé, false sinon
     */
  public function canAccessRagBI(string $username): bool
  {
    // 1. Vérifier la permission de contexte générale (si cette méthode existe dans McpPermissions)
    // NOTE: Si 'canAccessContext' n'existe pas, vous devrez la remplacer par 'hasPermission($username, 'ragbi_access')'
    if (!$this->mcpPermissions->canAccessContext($username, 'ragbi')) {
      return false;
    }

    // 2. Récupérer TOUTES les permissions de l'utilisateur (méthode existante dans McpPermissions)
    $permissions = $this->mcpPermissions->getUserPermissions($username);

    if (!$permissions) {
      return false;
    }

    // 3. Vérification stricte pour le RAG-BI (lecture seule analytique)
    // RAG-BI doit avoir select_data mais PAS les autres permissions (sécurité)
    // Cette logique est CORRECTE, mais elle utilise les clés des permissions globales.
    $hasSelectOnly = (bool) $permissions['select_data'] &&
      !(bool) $permissions['update_data'] &&
      !(bool) $permissions['create_data'] &&
      !(bool) $permissions['delete_data'] &&
      !(bool) $permissions['create_db'];

    if (!$hasSelectOnly) {
      McpSecurity::logSecurityEvent('RAG-BI access denied - user has excessive permissions', [
        'username' => $username,
        // Ne pas loguer toute la clé au complet, mais seulement les permissions critiques
        'permissions_check' => [
          'select_data' => (bool) $permissions['select_data'],
          'update_data' => (bool) $permissions['update_data'],
          'create_data' => (bool) $permissions['create_data'],
        ]
      ]);
      return false;
    }

    return true;
  }

    /**
     * Vérifie si une requête SQL est autorisée pour RAG-BI
     *
     * @param string $username Nom d'utilisateur MCP
     * @param string $sqlQuery Requête SQL à valider
     * @return bool True si autorisée, false sinon
     */
    public function canExecuteRagBIQuery(string $username, string $sqlQuery): bool
    {
        if (!$this->canAccessRagBI($username)) {
            return false;
        }

        // Nettoyer et analyser la requête
        $cleanQuery = strtoupper(trim($sqlQuery));
        
        // Seules les requêtes SELECT sont autorisées
        if (!preg_match('/^SELECT\s+/', $cleanQuery)) {
            McpSecurity::logSecurityEvent('RAG-BI SQL denied - not a SELECT query', [
                'username' => $username,
                'query_type' => $this->getSQLQueryType($cleanQuery)
            ]);
            return false;
        }

        // Vérifier les tables utilisées
        $tablesInQuery = $this->extractTablesFromQuery($cleanQuery);
        
        foreach ($tablesInQuery as $table) {
            if (!$this->isTableAllowedForRagBI($table)) {
                McpSecurity::logSecurityEvent('RAG-BI SQL denied - forbidden table access', [
                    'username' => $username,
                    'forbidden_table' => $table,
                    'query_tables' => $tablesInQuery
                ]);
                return false;
            }
        }

        // Vérifier qu'il n'y a pas de fonctions dangereuses
        if ($this->containsDangerousFunctions($cleanQuery)) {
            McpSecurity::logSecurityEvent('RAG-BI SQL denied - dangerous functions detected', [
                'username' => $username,
                'query_start' => substr($cleanQuery, 0, 100)
            ]);
            return false;
        }

        return true;
    }

    /**
     * Détermine le type d'une requête SQL
     *
     * @param string $query Requête SQL
     * @return string Type de requête
     */
    private function getSQLQueryType(string $query): string
    {
        $query = strtoupper(trim($query));

        if (preg_match('/^SELECT\s+/', $query)) return 'SELECT';
        if (preg_match('/^UPDATE\s+/', $query)) return 'UPDATE';
        if (preg_match('/^INSERT\s+/', $query)) return 'INSERT';
        if (preg_match('/^DELETE\s+/', $query)) return 'DELETE';
        if (preg_match('/^CREATE\s+/', $query)) return 'CREATE';
        if (preg_match('/^DROP\s+/', $query)) return 'DROP';
        if (preg_match('/^ALTER\s+/', $query)) return 'ALTER';

        return 'UNKNOWN';
    }

    /**
     * Extrait les noms de tables d'une requête SQL
     *
     * @param string $query Requête SQL
     * @return array Liste des tables trouvées
     */
    private function extractTablesFromQuery(string $query): array
    {
        $tables = [];
        
        // Pattern pour extraire les tables après FROM et JOIN
        $patterns = [
            '/FROM\s+([a-zA-Z_][a-zA-Z0-9_]*)/i',
            '/JOIN\s+([a-zA-Z_][a-zA-Z0-9_]*)/i',
            '/UPDATE\s+([a-zA-Z_][a-zA-Z0-9_]*)/i',
            '/INSERT\s+INTO\s+([a-zA-Z_][a-zA-Z0-9_]*)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $query, $matches)) {
                $tables = array_merge($tables, $matches[1]);
            }
        }

        return array_unique($tables);
    }

    /**
     * Vérifie si une table est autorisée pour RAG-BI
     *
     * @param string $tableName Nom de la table
     * @return bool True si autorisée, false sinon
     */
    private function isTableAllowedForRagBI(string $tableName): bool
    {
        // Supprimer le préfixe si présent
        $cleanTableName = str_replace('clic_', '', strtolower($tableName));
        $fullTableName = 'clic_' . $cleanTableName;

        // Vérifier si la table est explicitement interdite
        if (in_array($fullTableName, self::FORBIDDEN_TABLES, true)) {
            return false;
        }

        // Vérifier si la table est dans la liste autorisée
        return in_array($fullTableName, self::ALLOWED_TABLES, true);
    }

    /**
     * Vérifie si la requête contient des fonctions dangereuses
     *
     * @param string $query Requête SQL
     * @return bool True si des fonctions dangereuses sont détectées
     */
    private function containsDangerousFunctions(string $query): bool
    {
        $dangerousFunctions = [
            'LOAD_FILE',
            'INTO OUTFILE',
            'INTO DUMPFILE',
            'SYSTEM',
            'EXEC',
            'BENCHMARK',
            'SLEEP',
            'USER()',
            'VERSION()',
            'DATABASE()',
            'SCHEMA()'
        ];

        foreach ($dangerousFunctions as $function) {
            if (stripos($query, $function) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtient la liste des tables autorisées pour RAG-BI
     *
     * @return array Liste des tables autorisées
     */
    public function getAllowedTables(): array
    {
        return self::ALLOWED_TABLES;
    }

    /**
     * Obtient la liste des actions autorisées pour RAG-BI
     *
     * @return array Liste des actions autorisées
     */
    public function getAllowedActions(): array
    {
        return self::ALLOWED_ACTIONS;
    }

    /**
     * Génère un rapport de sécurité pour un utilisateur RAG-BI
     *
     * @param string $username Nom d'utilisateur MCP
     * @return array Rapport de sécurité
     */
    public function generateSecurityReport(string $username): array
    {
        $permissions = $this->mcpPermissions->getUserPermissionsForEndpoint($username);
        
        return [
            'username' => $username,
            'ragbi_access' => $this->canAccessRagBI($username),
            'permissions' => $permissions,
            'allowed_tables' => self::ALLOWED_TABLES,
            'forbidden_tables' => self::FORBIDDEN_TABLES,
            'allowed_actions' => self::ALLOWED_ACTIONS,
            'security_level' => 'READ_ONLY_ANALYTICS',
            'restrictions' => [
                'only_select_queries' => true,
                'table_whitelist_enforced' => true,
                'dangerous_functions_blocked' => true,
                'no_write_permissions' => true
            ]
        ];
    }
}