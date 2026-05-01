<?php
/**
 * Reset Chat Context AJAX Endpoint
 * 
 * Creates a new conversation context by clearing the current session
 * and generating a new context ID
 * 
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\AI\Agents\Memory\ConversationMemory;

define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../Core/ClicShopping/') . DIRECTORY_SEPARATOR);

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('ClicShoppingAdmin');

// Check admin access
AdministratorAdmin::hasUserAccess();

// Set JSON response header
header('Content-Type: application/json');

try {
  // Get POST data
  $rawInput = file_get_contents('php://input');
  $input = json_decode($rawInput, true);
  
  // Fallback to $_POST if JSON decode fails
  if (!$input) {
    $input = $_POST;
  }
  
  // Get user ID and language ID
  $userId = AdministratorAdmin::getUserAdminId();
  $languageId = Registry::get('Language')->getId();
  
  // Log for debug
  error_log("DEBUG ResetContext - User ID: {$userId}, Language ID: {$languageId}");
  
  // Initialize ConversationMemory
  $conversationMemory = new ConversationMemory($userId, $languageId);
  
  // Clear current context
  $conversationMemory->clearContext();
  
  // Generate new context ID
  $newContextId = 'context_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4));
  
  // Store new context ID in session
  $_SESSION['chat_context_id'] = $newContextId;
  $_SESSION['chat_context_created_at'] = time();
  
  // Clear conversation history in session
  if (isset($_SESSION['conversation_history'])) {
    unset($_SESSION['conversation_history']);
  }
  
  // Clear entity context
  if (isset($_SESSION['last_entity_id'])) {
    unset($_SESSION['last_entity_id']);
  }
  if (isset($_SESSION['last_entity_type'])) {
    unset($_SESSION['last_entity_type']);
  }
  
  error_log("DEBUG ResetContext - New context ID: {$newContextId}");
  
  // Return success response
  echo json_encode([
    'success' => true,
    'message' => 'Nouveau contexte créé avec succès',
    'new_context_id' => $newContextId,
    'timestamp' => time()
  ]);
  
} catch (\Exception $e) {
  error_log("ERROR ResetContext - Exception: " . $e->getMessage());
  
  echo json_encode([
    'success' => false,
    'error' => 'Erreur serveur: ' . $e->getMessage()
  ]);
}

exit;
