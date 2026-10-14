<?php
/**
 * API d'authentification MCP - Intégration avec le système clic_mcp
 * Structure ClicShopping : Tools/MCP/Sites/Shop/Pages/Auth
 */

namespace ClicShopping\Apps\Tools\MCP\Sites\Shop\Pages\Auth;


use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\McpSecurity;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\McpPermissions;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\Message;

class MCPAuth {
    private $validMcpKeys;
    private $accessLevels;
    private $mcpConfigs;
    private $allowedOrigins;
    
    public function __construct() {
        // Load MCP configurations from database
        $this->loadMcpConfigurations();
        
        // Clés d'API valides (en production, stocker en base de données chiffrée)
        $this->validMcpKeys = [
            'customer_products' => [
                'mcp_products_key_' . date('Ymd'), // Clé rotative quotidienne
                getenv('CLICSHOPPING_PRODUCTS_API_KEY') ?: 'mcp_products_default_key'
            ],
            'ragbi' => [
                'mcp_analytics_key_' . date('Ymd'), // Clé rotative quotidienne
                getenv('CLICSHOPPING_ANALYTICS_API_KEY') ?: 'mcp_analytics_default_key'
            ],
            'admin' => [
                'mcp_admin_key_' . date('Ymd'), // Clé rotative quotidienne
                getenv('CLICSHOPPING_ADMIN_API_KEY') ?: 'mcp_admin_default_key'
            ]
        ];
        
        $this->accessLevels = ['customer_products', 'ragbi', 'admin'];
    }
    
    /**
     * Load MCP configurations from database
     * Supports multiple MCP instances with different server configurations
     */
    private function loadMcpConfigurations(): void {
        $this->mcpConfigs = [];
        $this->allowedOrigins = [];
        
        try {
            $db = Registry::get('Db');
            
            // Fetch all active MCP configurations
            $Qmcp = $db->prepare('SELECT mcp_id, username, mcp_key, server_host, server_port, ssl_enabled, status
                                  FROM :table_mcp 
                                  WHERE status = 1
                                  ORDER BY mcp_id
                                ');
            $Qmcp->execute();
            
            while ($Qmcp->fetch()) {
                $mcpId = (int)$Qmcp->valueInt('mcp_id');
                $serverHost = $Qmcp->value('server_host') ?: 'localhost';
                $serverPort = (int)$Qmcp->valueInt('server_port') ?: 3001;
                $sslEnabled = (bool)$Qmcp->valueInt('ssl_enabled');
                
                // Build the origin URL based on SSL and port configuration
                $protocol = $sslEnabled ? 'https' : 'http';
                $origin = $protocol . '://' . $serverHost;
                
                // Add port if it's not the default for the protocol
                if ((!$sslEnabled && $serverPort != 80) || ($sslEnabled && $serverPort != 443)) {
                    $origin .= ':' . $serverPort;
                }
                
                $this->mcpConfigs[$mcpId] = [
                    'mcp_id' => $mcpId,
                    'username' => $Qmcp->value('username'),
                    'mcp_key' => $Qmcp->value('mcp_key'),
                    'server_host' => $serverHost,
                    'server_port' => $serverPort,
                    'ssl_enabled' => $sslEnabled,
                    'origin' => $origin
                ];
                
                // Add to allowed origins list
                if (!in_array($origin, $this->allowedOrigins)) {
                    $this->allowedOrigins[] = $origin;
                }
            }
            
            // Fallback to localhost if no configurations found
            if (empty($this->allowedOrigins)) {
                $this->allowedOrigins[] = 'http://localhost:3001';
                error_log('MCP Auth: No active MCP configurations found, using default localhost:3001');
            }
            
        } catch (\Exception $e) {
            error_log('MCP Auth: Failed to load configurations - ' . $e->getMessage());
            // Fallback to default
            $this->allowedOrigins[] = 'http://localhost:3001';
        }
    }
    
    /**
     * Get allowed CORS origins
     */
    public function getAllowedOrigins(): array {
        return $this->allowedOrigins;
    }
    
    /**
     * Check if an origin is allowed
     */
    public function isOriginAllowed(string $origin): bool {
        return in_array($origin, $this->allowedOrigins);
    }
    
    /**
     * Valide une clé d'API pour un niveau d'accès donné
     */
    public function validateMcpKey(string $mcpKey, string $accessLevel): bool {
        if (!in_array($accessLevel, $this->accessLevels)) {
            return false;
        }
        
        if (!isset($this->validMcpKeys[$accessLevel])) {
            return false;
        }
        
        return in_array($mcpKey, $this->validMcpKeys[$accessLevel]);
    }
    
    /**
     * Valide les headers de sécurité requis
     */
    public function validateSecurityHeaders(): bool {
        $requiredHeaders = [
            'HTTP_X_MCP_SOURCE' => 'clicshopping-mcp-server',
            'HTTP_X_MCP_VERSION' => '1.0.0',
            'HTTP_USER_AGENT' => 'ClicShopping-MCP/1.0.0'
        ];
        
        foreach ($requiredHeaders as $header => $expectedValue) {
            if (!isset($_SERVER[$header]) || $_SERVER[$header] !== $expectedValue) {
                error_log("MCP Auth: Invalid header $header");
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Log des tentatives d'authentification
     */
    public function logAuthAttempt(string $mcpKey, string $accessLevel, bool $success, string $ip): void {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $ip,
            'access_level' => $accessLevel,
            'mcp_key_prefix' => substr($mcpKey, 0, 8) . '...',
            'success' => $success ? 'SUCCESS' : 'FAILED',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        error_log("MCP Auth: " . json_encode($logEntry));
    }
    
    /**
     * Traite la requête d'authentification
     */
    public function handleRequest(): array {
        try {
            // Vérifier la méthode HTTP
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return [
                    'status' => 'error',
                    'message' => 'Only POST method allowed',
                    'code' => 'INVALID_METHOD'
                ];
            }
            
            // Vérifier les headers de sécurité
            if (!$this->validateSecurityHeaders()) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid security headers',
                    'code' => 'INVALID_HEADERS'
                ];
            }
            
            // Récupérer la clé d'API
            $mcpKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
            if (empty($mcpKey)) {
                return [
                    'status' => 'error',
                    'message' => 'API key required',
                    'code' => 'NO_API_KEY'
                ];
            }
            
            // Récupérer les données de la requête
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid JSON data',
                    'code' => 'INVALID_JSON'
                ];
            }
            
            $accessLevel = $input['access_level'] ?? '';
            $action = $input['action'] ?? '';
            
            if ($action !== 'validate_access') {
                return [
                    'status' => 'error',
                    'message' => 'Invalid action',
                    'code' => 'INVALID_ACTION'
                ];
            }
            
            if (empty($accessLevel)) {
                return [
                    'status' => 'error',
                    'message' => 'Access level required',
                    'code' => 'NO_ACCESS_LEVEL'
                ];
            }
            
            // Valider la clé d'API
            $isValid = $this->validateMcpKey($mcpKey, $accessLevel);
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            
            // Logger la tentative
            $this->logAuthAttempt($mcpKey, $accessLevel, $isValid, $clientIp);
            
            if (!$isValid) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid API key for access level',
                    'code' => 'INVALID_API_KEY'
                ];
            }
            
            return [
                'status' => 'success',
                'message' => 'Access validated',
                'data' => [
                    'access_level' => $accessLevel,
                    'valid_until' => date('Y-m-d H:i:s', strtotime('+1 hour')),
                    'permissions' => $this->getPermissionsForLevel($accessLevel)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("MCP Auth Error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Authentication service error',
                'code' => 'SERVICE_ERROR'
            ];
        }
    }
    
    /**
     * Retourne les permissions pour un niveau d'accès
     */
    private function getPermissionsForLevel(string $accessLevel): array {
        $permissions = [
            'customer_products' => [
                'read_products',
                'search_products',
                'get_product_details',
                'get_categories'
            ],
            'ragbi' => [
                'read_analytics',
                'generate_reports',
                'execute_queries',
                'access_business_data'
            ],
            'admin' => [
                'read_all',
                'write_all',
                'manage_users',
                'system_admin'
            ]
        ];
        
        return $permissions[$accessLevel] ?? [];
    }
}

// Traitement de la requête
$authAPI = new MCPAuth();

// Set CORS headers dynamically based on database configuration
header('Content-Type: application/json');

// Handle CORS origin validation
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!empty($requestOrigin) && $authAPI->isOriginAllowed($requestOrigin)) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
} else {
    // Use first allowed origin as default
    $allowedOrigins = $authAPI->getAllowedOrigins();
    if (!empty($allowedOrigins)) {
        header('Access-Control-Allow-Origin: ' . $allowedOrigins[0]);
    }
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, X-MCP-Source, X-MCP-Version');
header('Access-Control-Allow-Credentials: true');

// Gérer les requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$response = $authAPI->handleRequest();

http_response_code($response['status'] === 'success' ? 200 : 401);
echo json_encode($response, JSON_PRETTY_PRINT);
?>