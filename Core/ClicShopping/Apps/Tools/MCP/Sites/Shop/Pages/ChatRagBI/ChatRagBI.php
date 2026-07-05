<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/*
// Example CURL command for testing the RAG-BI API endpoint
// Note: Use a valid Base64 encoded "username:key"
//   Example: echo -n "RagBI:YOUR_KEY" | base64
curl "http://localhost/clicshopping_test/index.php?mcp&ChatRagBI&action=analyze" \
   -H "Authorization: Basic Token" \
   -H "Content-Type: application/json" \
  -d '{"message":"liste les produits","queryType":"analytics"}'
*/

namespace ClicShopping\Apps\Tools\MCP\Sites\Shop\Pages\ChatRagBI;

use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\AI\Infrastructure\Metrics\StatisticsTracker;
use ClicShopping\AI\Security\LlmGuardrails;
use ClicShopping\AI\Security\LlmResponseEvaluator;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\ChatCritic\ChatCriticSeam;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Context;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\ContextManager;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\EntityExtractor;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\MemoryManager;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\QueryProcessor;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\RequestValidator;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\ResponseFormatter;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\ResponseValidator;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\StatisticsManager;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\MCPConnector;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\EndPoint\RagBIPermissions;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\Authentification;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\McpPermissions;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\McpSecurity;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\Message;
use ClicShopping\Apps\Tools\MCP\MCP;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

class ChatRagBI extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $db;
  public mixed $app;
  public mixed $message;
  /** @var McpPermissions The McpPermissions instance for access control. */
  public McpPermissions $mcpPermissions;

  protected mixed $lang;
  protected bool $use_site_template = false;
  protected ?string $file = null;
  private mixed $ragBIPermissions;
  /** @var string The username authenticated via session or key. */
  private string $authenticatedUsername = '';
  /** @var int|null MCP session ID from request (if provided). */
  private ?string $authenticatedSessionId = null;

  /**
   * Valide une requête SQL pour RAG-BI
   * Utilise les contrôles stricts de RagBIPermissions
   *
   * @param string $sqlQuery Requête SQL à valider
   * @return bool True si autorisée, false sinon
   */
  public function validateRagBIQuery(string $sqlQuery): bool
  {
    // Utiliser l'utilisateur authentifié si la requête vient de l'intérieur de l'application
    $username = $this->authenticatedUsername;

    if (empty($username)) {
      // Si l'utilisateur n'est pas encore défini (appel hors du flux API standard), rejeter ou gérer différemment
      return false;
    }

    return $this->ragBIPermissions->canExecuteRagBIQuery($username, $sqlQuery);
  }

  /**
   * Obtient un rapport de sécurité pour l'utilisateur RAG-BI actuel
   *
   * @return array|null Rapport de sécurité ou null si non authentifié
   */
  public function getRagBISecurityReport(): ?array
  {
    // Utiliser l'utilisateur authentifié si la requête vient de l'intérieur de l'application
    $username = $this->authenticatedUsername;

    if (empty($username)) {
      return null;
    }

    return $this->ragBIPermissions->generateSecurityReport($username);
  }

  /**
   * Initializes the class by setting up dependencies, headers, and request routing.
   * It handles all incoming API requests (GET, POST, OPTIONS) and routes them to the appropriate handler.
   *
   * @return void
   */
  protected function init()
  {
    $this->db = Registry::get('Db');
    $this->lang = Registry::get('Language');

    // Set JSON content type
    header('Content-Type: application/json');

    // Enable CORS for MCP server with security headers
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    // Ajout de HTTP_MCP_USER/KEY et HTTP_MCP_TOKEN
    header(
      'Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key, X-Session-Token, X-MCP-USER, X-MCP-KEY, X-MCP-TOKEN'
    );
    header('Access-Control-Allow-Credentials: true');

    // Security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    if (!Registry::exists('MCP')) {
      Registry::set('MCP', new MCP());
    }

    $this->app = Registry::get('MCP');

    if (!Registry::exists('Message')) {
      Registry::set('Message', new Message());
    }
    $this->message = Registry::get('Message');

    // Initialisation de McpPermissions
    if (!Registry::exists('McpPermissions')) {
      Registry::set('McpPermissions', new McpPermissions());
    }
    $this->mcpPermissions = Registry::get('McpPermissions');

    if (!Registry::exists('MCPConnector')) {
      Registry::set('MCPConnector', new MCPConnector());
    }
    $this->mcpConnector = Registry::get('MCPConnector');

    if (!Registry::exists('RagBIPermissions')) {
      Registry::set('RagBIPermissions', new RagBIPermissions());
    }
    $this->ragBIPermissions = Registry::get('RagBIPermissions');

    // Maintien de la vérification de statut de l'application
    if (!\defined('CLICSHOPPING_APP_MCP_MC_STATUS') || CLICSHOPPING_APP_MCP_MC_STATUS == 'False') {
      $this->sendErrorResponse('API is disabled');
      return;
    }

    // Si c'est une requête OPTIONS (preflight), on autorise et on sort
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
      http_response_code(200);
      exit;
    }

    // =========================================================================
    // START: LOGIQUE D'AUTHENTIFICATION ET DE GESTION DE SESSION (Source unique)
    // =========================================================================

    // 1. Récupération des paramètres (vérification de l'URL et des Headers)
    $username = $_GET['user_name'] ?? $_POST['user_name'] ?? $_SERVER['HTTP_X_MCP_USER'] ?? $_SERVER['HTTP_MCP_USER'] ?? null;
    $key = $_GET['key'] ?? $_POST['key'] ?? $_SERVER['HTTP_X_MCP_KEY'] ?? $_SERVER['HTTP_MCP_KEY'] ?? null;
    $mcpSessionId = $_GET['token'] ?? $_POST['token'] ?? $_SERVER['HTTP_X_MCP_TOKEN'] ?? $_SERVER['HTTP_MCP_TOKEN'] ?? null;
    $this->authenticatedSessionId = $mcpSessionId;

    // DÉCODAGE DE L'EN-TÊTE AUTHORIZATION BASIC (pour le premier appel curl)
    $authorizationHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    if (empty($username) && empty($key) && !empty($authorizationHeader)) {
      if (preg_match('/Basic\s+(.*)/i', $authorizationHeader, $matches)) {
        $decodedCredentials = base64_decode($matches[1]);
        if (str_contains($decodedCredentials, ':')) {
          list($authUsername, $authKey) = explode(':', $decodedCredentials, 2);
          // Utiliser ces valeurs pour l'authentification
          $username = $authUsername;
          $key = $authKey;
        }
      }
    }

    // 2. Vérification/Création de session
    if (empty($mcpSessionId)) {
      // Le token de session est manquant -> Tentative d'authentification par identifiants
      if (empty($username) || empty($key)) {
        $this->message->sendError(
          'Unauthorized: Missing required session token OR credentials for authentication.',
          401
        );
        return;
      }

      try {
        // AUTHENTIFICATION ET CRÉATION DE SESSION
        $authentification = new Authentification($username, $key);
        $mcpSessionId = $authentification->authenticateAndCreateSession();
        // L'utilisateur est maintenant authentifié, on utilise le $username fourni
        $this->authenticatedUsername  = $username;
        $this->authenticatedSessionId = $mcpSessionId;
      } catch (\Exception $e) {
        McpSecurity::logSecurityEvent(
          'API Access Denied - Authentication Failed',
          [
          'username' => $username,
          'error' => $e->getMessage()
          ]);

        $this->message->sendError('Unauthorized: Authentication failed. ' . $e->getMessage(), 401);
        return;
      }
    } else {
      // 3. Validation de la session (Token) existante
      try {
        // Valide le Session ID, vérifie l'IP, et renouvelle si nécessaire.
        $validSessionId = McpSecurity::checkToken($mcpSessionId);

        // IMPORTANT : Récupérer le nom d'utilisateur associé au token pour la vérification des permissions.
        $this->authenticatedUsername  = McpSecurity::getUsernameFromSession($validSessionId);
        $this->authenticatedSessionId = $validSessionId;

        if (empty($this->authenticatedUsername)) {
          throw new \Exception("Session token is valid but associated username could not be found.");
        }
      } catch (\Exception $e) {
        McpSecurity::logSecurityEvent(
          'API Access Denied - Invalid Session Token',
          ['session_id' => $mcpSessionId, 'error' => $e->getMessage()]
        );
        $this->message->sendError('Unauthorized: Invalid or expired session token. ' . $e->getMessage(), 401);
        return;
      }
    }

    // Expose the current session token so clients can reuse it instead of
    // re-authenticating on every call (covers silent renewal too).
    McpSecurity::emitSessionHeaders($this->authenticatedSessionId);

    // =========================================================================
    // END: LOGIQUE D'AUTHENTIFICATION ET DE GESTION DE SESSION
    // =========================================================================

    // 4. VÉRIFICATION DES PERMISSIONS MCP ET RAG-BI (Post-authentification)

    // Toujours obtenir l'action même si l'URL est pourrie, pour la vérification de permission
    $action = HTML::sanitize($_GET['action'] ?? $_POST['action'] ?? 'analyze_sales');

    // Vérification de la permission RAG-BI générale (via RagBIPermissions)
    if (!$this->ragBIPermissions->canAccessRagBI($this->authenticatedUsername)) {
      McpSecurity::logSecurityEvent('RAG-BI Access Denied - General permission check failed', [
        'username' => $this->authenticatedUsername,
        'action' => $action
      ]);
      $this->message->sendError(
        'Forbidden: User "' . $this->authenticatedUsername . '" does not have general RAG-BI access.',
        403
      );
      return;
    }

    // Vérification de la permission spécifique RAG-BI pour l'action demandée
    // (RagBIPermissions owns its action whitelist and delegates evaluation to
    // McpPermissions::evaluateEndpointAction — Core stays endpoint-agnostic.)
    if (!$this->ragBIPermissions->canPerformAction($this->authenticatedUsername, $action)) {
      McpSecurity::logSecurityEvent('API Access Denied - Permission check failed', [
        'username' => $this->authenticatedUsername,
        'action' => $action
      ]);

      $this->message->sendError(
        'Forbidden: User "' . $this->authenticatedUsername . '" does not have permission for action "' . $action . '".',
        403
      );
      return;
    }

    // =========================================================================
    // START: LOGIQUE RAG-BI (Le reste de votre code)
    // =========================================================================

    try {
      Gpt::getEnvironment();

      $enableTimeout = true;
      $maxExecutionTime = defined('CLICSHOPPING_APP_CHATGPT_RA_MAX_EXECUTION_TIME')
        ? CLICSHOPPING_APP_CHATGPT_RA_MAX_EXECUTION_TIME
        : 120;
      RequestValidator::configureTimeout($maxExecutionTime, $enableTimeout);
      $queryStartTime = microtime(true);

      $validation = RequestValidator::validateRequest($_POST);
      if (!$validation['valid']) {
        if (ob_get_length()) {
          ob_clean();
        }
        echo json_encode($validation['error'], JSON_UNESCAPED_UNICODE);
        exit;
      }

      $prompt = HTML::sanitize($validation['query']);
      //$prompt = 'donne moi un tableau de l evolution du chiffre d\'affaires par mois de l\'annéé 2025'; // test

      $languageId = Registry::get('Language')->getId();
      $mcpUserId = $this->getMcpIdByUsername($this->authenticatedUsername);
      $sessionId = $this->authenticatedSessionId ?? session_id();

      if (\defined('CLICSHOPPING_APP_CHATGPT_RA_STATUS') && CLICSHOPPING_APP_CHATGPT_RA_STATUS == 'True') {
        // Optional pre-check if client forces web_search/hybrid
        $requestedType = HTML::sanitize($_POST['queryType'] ?? '');
        if ($this->isWebSearchType($requestedType) && !$this->isLocalMcpServerUp()) {
          if (ob_get_length()) {
            ob_clean();
          }
          echo json_encode([
            'status' => 'error',
            'message' => 'Local MCP server (localhost:3001) is not reachable. Please start it before using web_search or hybrid.',
            'code' => 'MCP_LOCAL_SERVER_DOWN'
          ], JSON_UNESCAPED_UNICODE);
          exit;
        }

        $statsTracker = new StatisticsTracker($mcpUserId, $sessionId, $languageId);
        $statsTracker->startTracking();

        $memoryService = ContextManager::initializeMemoryService($mcpUserId, $languageId);
        $context = ContextManager::retrieveContext($memoryService, $prompt, 5);

        $aiResponse = QueryProcessor::process($prompt, $mcpUserId, $languageId, $statsTracker);

        if ($enableTimeout && RequestValidator::checkTimeout($queryStartTime, $maxExecutionTime)) {
          if (ob_get_length()) {
            ob_clean();
          }
          echo json_encode([
            'status' => 'error',
            'message' => 'La requête prend trop de temps, veuillez réessayer',
            'code' => 'QUERY_TIMEOUT'
          ], JSON_UNESCAPED_UNICODE);
          exit;
        }

        $metadata = EntityExtractor::extractMetadata($aiResponse, $languageId);
        MemoryManager::recordInteraction($memoryService, $prompt, $aiResponse, $metadata);

        $formattedResponse = ResponseFormatter::format($aiResponse, $prompt, $metadata, $context ?: null);
        $formatted = $formattedResponse['content'];

        $responseTime = $statsTracker->stopTracking();
        StatisticsManager::recordTokenUsage($statsTracker);

        $guardrailsEval = LlmGuardrails::getLastEvaluation();
        if (is_array($guardrailsEval) && !isset($guardrailsEval['error'])) {
          $securityScore = $guardrailsEval['security_analysis']['overall_security_score'] ?? null;
          $aiResponse['metrics']['relevance_score'] = (float)($guardrailsEval['relevance'] ?? 0);
          $aiResponse['metrics']['security_score'] = $securityScore !== null ? (float)$securityScore : 0.8;
          $aiResponse['metrics']['hallucination_score'] = (float)($guardrailsEval['hallucination_risk'] ?? 0.1);
          $aiResponse['metrics']['response_quality'] = (float)($guardrailsEval['overall_score'] ?? 0);
          $aiResponse['metrics']['confidence_score'] = (float)($aiResponse['intent']['confidence'] ?? 0);
          // dark-launch: when the chat critic seam is enabled (OFF by code default), route the
          // verdict through the agnostic ActorCriticCoordinator (observe-only, verdict parity). Otherwise
          // keep the legacy direct path byte-identical.
          if (ChatCriticSeam::isEnabled()) {
            $seamAnswer = MemoryManager::extractResponseText($aiResponse) ?? $formatted;
            $qualityVerdict = ChatCriticSeam::evaluate((string)$seamAnswer, $guardrailsEval, new Context((string)$mcpUserId, $languageId, ['source' => 'chat_mcp']));
            $statsTracker->setQualityVerdict($qualityVerdict);
          } else {
            $statsTracker->setQualityVerdict(LlmResponseEvaluator::deriveQualitySignal($guardrailsEval));
          }
        }

        $metrics = StatisticsManager::calculateFallbackMetrics(
          $aiResponse,
          $formattedResponse['data_to_format'] ?? [],
          $formatted
        );

        $statsTracker->setQualityScores(
          $metrics['response_quality'] * 100,
          $metrics['security_score'] * 100
        );

        $responseText = MemoryManager::extractResponseText($aiResponse);
        if (empty($responseText)) {
          $responseText = $formatted;
        }

        // Mint the client-facing token BEFORE persistence so it is stored on rag_interactions
        $clientInteractionId = 'interaction_' . $mcpUserId . '_' . time() . '_' . substr(md5(uniqid('', true)), 0, 8);

        $interactionData = StatisticsManager::buildInteractionData(
          $prompt,
          $responseText,
          $aiResponse,
          $metadata,
          $mcpUserId,
          $sessionId,
          $languageId,
          $responseTime,
          $metrics,
          $statsTracker,
          $clientInteractionId
        );

        $dbInteractionId = StatisticsManager::persistInteraction($interactionData, $statsTracker);
        StatisticsManager::saveStatistics($statsTracker, $dbInteractionId);

        $queryType = $aiResponse['intent']['type'] ?? 'semantic';
        if ($this->isWebSearchType($queryType) && !$this->isLocalMcpServerUp()) {
          if (ob_get_length()) {
            ob_clean();
          }
          echo json_encode([
            'status' => 'error',
            'message' => 'Local MCP server (localhost:3001) is not reachable. Please start it before using web_search or hybrid.',
            'code' => 'MCP_LOCAL_SERVER_DOWN'
          ], JSON_UNESCAPED_UNICODE);
          exit;
        }
        $confidence = $aiResponse['intent']['confidence'] ?? 0;

        $jsonResponse = [
          'status' => 'success',
          'data' => [
            'response' => $responseText,
            'type' => $queryType,
            'source' => 'clicshopping-ragbi',
            'confidence' => $confidence,
            'metadata' => [
              'query_type' => $queryType,
              'language' => $languageId,
              'processing_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
              'rag_enabled' => true,
              'clicshopping_processed' => true
            ]
          ]
        ];

        $validation = ResponseValidator::validate([
          'success' => true,
          'interaction_id' => $clientInteractionId,
          'text_response' => is_string($responseText) ? $responseText : '',
          'type' => $queryType,
          'confidence' => $confidence,
          'agent_used' => $aiResponse['agent_used'] ?? 'unknown',
          'execution_time' => $aiResponse['execution_time'] ?? 0,
          'entity_id' => $metadata['entity_id'] ?? 0,
          'entity_type' => $metadata['entity_type'] ?? 'unknown',
          'language_id' => $languageId,
          'metrics' => $metrics,
          'metadata' => [
            'query' => $prompt,
            'timestamp' => time(),
            'user_id' => $mcpUserId
          ]
        ]);

        if (!$validation['valid'] && defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
          error_log('[RAGBI MCP] Response validation failed: ' . json_encode($validation['errors']));
        }

        if (ob_get_length()) {
          ob_clean();
        }
        echo json_encode($jsonResponse, JSON_UNESCAPED_UNICODE);
        exit;
      }

      // Fallback if RAG is disabled
      $response = Gpt::callWithModel($prompt, Gpt::defaultModel());
      $textResponse = $response['text_response']
        ?? $response['response']
        ?? $response['interpretation']
        ?? $response['raw_response']
        ?? '';

      if (ob_get_length()) {
        ob_clean();
      }
      echo json_encode([
        'status' => 'success',
        'data' => [
          'response' => is_string($textResponse) ? $textResponse : '',
          'type' => $response['response_type'] ?? ($response['type'] ?? 'semantic'),
          'source' => 'clicshopping-ragbi',
          'confidence' => $response['confidence'] ?? 0,
          'metadata' => [
            'query_type' => $response['response_type'] ?? ($response['type'] ?? 'semantic'),
            'language' => $languageId,
            'processing_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'rag_enabled' => false,
            'clicshopping_processed' => true
          ]
        ]
      ], JSON_UNESCAPED_UNICODE);
      exit;
      exit;
    } catch
    (\Exception $e) {
      error_log('Erreur dans le traitement AJAX : ' . $e->getMessage());
      echo json_encode([
        'status' => 'error',
        'data' => [
          'response' => "Une erreur s'est produite lors du traitement de votre requête : " . $e->getMessage(),
          'type' => 'error',
          'source' => 'clicshopping-ragbi',
          'confidence' => 0.1,
          'error' => [
            'type' => 'processing_error',
            'message' => $e->getMessage()
          ],
          'metadata' => [
            'rag_enabled' => false,
            'clicshopping_processed' => false
          ]
        ]
      ]);

      exit;
    }
  }

  /**
   * Sends an error response with the provided message.
   *
   * @param string $message The error message to be included in the response.
   * @return void
   */
  private function sendErrorResponse(string $message): void
  {
    // Utiliser la méthode sendError de l'objet message pour le format JSON standard de l'API
    if (isset($this->message)) {
      $this->message->sendError($message, 500); // Code HTTP 500 par défaut si non spécifié
    } else {
      // Fallback si l'objet message n'est pas initialisé
      http_response_code(500);
      echo json_encode(['status' => 'error', 'message' => $message]);
      exit;
    }
  }

  /**
   * Resolve MCP user ID by username.
   *
   * @param string $username
   * @return int
   */
  private function getMcpIdByUsername(string $username): int
  {
    if (empty($username)) {
      return 0;
    }

    $Qcheck = $this->db->prepare('SELECT mcp_id
                                   FROM :table_mcp
                                   WHERE username = :mcp_username
                                   LIMIT 1
                                  ');
    $Qcheck->bindValue(':mcp_username', $username);
    $Qcheck->execute();

    if ($Qcheck->fetch()) {
      return (int)$Qcheck->valueInt('mcp_id');
    }

    return 0;
  }

  /**
   * Check if the request type relies on local MCP web search.
   *
   * @param string $queryType
   * @return bool
   */
  private function isWebSearchType(string $queryType): bool
  {
    $type = strtolower(trim($queryType));
    return in_array($type, ['web_search', 'websearch', 'hybrid'], true);
  }

  /**
   * Check if local MCP server is reachable.
   *
   * @return bool
   */
  private function isLocalMcpServerUp(): bool
  {
    $host = 'http://localhost:3001/';
    $mcpId = 0;

    // 1) Resolve MCP id from active session
    if (!empty($this->authenticatedSessionId)) {
      $Qsession = $this->db->prepare('select mcp_id
                                        from :table_mcp_session
                                       where session_id = :session_id
                                       order by date_modified desc
                                       limit 1
                                      ');
      $Qsession->bindValue(':session_id', $this->authenticatedSessionId);
      $Qsession->execute();
      if ($Qsession->fetch()) {
        $mcpId = (int)$Qsession->valueInt('mcp_id');
      }
    }

    // 2) Fallback to username mapping
    if ($mcpId === 0 && !empty($this->authenticatedUsername)) {
      $mcpId = $this->getMcpIdByUsername($this->authenticatedUsername);
    }

    // 3) Resolve host/port/ssl for the selected MCP id
    if ($mcpId > 0) {
      try {
        // Enforce IP restrictions for this MCP config
        if (!McpSecurity::validateIp($mcpId)) {
          return false;
        }

        $config = MCPConnector::getConfigDb($mcpId);
        $protocol = !empty($config['ssl']) ? 'https' : 'http';
        $serverHost = $config['server_host'] ?? 'localhost';
        $serverPort = (int)($config['server_port'] ?? 3001);
        $host = "{$protocol}://{$serverHost}:{$serverPort}/";
      } catch (\Throwable $e) {
        // keep fallback host
      }
    }

    $response = HTTP::getResponse([
      'url' => $host,
      'method' => 'get',
      'header' => ['Accept: */*'],
      'parameters' => ''
    ]);

    return $response !== false;
  }

  /**
   * Handle GET request
   */
  private function handleGetRequest(array $statusCheck)
  {
    if ($statusCheck['get'] == 0) {
      return $this->sendErrorResponse('Category fetch not allowed');
    }

    return $this->sendSuccessResponse(static::getCategories());
  }

  /**
   * Sends a success response with the provided data.
   *
   * @param mixed $data The data to be included in the success response.
   * @return void
   */
  private function sendSuccessResponse(mixed $data): void
  {
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
  }

  /**
   * Handle PUT request
   */
  private function handlePutRequest(array $statusCheck)
  {
    if (!$statusCheck['update'] == 0) {
      return $this->sendErrorResponse('Update not allowed');
    }

    return $this->sendSuccessResponse('Category updated successfully');
  }

  /**
   * Handle DELETE request
   */
  private function handleDeleteRequest(array $statusCheck)
  {
    if ($statusCheck['delete'] == 0) {
      return $this->sendErrorResponse('Category deletion not allowed');
    }

    return $this->sendSuccessResponse(static::deleteCategories());
  }

  /**
   * Handle POST request
   */
  private function handlePostRequest(array $statusCheck)
  {
    if (isset($_GET['update']) && $statusCheck['update'] == 0) {
      return $this->sendErrorResponse('Category update not allowed');
    }

    if (isset($_GET['insert']) && $statusCheck['insert'] == 0) {
      return $this->sendErrorResponse('Category insertion not allowed');
    }

    return $this->sendSuccessResponse(self::saveCategories());
  }

  /**
   * Checks the status based on the provided string and token.
   *
   * @param string $string The column name to be selected from the database.
   * @param string $token The session token used for identifying the API session.
   * @return int The integer value associated with the specified column.
   */
  private function statusCheck(string $string, string $token): int
  {
    // Resolve MCP primary key column (mcp_id or id) to avoid schema mismatches
    $mcpIdColumn = 'mcp_id';
    try {
      $table = CLICSHOPPING::getConfig('db_table_prefix') . 'mcp';
      $Qcol = $this->db->prepare('SELECT COLUMN_NAME
                                    FROM INFORMATION_SCHEMA.COLUMNS
                                   WHERE TABLE_SCHEMA = DATABASE()
                                     AND TABLE_NAME = :table
                                     AND COLUMN_NAME IN ("mcp_id", "id")
                                   LIMIT 1
                                 ');
      $Qcol->bindValue(':table', $table);
      $Qcol->execute();
      if ($Qcol->fetch()) {
        $mcpIdColumn = $Qcol->value('COLUMN_NAME');
      }
    } catch (\Throwable $e) {
      // Fallback to default column name
    }

    $QstatusCheck = $this->db->prepare(
      'select a.' . $string . '
             from :table_mcp a,
                  :table_mcp_session ase
             where a.' . $mcpIdColumn . ' = ase.mcp_id
             and ase.session_id = :session_id
           '
    );
    $QstatusCheck->bindValue('session_id', $token);
    $QstatusCheck->execute();

    return $QstatusCheck->valueInt($string);
  }

  // Les fonctions validateRagBIAccess() et getMcpAuthFromRequest() ont été supprimées.
}
