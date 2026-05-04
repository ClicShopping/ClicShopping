<?php
/**
 * BusinessDomainPermissionManager
 *
 * Manages agent permissions for accessing business domains. Controls what actions
 * agents can perform on business-specific entities, rules, and logic.
 *
 * Permission Levels:
 * - read_only: Can only read business domain data
 * - propose: Can read and propose changes (requires approval)
 * - execute_safe: Can read and execute safe operations
 * - execute_all: Can read and execute all operations
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\OM\Registry;
use Exception;

class BusinessDomainPermissionManager
{
  private $db;
  
  // Permission level constants
  const PERMISSION_READ_ONLY = 'read_only';
  const PERMISSION_PROPOSE = 'propose';
  const PERMISSION_EXECUTE_SAFE = 'execute_safe';
  const PERMISSION_EXECUTE_ALL = 'execute_all';
  
  // Valid permission levels
  private array $validPermissionLevels = [
    self::PERMISSION_READ_ONLY,
    self::PERMISSION_PROPOSE,
    self::PERMISSION_EXECUTE_SAFE,
    self::PERMISSION_EXECUTE_ALL
  ];
  
  // Actions that require approval
  private array $approvalRequiredActions = [
    'modify_business_rules',
    'delete_business_entity',
    'update_critical_data',
    'change_business_logic'
  ];
  
  /**
   * Constructor
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
  }
  
  /**
   * Check if agent has permission for a specific action on a business domain
   *
   * @param string $agentId Agent identifier
   * @param string $businessDomain Business domain name (e.g., 'Ecommerce', 'Finance', 'Hr')
   * @param string $action Action to perform (e.g., 'read', 'write', 'modify_rules')
   * @return bool True if agent has permission
   * @throws Exception If permission check fails
   */
  public function checkPermission(string $agentId, string $businessDomain, string $action): bool
  {
    $action = strtolower(trim($action));
    if ($action === '') {
      $this->logAccess($agentId, $businessDomain, $action, false);
      return false;
    }

    $businessDomain = $this->normalizeDomain($businessDomain);

    try {
      // Get agent's permission level for this domain
      $permissionLevel = $this->getAgentPermissionLevel($agentId, $businessDomain);
      
      // Check if action is allowed for this permission level
      $granted = $this->isActionAllowed($permissionLevel, $action);
      
      // Log the access attempt
      $this->logAccess($agentId, $businessDomain, $action, $granted);
      
      return $granted;
    } catch (Exception $e) {
      error_log("Permission check failed for agent $agentId on domain $businessDomain: " . $e->getMessage());
      // Log failed permission check
      $this->logAccess($agentId, $businessDomain, $action, false);
      throw new Exception("Permission check failed: " . $e->getMessage());
    }
  }
  
  /**
   * Get agent's permission level for a business domain
   *
   * @param string $agentId Agent identifier
   * @param string $businessDomain Business domain name
   * @return string Permission level (defaults to read_only if not set)
   */
  private function getAgentPermissionLevel(string $agentId, string $businessDomain): string
  {
    $businessDomain = $this->normalizeDomain($businessDomain);
    try {
      $Qperm = $this->db->prepare('
        SELECT permission_level
        FROM :table_rag_agent_business_domain_permissions
        WHERE agent_id = :agent_id
          AND business_domain = :business_domain
      ');
      $Qperm->bindValue(':agent_id', $agentId);
      $Qperm->bindValue(':business_domain', $businessDomain);
      $Qperm->execute();
      
      if ($Qperm->fetch()) {
        return $Qperm->value('permission_level');
      }
      
      // Default to read_only if no permission is set
      return self::PERMISSION_READ_ONLY;
    } catch (Exception $e) {
      error_log("Failed to get permission level: " . $e->getMessage());
      // Default to most restrictive permission on error
      return self::PERMISSION_READ_ONLY;
    }
  }
  
  /**
   * Check if an action is allowed for a given permission level
   *
   * @param string $permissionLevel Permission level
   * @param string $action Action to check
   * @return bool True if action is allowed
   */
  private function isActionAllowed(string $permissionLevel, string $action): bool
  {
    $action = strtolower(trim($action));

    // Define action mappings for each permission level
    $permissionActions = [
      self::PERMISSION_READ_ONLY => [
        'read',
        'view',
        'query',
        'list'
      ],
      self::PERMISSION_PROPOSE => [
        'read',
        'view',
        'query',
        'list',
        'propose',
        'propose_change',
        'suggest_modification'
      ],
      self::PERMISSION_EXECUTE_SAFE => [
        'read',
        'view',
        'query',
        'list',
        'create',
        'update',
        'execute_safe',
        'execute_safe_operation'
      ],
      self::PERMISSION_EXECUTE_ALL => [
        'read',
        'view',
        'query',
        'list',
        'create',
        'update',
        'delete',
        'execute',
        'execute_all',
        'execute_safe',
        'execute_safe_operation',
        'execute_all_operations',
        'modify_rules',
        'modify_business_rules'
      ]
    ];
    
    // Check if action is allowed for this permission level
    if (isset($permissionActions[$permissionLevel])) {
      return in_array($action, $permissionActions[$permissionLevel], true);
    }
    
    // If permission level is unknown, deny access
    return false;
  }
  
  /**
   * Get all permissions for an agent
   *
   * @param string $agentId Agent identifier
   * @return array Array of business domain permissions
   */
  public function getAgentPermissions(string $agentId): array
  {
    try {
      $permissions = [];
      
      $Qperms = $this->db->prepare('
        SELECT business_domain, permission_level, created_at, updated_at
        FROM :table_rag_agent_business_domain_permissions
        WHERE agent_id = :agent_id
        ORDER BY business_domain
      ');
      $Qperms->bindValue(':agent_id', $agentId);
      $Qperms->execute();
      
      while ($Qperms->fetch()) {
        $permissions[] = [
          'business_domain' => $Qperms->value('business_domain'),
          'permission_level' => $Qperms->value('permission_level'),
          'created_at' => $Qperms->value('created_at'),
          'updated_at' => $Qperms->value('updated_at')
        ];
      }
      
      return $permissions;
    } catch (Exception $e) {
      error_log("Failed to get agent permissions: " . $e->getMessage());
      return [];
    }
  }
  
  /**
   * Set agent permission for a business domain
   *
   * @param string $agentId Agent identifier
   * @param string $businessDomain Business domain name
   * @param string $permissionLevel Permission level
   * @return bool True if successful
   * @throws Exception If permission level is invalid
   */
  public function setAgentPermission(string $agentId, string $businessDomain, string $permissionLevel): bool
  {
    $businessDomain = $this->normalizeDomain($businessDomain);
    try {
      // Validate permission level
      if (!in_array($permissionLevel, $this->validPermissionLevels, true)) {
        throw new Exception("Invalid permission level: $permissionLevel. Must be one of: " . 
                          implode(', ', $this->validPermissionLevels));
      }
      
      // Check if permission already exists
      $Qcheck = $this->db->prepare('
        SELECT COUNT(*) as count
        FROM :table_rag_agent_business_domain_permissions
        WHERE agent_id = :agent_id
          AND business_domain = :business_domain
      ');
      $Qcheck->bindValue(':agent_id', $agentId);
      $Qcheck->bindValue(':business_domain', $businessDomain);
      $Qcheck->execute();
      
      $exists = $Qcheck->fetch() && $Qcheck->valueInt('count') > 0;
      
      if ($exists) {
        // Update existing permission
        $Qupdate = $this->db->prepare('
          UPDATE :table_rag_agent_business_domain_permissions
          SET permission_level = :permission_level,
              updated_at = NOW()
          WHERE agent_id = :agent_id
            AND business_domain = :business_domain
        ');
        $Qupdate->bindValue(':agent_id', $agentId);
        $Qupdate->bindValue(':business_domain', $businessDomain);
        $Qupdate->bindValue(':permission_level', $permissionLevel);
        $Qupdate->execute();
      } else {
        // Insert new permission
        $Qinsert = $this->db->prepare('
          INSERT INTO :table_rag_agent_business_domain_permissions
          (agent_id, business_domain, permission_level, created_at, updated_at)
          VALUES
          (:agent_id, :business_domain, :permission_level, NOW(), NOW())
        ');
        $Qinsert->bindValue(':agent_id', $agentId);
        $Qinsert->bindValue(':business_domain', $businessDomain);
        $Qinsert->bindValue(':permission_level', $permissionLevel);
        $Qinsert->execute();
      }
      
      return true;
    } catch (Exception $e) {
      error_log("Failed to set agent permission: " . $e->getMessage());
      throw $e;
    }
  }
  
  /**
   * Check if an action requires approval from orchestrator or human operator
   *
   * @param string $agentId Agent identifier
   * @param string $businessDomain Business domain name
   * @param string $action Action to perform
   * @return bool True if approval is required
   */
  public function requiresApproval(string $agentId, string $businessDomain, string $action): bool
  {
    try {
      $action = strtolower(trim($action));
      if ($action === '') {
        return true;
      }

      $businessDomain = $this->normalizeDomain($businessDomain);

      // Get agent's permission level
      $permissionLevel = $this->getAgentPermissionLevel($agentId, $businessDomain);
      
      // Actions that always require approval regardless of permission level
      if (in_array($action, $this->approvalRequiredActions, true)) {
        return true;
      }
      
      // Propose permission level always requires approval for write actions
      if ($permissionLevel === self::PERMISSION_PROPOSE) {
        $writeActions = ['create', 'update', 'delete', 'modify', 'propose'];
        foreach ($writeActions as $writeAction) {
          if (strpos($action, $writeAction) !== false) {
            return true;
          }
        }
      }
      
      // Execute_safe requires approval for non-safe operations
      if ($permissionLevel === self::PERMISSION_EXECUTE_SAFE) {
        $unsafeActions = ['delete', 'modify_business_rules', 'modify_rules', 'change_business_logic'];
        if (in_array($action, $unsafeActions, true)) {
          return true;
        }
      }
      
      return false;
    } catch (Exception $e) {
      error_log("Failed to check approval requirement: " . $e->getMessage());
      // Default to requiring approval on error (safer)
      return true;
    }
  }
  
  /**
   * Log agent access attempt to business domain
   *
   * @param string $agentId Agent identifier
   * @param string $businessDomain Business domain name
   * @param string $action Action attempted
   * @param bool $granted Whether access was granted
   * @return void
   */
  public function logAccess(string $agentId, string $businessDomain, string $action, bool $granted): void
  {
    $businessDomain = $this->normalizeDomain($businessDomain);
    try {
      $Qlog = $this->db->prepare('
        INSERT INTO :table_rag_agent_business_domain_access_log
        (agent_id, business_domain, action, granted, accessed_at)
        VALUES
        (:agent_id, :business_domain, :action, :granted, NOW())
      ');
      $Qlog->bindValue(':agent_id', $agentId);
      $Qlog->bindValue(':business_domain', $businessDomain);
      $Qlog->bindValue(':action', $action);
      $Qlog->bindInt(':granted', $granted ? 1 : 0);
      $Qlog->execute();
    } catch (Exception $e) {
      // Log to error log but don't throw exception
      // We don't want logging failures to break the main flow
      error_log("Failed to log business domain access: " . $e->getMessage());
    }
  }
  
  /**
   * Get access log for an agent
   *
   * @param string $agentId Agent identifier
   * @param int $limit Maximum number of records to return
   * @return array Array of access log entries
   */
  public function getAccessLog(string $agentId, int $limit = 100): array
  {
    try {
      $log = [];
      
      $Qlog = $this->db->prepare('
        SELECT business_domain, action, granted, accessed_at
        FROM :table_rag_agent_business_domain_access_log
        WHERE agent_id = :agent_id
        ORDER BY accessed_at DESC
        LIMIT :limit
      ');
      $Qlog->bindValue(':agent_id', $agentId);
      $Qlog->bindInt(':limit', $limit);
      $Qlog->execute();
      
      while ($Qlog->fetch()) {
        $log[] = [
          'business_domain' => $Qlog->value('business_domain'),
          'action' => $Qlog->value('action'),
          'granted' => $Qlog->valueInt('granted') === 1,
          'accessed_at' => $Qlog->value('accessed_at')
        ];
      }
      
      return $log;
    } catch (Exception $e) {
      error_log("Failed to get access log: " . $e->getMessage());
      return [];
    }
  }
  
  /**
   * Remove agent permission for a business domain
   *
   * @param string $agentId Agent identifier
   * @param string $businessDomain Business domain name
   * @return bool True if successful
   */
  public function removeAgentPermission(string $agentId, string $businessDomain): bool
  {
    $businessDomain = $this->normalizeDomain($businessDomain);
    try {
      $Qdelete = $this->db->prepare('
        DELETE FROM :table_rag_agent_business_domain_permissions
        WHERE agent_id = :agent_id
          AND business_domain = :business_domain
      ');
      $Qdelete->bindValue(':agent_id', $agentId);
      $Qdelete->bindValue(':business_domain', $businessDomain);
      $Qdelete->execute();
      
      return true;
    } catch (Exception $e) {
      error_log("Failed to remove agent permission: " . $e->getMessage());
      return false;
    }
  }
  
  /**
   * Get all agents with access to a specific business domain
   *
   * @param string $businessDomain Business domain name
   * @return array Array of agents with their permission levels
   */
  public function getAgentsWithDomainAccess(string $businessDomain): array
  {
    $businessDomain = $this->normalizeDomain($businessDomain);
    try {
      $agents = [];
      
      $Qagents = $this->db->prepare('
        SELECT agent_id, permission_level, created_at, updated_at
        FROM :table_rag_agent_business_domain_permissions
        WHERE business_domain = :business_domain
        ORDER BY agent_id
      ');
      $Qagents->bindValue(':business_domain', $businessDomain);
      $Qagents->execute();
      
      while ($Qagents->fetch()) {
        $agents[] = [
          'agent_id' => $Qagents->value('agent_id'),
          'permission_level' => $Qagents->value('permission_level'),
          'created_at' => $Qagents->value('created_at'),
          'updated_at' => $Qagents->value('updated_at')
        ];
      }
      
      return $agents;
    } catch (Exception $e) {
      error_log("Failed to get agents with domain access: " . $e->getMessage());
      return [];
    }
  }
  
  /**
   * Check if agent has any permission on any business domain
   *
   * @param string $agentId Agent identifier
   * @return bool True if agent has at least one permission
   */
  public function hasAnyPermission(string $agentId): bool
  {
    try {
      $Qcheck = $this->db->prepare('
        SELECT COUNT(*) as count
        FROM :table_rag_agent_business_domain_permissions
        WHERE agent_id = :agent_id
      ');
      $Qcheck->bindValue(':agent_id', $agentId);
      $Qcheck->execute();
      
      return $Qcheck->fetch() && $Qcheck->valueInt('count') > 0;
    } catch (Exception $e) {
      error_log("Failed to check if agent has any permission: " . $e->getMessage());
      return false;
    }
  }
  
  /**
   * Get valid permission levels
   *
   * @return array Array of valid permission level constants
   */
  public function getValidPermissionLevels(): array
  {
    return $this->validPermissionLevels;
  }

  /**
   * Normalize business domain keys for consistent storage and lookup.
   *
   * @param string $businessDomain
   * @return string
   */
  private function normalizeDomain(string $businessDomain): string
  {
    return strtolower(trim($businessDomain));
  }
}
