<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\AI\CoreAI\Memory\ConversationMemory;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../Core/ClicShopping/') . DIRECTORY_SEPARATOR);

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('ClicShoppingAdmin');

$CLICSHOPPING_Db = Registry::get('Db');
$CLICSHOPPING_Language = Registry::get('Language');
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
  
  // Validate required parameters
  if (!isset($input['interaction_id']) || empty($input['interaction_id'])) {
    echo json_encode([
      'success' => false,
      'error' => 'interaction_id is required'
    ]);
    exit;
  }
  
  if (!isset($input['feedback_type']) || empty($input['feedback_type'])) {
    echo json_encode([
      'success' => false,
      'error' => 'feedback_type is required'
    ]);
    exit;
  }
  
  // Validate feedback_type
  $validTypes = ['positive', 'negative', 'correction'];
  if (!in_array($input['feedback_type'], $validTypes)) {
    echo json_encode([
      'success' => false,
      'error' => 'feedback_type must be one of: ' . implode(', ', $validTypes)
    ]);
    exit;
  }
  
  // Sanitize inputs
  $interactionId = HTML::sanitize($input['interaction_id']);
  $feedbackType = HTML::sanitize($input['feedback_type']);
  $feedbackText = isset($input['feedback_text']) ? HTML::sanitize($input['feedback_text']) : '';
  $correctedText = isset($input['corrected_text']) ? HTML::sanitize($input['corrected_text']) : '';
  $originalQuery = isset($input['original_query']) ? HTML::sanitize($input['original_query']) : '';
  
  // Get user ID and language ID (admin context)
  $userId = AdministratorAdmin::getUserAdminId();
  $languageId = $CLICSHOPPING_Language->getId();
  
  // Log pour debug
  error_log("DEBUG Feedback - User ID: {$userId}, Language ID: {$languageId}");
  error_log("DEBUG Feedback - Interaction ID: {$interactionId}, Type: {$feedbackType}");
  
  // If original_query not provided, try to get it from rag_interactions table
  if (empty($originalQuery)) {
    $interactionQuery = $CLICSHOPPING_Db->prepare("
      SELECT question 
      FROM :table_rag_interactions 
      WHERE interaction_id = :interaction_id
      LIMIT 1
    ");
    $interactionQuery->bindValue(':interaction_id', $interactionId);
    $interactionQuery->execute();
    
    if ($interactionQuery->fetch()) {
      $originalQuery = $interactionQuery->value('question');
    }
  }
  
  // Initialize ConversationMemory
  $conversationMemory = new ConversationMemory($userId, $languageId);
  
  // Prepare feedback data with original query and corrected text
  $feedbackData = [
    'feedback_text' => $feedbackText,
    'corrected_text' => $correctedText,
    'original_query' => $originalQuery,
    'timestamp' => time(),
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_id' => $userId,
    'language_id' => $languageId
  ];
  
  error_log("DEBUG Feedback - Data prepared: " . json_encode($feedbackData));
  
  // Record feedback
  $result = $conversationMemory->recordFeedback(
    $interactionId,
    $feedbackType,
    $feedbackData
  );
  
  error_log("DEBUG Feedback - Result: " . ($result ? 'SUCCESS' : 'FAILED'));
  
  if ($result) {
    echo json_encode([
      'success' => true,
      'message' => 'Feedback enregistré avec succès'
    ]);
  } else {
    echo json_encode([
      'success' => false,
      'error' => 'Échec de l\'enregistrement du feedback'
    ]);
  }
  
} catch (\Exception $e) {
  echo json_encode([
    'success' => false,
    'error' => 'Erreur serveur: ' . $e->getMessage()
  ]);
}

exit;
