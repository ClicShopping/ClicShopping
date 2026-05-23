<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * MCP (Multi-Channel Products) API endpoint for customer product management.
 *
 * This class acts as a central hub for handling API requests related to products,
 * including listings, single product details, search, and chat-based queries.
 * It routes requests to the appropriate methods within the `Products` and `Message` classes.
 */

namespace ClicShopping\Apps\Tools\MCP\Sites\Shop\Pages\CustomersProducts;


use ClicShopping\Apps\Tools\MCP\Classes\Shop\EndPoint\Products;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\Authentification;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\McpPermissions;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\McpSecurity;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\Message;
use ClicShopping\Apps\Tools\MCP\MCP;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

// Nouvelle dépendance pour l'authentification


/**
 * This class serves as the main entry point for the MCP Products API.
 * It handles request routing for product-related queries and chat interactions.
 */
class CustomersProducts extends \ClicShopping\OM\Domains\PagesAbstract
{
  /** @var mixed The database connection instance. */
  public mixed $db;
  /**
   * ClicShopping application instance.
   * @var mixed
   */

  public mixed $app;


  public mixed $product;

   /**
   * Display the message
   * @var bool
   */
  public mixed $message;
  /** @var McpPermissions The McpPermissions instance for access control. */
  public McpPermissions $mcpPermissions;
  /**
   * Determines if the site template should be used.
   * @var bool
   */
  protected bool $use_site_template = false;
  /**
   * The file name for the page.
   * @var string|null
   */
  protected ?string $file = null;
  /** @var string The username authenticated via session or key. */
  private string $authenticatedUsername = '';
  /** @var string|null The username resolved from a key-only auth flow. */
  private ?string $resolvedUsernameFromKey = null;

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

    Registry::set('Products', new Products());
    $this->product = Registry::get('Products');

    if (!Registry::exists('Message')) {
      Registry::set('Message', new Message());
    }
    $this->message = Registry::get('Message');

    // Initialisation de McpPermissions
    if (!Registry::exists('McpPermissions')) {
      Registry::set('McpPermissions', new McpPermissions());
    }
    $this->mcpPermissions = Registry::get('McpPermissions');


    // =========================================================================
    // START: LOGIQUE D'AUTHENTIFICATION ET DE GESTION DE SESSION
    // =========================================================================

    // 1. Récupération des paramètres (vérification de l'URL et des Headers)
    $username = $_GET['user_name'] ?? $_POST['user_name'] ?? $_SERVER['HTTP_X_MCP_USER'] ?? $_SERVER['HTTP_MCP_USER'] ?? null;
    $key = $_GET['key'] ?? $_POST['key'] ?? $_SERVER['HTTP_X_MCP_KEY'] ?? $_SERVER['HTTP_MCP_KEY'] ?? null;
    $mcpSessionId = $_GET['token'] ?? $_POST['token'] ?? $_SERVER['HTTP_X_MCP_TOKEN'] ?? $_SERVER['HTTP_MCP_TOKEN'] ?? null;

    // Si c'est une requête OPTIONS (preflight), on autorise et on sort
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
      http_response_code(200);
      exit;
    }

    // DÉCODAGE DE L'EN-TÊTE AUTHORIZATION BASIC (pour le premier appel curl)
    $authorizationHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    if (empty($username) && empty($key) && !empty($authorizationHeader)) {
      if (preg_match('/Basic\s+(.*)/i', $authorizationHeader, $matches)) {
        $basicToken = trim($matches[1]);
        $decodedCredentials = base64_decode($basicToken, true);
        if ($decodedCredentials !== false && str_contains($decodedCredentials, ':')) {
          list($authUsername, $authKey) = explode(':', $decodedCredentials, 2);
          // Utiliser ces valeurs pour l'authentification
          $username = $authUsername;
          $key = $authKey;
        } else {
          // Fallback: allow raw key passed as Basic token (legacy/example usage)
          $key = $basicToken;
        }
      }
    }

    // Fallback: if only key is provided, attempt to resolve username by key
    if (empty($username) && !empty($key)) {
      $this->resolvedUsernameFromKey = $this->findUsernameByKey($key);
      if (!empty($this->resolvedUsernameFromKey)) {
        $username = $this->resolvedUsernameFromKey;
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
        $this->authenticatedUsername = $username;
      } catch (\Exception $e) {
        McpSecurity::logSecurityEvent(
          'API Access Denied - Authentication Failed',
          ['username' => $username, 'error' => $e->getMessage()]
        );

        $this->message->sendError('Unauthorized: Authentication failed. ' . $e->getMessage(), 401);
        return;
      }
    } else {
      // 3. Validation de la session (Token) existante
      try {
        // Valide le Session ID, vérifie l'IP, et renouvelle si nécessaire.
        $validSessionId = McpSecurity::checkToken($mcpSessionId);

        // IMPORTANT : Récupérer le nom d'utilisateur associé au token pour la vérification des permissions.
        $this->authenticatedUsername = McpSecurity::getUsernameFromSession($validSessionId);

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

    // =========================================================================
    // END: LOGIQUE D'AUTHENTIFICATION ET DE GESTION DE SESSION
    // =========================================================================

    // 4. Vérification des permissions spécifiques

    // Toujours obtenir l'action même si l'URL est pourrie, pour la vérification de permission
    $action = HTML::sanitize($_GET['action'] ?? 'products');

    // Vérification de la permission pour l'action demandée
    if (!$this->mcpPermissions->hasPermissionForEndpoint($this->authenticatedUsername, 'CustomerProducts', $action)) {
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


    // --- Début de la logique métier (Après la validation réussie) ---

    $isProductionMode = \defined('CLICSHOPPING_APP_MCP_MC_DISPLAY_BROWSER_JSON') && CLICSHOPPING_APP_MCP_MC_DISPLAY_BROWSER_JSON == 'True';

    if ($isProductionMode && $_SERVER['REQUEST_METHOD'] == 'GET') {
      // En mode production, seule l'action 'search' est généralement autorisée via le navigateur.
      if ($action !== 'search') {
        header('Content-Type: text/plain');
        http_response_code(403);
        echo 'Access to this API endpoint is restricted in production mode. Only search action is allowed for direct browser access.';
        exit;
      }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $this->handleChatRequest();
      return;
    }

    try {
      // L'action est déjà validée par les permissions
      match ($action) {
        'products' => $this->product->getProductsList(),
        'product' => $this->product->getProductDetail(),
        'categories' => $this->product->getCategories(),
        'search' => $this->product->handleSearchQuery(),
        'stats' => $this->product->getProductStats(),
        'recommendations' => $this->product->getProductRecommendations(),
        // L'appel sendError ici était déjà correct
        default => $this->message->sendError('Invalid action', 400)
      };
    } catch (\Exception $e) {
      error_log('MCP Products API Error: ' . $e->getMessage());
      // L'appel sendError ici était déjà correct
      $this->message->sendError($e->getMessage(), 500);
    }
  }

  /**
   * Resolve username from a raw MCP key.
   *
   * @param string $key
   * @return string|null
   */
  private function findUsernameByKey(string $key): ?string
  {
    try {
      $Quser = $this->db->prepare('SELECT username
                                   FROM :table_mcp
                                   WHERE mcp_key = :mcp_key AND status = 1
                                   LIMIT 1
                                  ');
      $Quser->bindValue(':mcp_key', $key);
      $Quser->execute();

      if ($Quser->fetch()) {
        return (string)$Quser->value('username');
      }
    } catch (\Exception $e) {
      McpSecurity::logSecurityEvent('Failed to resolve username from key', [
        'error' => $e->getMessage()
      ]);
    }

    return null;
  }


  // Suppression des anciennes méthodes de sécurité (validateMcpKey, validateSessionToken, etc.)
  // qui sont remplacées par le bloc de McpSecurity dans init().
  // Les méthodes de Chat et d'aide sont conservées.

  /**
   * Handles the main chat request, decoding the JSON input and routing to
   * either the admin or client chat handler.
   *
   * @return void
   */
  private function handleChatRequest(): void
  {
    try {
      $input = json_decode(file_get_contents('php://input'), true);
      if (!$input) {
        // L'appel sendError ici était déjà correct
        $this->message->sendError('Invalid JSON input', 400);
        return;
      }

      $message = $input['message'] ?? '';
      $context = $input['context'] ?? [];

      if (empty($message)) {
        // L'appel sendError ici était déjà correct
        $this->message->sendError('Message is required', 400);
        return;
      }

      ($context['user_type'] ?? 'client') === 'admin'
        ? $this->handleAdminChat($message, $context)
        : $this->handleClientChat($message, $context);
    } catch (\Exception $e) {
      error_log('MCP Chat Error: ' . $e->getMessage());
      // L'appel sendError ici était déjà correct
      $this->message->sendError('Chat processing error: ' . $e->getMessage(), 500);
    }
  }

  /**
   * Handles chat requests for administrators (RAG BI).
   *
   * @param string $message The chat message from the user.
   * @param mixed $context The context of the chat session.
   * @return void
   */
  private function handleAdminChat(string $message, mixed $context): void
  {
    /*
    // ... (Logique RAG BI pour admin)
    */
  }

  /**
   * Handles chat requests for customers (product-related only).
   *
   * @param string $message The chat message from the customer.
   * @param mixed $context The context of the chat session.
   * @return void
   */
  private function handleClientChat(string $message, mixed $context): void
  {
    try {
      // Analyze the product search intent.
      $intent = $this->product->analyzeProductIntent($message);

      // Execute the product search based on the detected intent.
      $results = $this->product->executeProductSearch($intent, $context);

      // Generate the response message for the client.
      $response = $this->generateProductResponse($results, $intent);

      // Return the JSON response.
      $this->message->sendSuccess([
        'response' => $response,
        'type' => 'product_info',
        'confidence' => $intent['confidence'],
        'products' => $results['products'],
        'metadata' => [
          'search_time' => microtime(true),
          'result_count' => count($results['products']),
          'user_mode' => 'client',
          'intent' => $intent['type']
        ]
      ]);
    } catch (\Exception $e) {
      error_log('Client chat error: ' . $e->getMessage());
      // L'appel sendError ici était déjà correct
      $this->message->sendError('Client chat error: ' . $e->getMessage(), 500);
    }
  }

  /**
   * Generates a formatted text response for the customer based on product search results.
   *
   * @param array $results The search results containing product data.
   * @param array $intent The detected user intent and filters.
   * @return string The formatted response message.
   */
  private function generateProductResponse(array $results, array $intent): string
  {
    if (empty($results['products'])) {
      return "🔍 No products found for your search.";
    }

    $response = "🛍️ **I found " . count($results['products']) . " product(s):**\n\n";

    // Display applied filters and detected intent
    if (!empty($intent['filters'])) {
      $response .= "**Applied Filters:** ";
      $filters = [];
      foreach ($intent['filters'] as $key => $value) {
        $filters[] = "$key: $value";
      }
      $response .= implode(", ", $filters) . "\n";
    }
    if (!empty($intent['type'])) {
      $response .= "**Detected Intent:** " . $intent['type'] . "\n\n";
    }

    foreach ($results['products'] as $i => $p) {
      $response .= "**" . ($i + 1) . ". " . ($p['products_name'] ?? '') . "**\n";
      $response .= "💰 Price: " . ($p['products_price'] ?? 'N/A') . "€\n";
      $response .= "📦 Stock: " . ($p['products_quantity'] ?? 'N/A') . " units\n";

      $ean = $p['products_ean'] ?? '';
      $sku = $p['products_sku'] ?? '';
      $mpn = $p['products_mpn'] ?? '';
      $isbn = $p['products_isbn'] ?? '';
      $upc = $p['products_upc'] ?? '';
      $jan = $p['products_jan'] ?? '';
      $weight = $p['products_weight'] ?? '';

      if ($ean || $sku || $mpn || $isbn || $upc || $jan) {
        $response .= "📑 References: ";
        $refs = [];
        if ($ean) {
          $refs[] = "EAN: $ean";
        }
        if ($sku) {
          $refs[] = "SKU: $sku";
        }
        if ($mpn) {
          $refs[] = "MPN: $mpn";
        }
        if ($isbn) {
          $refs[] = "ISBN: $isbn";
        }
        if ($upc) {
          $refs[] = "UPC: $upc";
        }
        if ($jan) {
          $refs[] = "JAN: $jan";
        }
        $response .= implode(", ", $refs) . "\n";
      }

      if ($weight) {
        $response .= "⚖️ Weight: $weight kg\n";
      }

      $response .= !empty($p['category_name']) ? "🏷️ Category: " . $p['category_name'] . "\n" : "";
      $response .= "\n";
    }

    return $response;
  }

  /**
   * Checks if the user is an administrator based on the context array.
   *
   * @param array $context The context containing user information.
   * @return bool True if the user is an admin, otherwise false.
   */
  private function isAdmin(array $context): bool
  {
    return isset($context['user_type']) && $context['user_type'] === 'admin';
  }

  /**
   * Redirects to the RAG BI service for administrators.
   *
   * @param string $message The chat message.
   * @param mixed $context The chat context.
   * @return void
   */
  private function redirectToRagBI(string $message, mixed $context): void
  {
    // Build the RAG BI URL
    $ragBiUrl = $this->app->getRagBIEndpoint();

    // Prepare data for RAG BI
    $ragBiData = [
      'message' => $message,
      'context' => $context,
      'admin_mode' => true
    ];

    // Call RAG BI
    $response = $this->callRagBI($ragBiUrl, $ragBiData);

    if ($response) {
      $this->message->sendSuccess([
        'response' => $response['response'],
        'type' => 'admin-rag',
        'confidence' => $response['confidence'] ?? 0.8,
        'metadata' => [
          'admin_mode' => true,
          'rag_bi_used' => true,
          'query_time' => microtime(true)
        ]
      ]);
    } else {
      // L'appel sendError ici était déjà correct
      $this->message->sendError('MCP CustomersProducts service unavailable', 503);
    }
  }

  /**
   * Calls the RAG BI service via an HTTP request.
   *
   * @param string $url The URL of the RAG BI service.
   * @param array $data The data to send in the request body.
   * @return array|null The decoded JSON response from the service, or null on failure.
   */
  private function callRagBI(string $url, array $data): ?array
  {
    try {
      $response = HTTP::getResponse([
        'url' => $url,
        'headers' => [
          'Content-Type' => 'application/json',
          'Accept' => 'application/json'
        ],
        'parameters' => json_encode($data),
        'method' => 'post'
      ]);

      if ($response->getStatusCode() === 200) {
        return json_decode($response->getBody()->getContents(), true);
      }

      return null;
    } catch (\Exception $e) {
      error_log('MCP CustomersProducts API error: ' . $e->getMessage());
      // L'appel sendError ici était déjà correct
      $this->message->sendError($e->getMessage(), 500);
    }

    return null;
  }
}