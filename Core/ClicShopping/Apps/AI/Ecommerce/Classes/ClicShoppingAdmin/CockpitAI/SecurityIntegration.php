<?php
/**
 * CockpitAI Security Integration
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\GuardrailsConfig;
use ClicShopping\OM\Registry;
use ClicShopping\OM\HTML;

/**
 * SecurityIntegration
 *
 * Integrates CockpitAI module with ClicShopping AI security system.
 * Implements Requirements 21.1, 21.3, 21.4, 21.5:
 * - User permission validation (21.1, 21.3)
 * - Input sanitization and validation (21.4)
 * - Audit logging via SecurityLogger (21.5)
 * - Guardrails integration for secure operations
 *
 * Security Layers:
 * 1. Permission Validation: Checks user access rights via AdministratorAdmin
 * 2. Input Sanitization: Validates and sanitizes all inputs (product_id, language_id)
 * 3. Audit Logging: Logs all analysis requests with user identification
 * 4. Guardrails: Enforces domain-specific security rules
 */
class SecurityIntegration
{
  private SecurityLogger $logger;
  private array $guardrailsConfig;
  private bool $debug;

  /**
   * Constructor
   *
   * Initializes security components and loads guardrails configuration.
   */
  public function __construct()
  {
    $this->logger = new SecurityLogger();
    $this->guardrailsConfig = GuardrailsConfig::getConfig();
    $this->debug = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG') && CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG === 'True';
  }

  /**
   * Validate user permissions before analysis trigger
   *
   * Requirements 21.1, 21.3:
   * - Checks if user is authenticated (session exists)
   * - Validates user has admin access rights
   * - Verifies user account is active (status = 1)
   *
   * @return array ['valid' => bool, 'user_id' => int|null, 'error' => string|null]
   */
  public function validateUserPermissions(): array
  {
    // Check if admin session exists
    if (!isset($_SESSION['admin']['id'], $_SESSION['admin']['access'])) {
      $this->logger->logStructured('warning', 'CockpitAI', 'permission_denied', [
        'reason' => 'no_session',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'timestamp' => date('Y-m-d H:i:s'),
      ]);

      return [
        'valid' => false,
        'user_id' => null,
        'error' => 'Access denied: No valid admin session'
      ];
    }

    // Validate user access via AdministratorAdmin
    $userId = AdministratorAdmin::getAdminIdByAccess();

    if (!$userId || $userId === 0) {
      $this->logger->logStructured('warning', 'CockpitAI', 'permission_denied', [
        'reason' => 'invalid_access',
        'session_id' => $_SESSION['admin']['id'] ?? 'unknown',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'timestamp' => date('Y-m-d H:i:s'),
      ]);

      return [
        'valid' => false,
        'user_id' => null,
        'error' => 'Access denied: Invalid administrator access'
      ];
    }

    // Log successful permission validation
    if ($this->debug) {
      $this->logger->logStructured('info', 'CockpitAI', 'permission_validated', [
        'user_id' => $userId,
        'user_name' => AdministratorAdmin::getUserAdmin(),
        'timestamp' => date('Y-m-d H:i:s'),
      ]);
    }

    return [
      'valid' => true,
      'user_id' => $userId,
      'error' => null
    ];
  }

  /**
   * Sanitize and validate input parameters
   *
   * Requirement 21.4:
   * - Validates product_id is a positive integer
   * - Validates language_id is a positive integer
   * - Sanitizes inputs to prevent injection attacks
   * - Verifies product exists in database
   * - Verifies language exists in database
   *
   * @param mixed $productId Product ID to validate
   * @param mixed $languageId Language ID to validate
   * @return array ['valid' => bool, 'product_id' => int|null, 'language_id' => int|null, 'error' => string|null]
   */
  public function sanitizeAndValidateInputs($productId, $languageId): array
  {
    $errors = [];

    // Validate product_id
    if (!is_numeric($productId) || $productId <= 0) {
      $errors[] = 'Invalid product_id: must be a positive integer';
      $productId = null;
    } else {
      $productId = (int)$productId;

      // Verify product exists
      if (!$this->productExists($productId)) {
        $errors[] = "Product ID {$productId} does not exist";
        $productId = null;
      }
    }

    // Validate language_id
    if (!is_numeric($languageId) || $languageId <= 0) {
      $errors[] = 'Invalid language_id: must be a positive integer';
      $languageId = null;
    } else {
      $languageId = (int)$languageId;

      // Verify language exists
      if (!$this->languageExists($languageId)) {
        $errors[] = "Language ID {$languageId} does not exist";
        $languageId = null;
      }
    }

    // Log validation failure
    if (!empty($errors)) {
      $this->logger->logStructured('warning', 'CockpitAI', 'input_validation_failed', [
        'errors' => $errors,
        'raw_product_id' => $productId,
        'raw_language_id' => $languageId,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'timestamp' => date('Y-m-d H:i:s'),
      ]);

      return [
        'valid' => false,
        'product_id' => null,
        'language_id' => null,
        'error' => implode('; ', $errors)
      ];
    }

    // Log successful validation
    if ($this->debug) {
      $this->logger->logStructured('info', 'CockpitAI', 'input_validated', [
        'product_id' => $productId,
        'language_id' => $languageId,
        'timestamp' => date('Y-m-d H:i:s'),
      ]);
    }

    return [
      'valid' => true,
      'product_id' => $productId,
      'language_id' => $languageId,
      'error' => null
    ];
  }

  /**
   * Log analysis request with user identification
   *
   * Requirement 21.5:
   * - Logs all analysis requests with user identification
   * - Includes product_id, language_id, timestamp
   * - Uses SecurityLogger for audit trail
   *
   * @param int $productId Product ID being analyzed
   * @param int $languageId Language ID for analysis
   * @param int $userId User ID triggering analysis
   * @param string $status Request status ('initiated', 'completed', 'failed')
   * @param array $additionalData Additional data to log
   * @return bool True if logged successfully
   */
  public function logAnalysisRequest(
    int $productId,
    int $languageId,
    int $userId,
    string $status,
    array $additionalData = []
  ): bool {
    $userName = AdministratorAdmin::getAdminNameById($userId);

    $logData = array_merge([
      'product_id' => $productId,
      'language_id' => $languageId,
      'user_id' => $userId,
      'user_name' => $userName,
      'status' => $status,
      'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
      'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
      'timestamp' => date('Y-m-d H:i:s'),
    ], $additionalData);

    return $this->logger->logStructured('info', 'CockpitAI', 'analysis_request', $logData);
  }

  /**
   * Validate operation against guardrails
   *
   * Checks if an operation is allowed by domain guardrails.
   * Used for future extensibility (e.g., batch operations, automated triggers).
   *
   * @param string $operation Operation to validate (e.g., 'SELECT', 'INSERT', 'UPDATE')
   * @return bool True if operation is allowed
   */
  public function isOperationAllowed(string $operation): bool
  {
    $allowedOperations = $this->guardrailsConfig['allowed_operations'] ?? [];
    return in_array(strtoupper($operation), $allowedOperations, true);
  }

  /**
   * Check if product exists in database
   *
   * @param int $productId Product ID to check
   * @return bool True if product exists
   */
  private function productExists(int $productId): bool
  {
    try {
      $db = Registry::get('Db');

      $query = $db->prepare('SELECT products_id 
                             FROM :table_products 
                             WHERE products_id = :product_id 
                             LIMIT 1');
      $query->bindInt(':product_id', $productId);
      $query->execute();

      return $query->fetch() !== false;
    } catch (\Exception $e) {
      $this->logger->logError('Failed to check product existence: ' . $e->getMessage(), [
        'product_id' => $productId
      ]);
      return false;
    }
  }

  /**
   * Check if language exists in database
   *
   * @param int $languageId Language ID to check
   * @return bool True if language exists
   */
  private function languageExists(int $languageId): bool
  {
    try {
      $db = Registry::get('Db');

      $query = $db->prepare('SELECT languages_id 
                             FROM :table_languages 
                             WHERE languages_id = :language_id 
                             LIMIT 1');
      $query->bindInt(':language_id', $languageId);
      $query->execute();

      return $query->fetch() !== false;
    } catch (\Exception $e) {
      $this->logger->logError('Failed to check language existence: ' . $e->getMessage(), [
        'language_id' => $languageId
      ]);
      return false;
    }
  }

  /**
   * Sanitize string input for safe output
   *
   * Uses htmlspecialchars() for proper HTML entity encoding.
   * HTML::output() is used for display purposes, not encoding.
   *
   * @param string $input Input string to sanitize
   * @return string Sanitized string with HTML entities encoded
   */
  public function sanitizeOutput(string $input): string
  {
    return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  }

  /**
   * Get security logger instance
   *
   * Allows other components to access the logger for consistent logging.
   *
   * @return SecurityLogger
   */
  public function getLogger(): SecurityLogger
  {
    return $this->logger;
  }

  /**
   * Get guardrails configuration
   *
   * @return array Guardrails configuration
   */
  public function getGuardrailsConfig(): array
  {
    return $this->guardrailsConfig;
  }
}
