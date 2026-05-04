<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\OM\Domains;

use ClicShopping\OM\Domains\ConfigurableAppAbstract;
use ClicShopping\OM\Interfaces\DomainAppInterface;
use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\BusinessDomainPermissionManager;

/**
 * Abstract base class for Domain-specific ClicShopping AI applications
 * 
 * This class provides the foundation for multi-domain RAG (Retrieval-Augmented Generation)
 * systems where each domain (e.g., Ecommerce, HR, Finance) is implemented as a standard
 * ClicShopping App with domain-specific entities, guardrails, LLM prompts, and helpers.
 * 
 * Domain apps are discovered automatically via Apps::getAll() and registered with the
 * DomainRegistry. They support Pure LLM Mode for entity detection and query classification,
 * eliminating the need for pattern-based matching.
 * 
 * Key Features:
 * - Extends AbstractConfigurableApp for standard ClicShopping App functionality
 * - Implements DomainAppInterface for domain-specific operations
 * - Automatic registration with DomainRegistry on initialization
 * - Pure LLM Mode enabled by default (no pattern matching)
 * - Vendor fixed to 'AI' for all domain apps
 * - Support for domain-specific entities, guardrails, prompts, and helpers
 * 
 * Usage:
 * ```php
 * class Ecommerce extends AbstractDomainApp
 * {
 *     protected function init(): void
 *     {
 *         parent::init();
 *         // Additional domain-specific initialization
 *     }
 * 
 *     public function getDomainId(): string
 *     {
 *         return 'ecommerce';
 *     }
 * 
 *     public function getDomainName(): string
 *     {
 *         return 'E-Commerce';
 *     }
 * 
 *     // Implement other DomainAppInterface methods...
 * }
 * ```
 * 
 * @see ConfigurableAppAbstract
 * @see DomainAppInterface
 * @see \ClicShopping\AI\DomainRegistry
 */
abstract class AbstractDomainApp extends ConfigurableAppAbstract implements DomainAppInterface
{
  /**
   * Pure LLM Mode flag
   * 
   * When true, all entity detection and query classification is performed by the LLM
   * using prompts loaded directly by AI components via DomainConfig. Pattern-based 
   * matching is not used.
   * 
   * This is the recommended mode for all new domain implementations as it provides:
   * - More accurate entity detection across languages
   * - Better handling of ambiguous queries
   * - No need to maintain pattern libraries
   * - Easier to extend with new entities
   * - Consistent behavior across domains
   * 
   * @var bool
   */
  protected bool $pureLlmMode = true;

  /**
   * Initialize the domain app
   * 
   * This method is called by the parent constructor after basic initialization.
   * It registers the domain app with the DomainRegistry for discovery and management.
   * 
   * Child classes can override this method to add domain-specific initialization,
   * but MUST call parent::init() to ensure proper registration.
   * 
   * Note: The vendor is automatically set to 'AI' by the parent class based on the
   * namespace (ClicShopping\Apps\AI\DomainName). All domain apps MUST be placed under
   * Core/ClicShopping/Apps/AI/ to ensure proper vendor assignment.
   * 
   * @return void
   */
  protected function init(): void
  {
    // Register this domain app with the DomainRegistry
    // Note: DomainRegistry will be created in task 2.3
    // This registration allows the domain to be discovered and activated
    if (class_exists('ClicShopping\AI\DomainRegistry')) {
      $registry = \ClicShopping\AI\DomainRegistry::getInstance();
      $registry->registerApp($this);
    }
  }

  /**
   * Check if Pure LLM Mode is enabled for this domain
   * 
   * Returns true if the domain uses Pure LLM Mode for entity detection and query
   * classification. In Pure LLM Mode, all detection and classification is performed
   * by the LLM using prompts loaded directly by AI components via DomainConfig.
   * 
   * @return bool True if Pure LLM Mode is enabled (default), false for pattern-based mode
   */
  public function isPureLlmMode(): bool
  {
    return $this->pureLlmMode;
  }

  /**
   * Get the pattern classes for backward compatibility (optional)
   * 
   * Returns an empty array by default since Pure LLM Mode is enabled.
   * Pattern classes are NOT used when isPureLlmMode() returns true.
   * 
   * Child classes can override this method to provide pattern classes for:
   * - Backward compatibility with existing implementations
   * - Fallback when LLM is unavailable
   * - Testing and comparison purposes
   * 
   * @return array Empty array (pattern classes not used in Pure LLM Mode)
   */
  public function getPatternClasses(): array
  {
    return [];
  }

  /**
   * Get the unique domain identifier
   * 
   * Child classes MUST implement this method to return a unique domain identifier.
   * 
   * @return string The unique domain identifier (e.g., 'ecommerce', 'hr', 'finance')
   */
  abstract public function getDomainId(): string;

  /**
   * Get the human-readable domain name
   * 
   * Child classes MUST implement this method to return a user-friendly domain name.
   * 
   * @return string The human-readable domain name (e.g., 'E-Commerce', 'Human Resources')
   */
  abstract public function getDomainName(): string;

  /**
   * Get the entity configuration for this domain
   * 
   * Child classes MUST implement this method to return domain-specific entity definitions.
   * 
   * @return array Associative array of entity configurations keyed by entity name
   */
  abstract public function getEntityConfig(): array;

  abstract public function getHelpers(): array;

  /**
   * Check if an agent has permission to perform an action on this domain
   * 
   * This method integrates with the BusinessDomainPermissionManager to enforce
   * permission checks for autonomous agent access to business domains.
   * 
   * @param string $agentId Agent identifier
   * @param string $action Action to perform (read, write, modify_rules, etc.)
   * @return bool True if agent has permission
   * @throws \Exception If permission check fails
   */
  public function checkAgentPermission(string $agentId, string $action): bool
  {
    // Get BusinessDomainPermissionManager
    $permissionManager = $this->getBusinessDomainPermissionManager();
    
    // Check permission for this domain
    $hasPermission = $permissionManager->checkPermission(
      $agentId,
      $this->getDomainId(),
      $action
    );
    
    return $hasPermission;
  }

  /**
   * Check if an action requires approval from orchestrator or human operator
   * 
   * @param string $agentId Agent identifier
   * @param string $action Action to perform
   * @return bool True if approval is required
   */
  public function requiresApproval(string $agentId, string $action): bool
  {
    $permissionManager = $this->getBusinessDomainPermissionManager();
    
    return $permissionManager->requiresApproval(
      $agentId,
      $this->getDomainId(),
      $action
    );
  }

  /**
   * Get the BusinessDomainPermissionManager instance
   * 
   * @return \ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\BusinessDomainPermissionManager
   */
  protected function getBusinessDomainPermissionManager()
  {
    static $permissionManager = null;
    
    if ($permissionManager === null) {
      $permissionManager = new BusinessDomainPermissionManager();
    }
    
    return $permissionManager;
  }

  /**
   * Enforce permission check for an agent action
   * 
   * This method checks permissions and throws an exception if access is denied.
   * Use this method to enforce permission checks at domain access points.
   * 
   * @param string $agentId Agent identifier
   * @param string $action Action to perform
   * @throws \Exception If agent does not have permission
   * @return void
   */
  public function enforcePermission(string $agentId, string $action): void
  {
    if (!$this->checkAgentPermission($agentId, $action)) {
      throw new \Exception(
        "Agent '{$agentId}' does not have permission to perform action '{$action}' on domain '{$this->getDomainId()}'"
      );
    }
  }
}
