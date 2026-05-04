<?php
/**
 * AuthorizationManager
 *
 * Manages authorization verification for autonomous agent actions including
 * objective creation, evaluation, and role-based access control.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\OM\Registry;
use Exception;

class AuthorizationManager
{
  private $db;
  private array $agentRoles = [];
  private array $rolePermissions = [];
  
  /**
   * Constructor
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->initializeRolePermissions();
  }
  
  /**
   * Initialize default role permissions
   */
  private function initializeRolePermissions(): void
  {
    // Define default role permissions
    $this->rolePermissions = [
      'admin' => [
        'create_objective' => true,
        'evaluate_output' => true,
        'approve_objective' => true,
        'cancel_objective' => true,
        'modify_permissions' => true
      ],
      'autonomous_agent' => [
        'create_objective' => true,
        'evaluate_output' => true,
        'approve_objective' => false,
        'cancel_objective' => false,
        'modify_permissions' => false
      ],
      'evaluator_agent' => [
        'create_objective' => false,
        'evaluate_output' => true,
        'approve_objective' => false,
        'cancel_objective' => false,
        'modify_permissions' => false
      ],
      'read_only_agent' => [
        'create_objective' => false,
        'evaluate_output' => false,
        'approve_objective' => false,
        'cancel_objective' => false,
        'modify_permissions' => false
      ]
    ];
  }
  
  /**
   * Verify authorization for objective creation
   *
   * @param string $agentId Agent identifier
   * @param array $objectiveScope Scope of the objective
   * @return bool True if authorized
   * @throws Exception If authorization check fails
   */
  public function verifyObjectiveCreationAuth(string $agentId, array $objectiveScope): bool
  {
    try {
      // Get agent role
      $role = $this->getAgentRole($agentId);
      
      // Check if role has objective creation permission
      if (!$this->hasPermission($role, 'create_objective')) {
        return false;
      }
      
      // Check scope-specific permissions
      if (isset($objectiveScope['domain'])) {
        return $this->checkDomainPermission($agentId, $objectiveScope['domain'], 'create');
      }
      
      return true;
    } catch (Exception $e) {
      throw new Exception("Authorization verification failed: " . $e->getMessage());
    }
  }
  
  /**
   * Verify authorization for evaluation
   *
   * @param string $evaluatorAgentId Evaluator agent identifier
   * @param string $outputType Type of output being evaluated
   * @return bool True if authorized
   * @throws Exception If authorization check fails
   */
  public function verifyEvaluationAuth(string $evaluatorAgentId, string $outputType): bool
  {
    try {
      // Get agent role
      $role = $this->getAgentRole($evaluatorAgentId);
      
      // Check if role has evaluation permission
      if (!$this->hasPermission($role, 'evaluate_output')) {
        return false;
      }
      
      // Check if agent has capability for this output type
      $capabilityRegistry = new AgentCapabilityRegistry();
      return $capabilityRegistry->hasCapability($evaluatorAgentId, $outputType);
    } catch (Exception $e) {
      throw new Exception("Evaluation authorization verification failed: " . $e->getMessage());
    }
  }
  
  /**
   * Get agent role
   *
   * @param string $agentId Agent identifier
   * @return string Role name
   */
  public function getAgentRole(string $agentId): string
  {
    // Check cache first
    if (isset($this->agentRoles[$agentId])) {
      return $this->agentRoles[$agentId];
    }
    
    // Query database for agent role
    $Qrole = $this->db->prepare('
      SELECT role
      FROM :table_rag_agent_roles
      WHERE agent_id = :agent_id
    ');
    $Qrole->bindValue(':agent_id', $agentId);
    $Qrole->execute();
    
    if ($Qrole->fetch()) {
      $role = $Qrole->value('role');
      $this->agentRoles[$agentId] = $role;
      return $role;
    }
    
    // Default to read_only if no role found
    $this->agentRoles[$agentId] = 'read_only_agent';
    return 'read_only_agent';
  }
  
  /**
   * Set agent role
   *
   * @param string $agentId Agent identifier
   * @param string $role Role name
   * @return bool True if successful
   */
  public function setAgentRole(string $agentId, string $role): bool
  {
    try {
      // Validate role exists
      if (!isset($this->rolePermissions[$role])) {
        throw new Exception("Invalid role: $role");
      }
      
      // Check if role already exists
      $Qcheck = $this->db->prepare('
        SELECT COUNT(*) as count
        FROM :table_rag_agent_roles
        WHERE agent_id = :agent_id
      ');
      $Qcheck->bindValue(':agent_id', $agentId);
      $Qcheck->execute();
      
      $exists = $Qcheck->fetch() && $Qcheck->valueInt('count') > 0;
      
      if ($exists) {
        // Update existing role
        $Qupdate = $this->db->prepare('
          UPDATE :table_rag_agent_roles
          SET role = :role,
              updated_at = NOW()
          WHERE agent_id = :agent_id
        ');
        $Qupdate->bindValue(':agent_id', $agentId);
        $Qupdate->bindValue(':role', $role);
        $Qupdate->execute();
      } else {
        // Insert new role
        $Qinsert = $this->db->prepare('
          INSERT INTO :table_rag_agent_roles
          (agent_id, role, created_at)
          VALUES
          (:agent_id, :role, NOW())
        ');
        $Qinsert->bindValue(':agent_id', $agentId);
        $Qinsert->bindValue(':role', $role);
        $Qinsert->execute();
      }
      
      // Update cache
      $this->agentRoles[$agentId] = $role;
      
      return true;
    } catch (Exception $e) {
      error_log("Failed to set agent role: " . $e->getMessage());
      return false;
    }
  }
  
  /**
   * Check if role has specific permission
   *
   * @param string $role Role name
   * @param string $permission Permission name
   * @return bool True if role has permission
   */
  public function hasPermission(string $role, string $permission): bool
  {
    return isset($this->rolePermissions[$role][$permission]) 
           && $this->rolePermissions[$role][$permission] === true;
  }
  
  /**
   * Check domain-specific permission
   *
   * @param string $agentId Agent identifier
   * @param string $domain Domain name
   * @param string $action Action type (create, read, update, delete)
   * @return bool True if authorized
   */
  private function checkDomainPermission(string $agentId, string $domain, string $action): bool
  {
    $Qperm = $this->db->prepare('
      SELECT permission_level
      FROM :table_rag_agent_domain_permissions
      WHERE agent_id = :agent_id
        AND domain = :domain
    ');
    $Qperm->bindValue(':agent_id', $agentId);
    $Qperm->bindValue(':domain', $domain);
    $Qperm->execute();
    
    if (!$Qperm->fetch()) {
      // No specific permission, deny by default
      return false;
    }
    
    $permissionLevel = $Qperm->value('permission_level');
    
    // Check if permission level allows the action
    switch ($permissionLevel) {
      case 'full':
        return true;
      case 'write':
        return in_array($action, ['create', 'read', 'update'], true);
      case 'read':
        return $action === 'read';
      default:
        return false;
    }
  }
  
  /**
   * Grant domain permission to agent
   *
   * @param string $agentId Agent identifier
   * @param string $domain Domain name
   * @param string $permissionLevel Permission level (read, write, full)
   * @return bool True if successful
   */
  public function grantDomainPermission(string $agentId, string $domain, string $permissionLevel): bool
  {
    try {
      $validLevels = ['read', 'write', 'full'];
      if (!in_array($permissionLevel, $validLevels, true)) {
        throw new Exception("Invalid permission level: $permissionLevel");
      }
      
      // Check if permission already exists
      $Qcheck = $this->db->prepare('
        SELECT COUNT(*) as count
        FROM :table_rag_agent_domain_permissions
        WHERE agent_id = :agent_id
          AND domain = :domain
      ');
      $Qcheck->bindValue(':agent_id', $agentId);
      $Qcheck->bindValue(':domain', $domain);
      $Qcheck->execute();
      
      $exists = $Qcheck->fetch() && $Qcheck->valueInt('count') > 0;
      
      if ($exists) {
        // Update existing permission
        $Qupdate = $this->db->prepare('
          UPDATE :table_rag_agent_domain_permissions
          SET permission_level = :permission_level,
              updated_at = NOW()
          WHERE agent_id = :agent_id
            AND domain = :domain
        ');
        $Qupdate->bindValue(':agent_id', $agentId);
        $Qupdate->bindValue(':domain', $domain);
        $Qupdate->bindValue(':permission_level', $permissionLevel);
        $Qupdate->execute();
      } else {
        // Insert new permission
        $Qinsert = $this->db->prepare('
          INSERT INTO :table_rag_agent_domain_permissions
          (agent_id, domain, permission_level, created_at)
          VALUES
          (:agent_id, :domain, :permission_level, NOW())
        ');
        $Qinsert->bindValue(':agent_id', $agentId);
        $Qinsert->bindValue(':domain', $domain);
        $Qinsert->bindValue(':permission_level', $permissionLevel);
        $Qinsert->execute();
      }
      
      return true;
    } catch (Exception $e) {
      error_log("Failed to grant domain permission: " . $e->getMessage());
      return false;
    }
  }
  
  /**
   * Revoke domain permission from agent
   *
   * @param string $agentId Agent identifier
   * @param string $domain Domain name
   * @return bool True if successful
   */
  public function revokeDomainPermission(string $agentId, string $domain): bool
  {
    try {
      $Qdelete = $this->db->prepare('
        DELETE FROM :table_rag_agent_domain_permissions
        WHERE agent_id = :agent_id
          AND domain = :domain
      ');
      $Qdelete->bindValue(':agent_id', $agentId);
      $Qdelete->bindValue(':domain', $domain);
      $Qdelete->execute();
      
      return true;
    } catch (Exception $e) {
      error_log("Failed to revoke domain permission: " . $e->getMessage());
      return false;
    }
  }
  
  /**
   * Get all permissions for an agent
   *
   * @param string $agentId Agent identifier
   * @return array Array of permissions
   */
  public function getAgentPermissions(string $agentId): array
  {
    $permissions = [
      'role' => $this->getAgentRole($agentId),
      'role_permissions' => [],
      'domain_permissions' => []
    ];
    
    // Get role permissions
    $role = $permissions['role'];
    if (isset($this->rolePermissions[$role])) {
      $permissions['role_permissions'] = $this->rolePermissions[$role];
    }
    
    // Get domain permissions
    $Qdomains = $this->db->prepare('
      SELECT domain, permission_level
      FROM :table_rag_agent_domain_permissions
      WHERE agent_id = :agent_id
    ');
    $Qdomains->bindValue(':agent_id', $agentId);
    $Qdomains->execute();
    
    while ($Qdomains->fetch()) {
      $permissions['domain_permissions'][$Qdomains->value('domain')] = $Qdomains->value('permission_level');
    }
    
    return $permissions;
  }
}
