<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin;

use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Exceptions\McpException;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Transport\SseTransport;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\McpSecurity;

use Psr\Log\LoggerInterface;

/**
 * Class MCPConnector
 *
 * The `MCPConnector` class serves as a singleton-based client for interacting with the MCP (Management & Control Panel) server.
 * It manages the connection, configuration, and communication with the server using a specific protocol and transport layer.
 * This class is designed to be the primary interface for sending requests and retrieving data from the MCP service.
 */
class MCPConnector
{
  /**
   * @var McpProtocol The protocol handler for communication with the MCP server.
   */
  private McpProtocol $protocol;

  /**
   * @var array The current configuration settings for the MCP connection.
   */
  private array $config;

  /**
   * @var self|null The singleton instance of the MCPConnector class.
   */
  private static ?MCPConnector $instance = null;

  /**
   * MCPConnector constructor.
   *
   * Initializes the MCP connector with the provided configuration. It retrieves settings from the database,
   * initializes the SSE transport layer, and sets up the communication protocol.
   *
   * @param array $config An optional array of configuration settings.
   * @param LoggerInterface|null $logger An optional PSR-3 logger instance.
   * @throws McpConfigurationException if a critical configuration key is missing.
   */
  public function __construct(array $config = [], ?LoggerInterface $logger = null)
  {
    $this->config = self::getConfigDb();

    $transport = new SseTransport($this->config, $logger);

    $this->protocol = new McpProtocol($this->config, $transport, $logger);
  }

  /**
   * Retrieves the MCP configuration from the database.
   *
   * This method fetches configuration values from the MCP database table.
   * Connection-specific settings (server_host, server_port, ssl_enabled) come from the database.
   * App-level settings (status, token) come from constants.
   * 
   * Note: Since multiple MCP connections can exist, this method gets the first active connection.
   * For specific connection selection, pass mcp_id parameter.
   *
   * @param int|null $mcpId Optional specific MCP connection ID
   * @return array The associative array of configuration settings.
   * @throws McpException if no active MCP configuration is found.
   */
  public static function getConfigDb(?int $mcpId = null): array
  {
    $db = Registry::get('Db');
    $config = [];

    // Fetch MCP configuration from database
    if ($mcpId !== null) {
      // Get specific MCP connection
      $Qmcp = $db->prepare('SELECT * 
                            FROM :table_mcp 
                            WHERE mcp_id = :mcp_id
                          ');
      $Qmcp->bindInt(':mcp_id', $mcpId);
      $Qmcp->execute();
    } else {
      // Get first active MCP connection
      $Qmcp = $db->prepare('SELECT * 
                            FROM :table_mcp 
                            WHERE status = 1 
                            ORDER BY mcp_id 
                            LIMIT 1
                          ');
      $Qmcp->execute();
    }

    if ($Qmcp->fetch()) {
      // Connection-specific settings from database
      $config['mcp_id'] = (int)$Qmcp->valueInt('mcp_id');
      $config['username'] = $Qmcp->value('username');
      $config['mcp_key'] = $Qmcp->value('mcp_key'); // Token from database
      $config['server_host'] = $Qmcp->value('server_host') ?: 'localhost';
      $config['server_port'] = (int)$Qmcp->valueInt('server_port') ?: 3001;
      $config['ssl'] = (bool)$Qmcp->valueInt('ssl_enabled');
      $config['status'] = (int)$Qmcp->valueInt('status'); // Status from database
      
      // Alert and monitoring settings from database
      $config['alert_threshold'] = (int)$Qmcp->valueInt('alert_threshold') ?: 20;
      $config['latency_threshold'] = (int)$Qmcp->valueInt('latency_threshold') ?: 1000;
      $config['downtime_threshold'] = (int)$Qmcp->valueInt('downtime_threshold') ?: 300;
      $config['data_retention'] = (int)$Qmcp->valueInt('data_retention') ?: 7;
      $config['alert_notification'] = (bool)$Qmcp->valueInt('alert_notification');
      
      // Permissions from database
      $config['select_data'] = (bool)$Qmcp->valueInt('select_data');
      $config['update_data'] = (bool)$Qmcp->valueInt('update_data');
      $config['create_data'] = (bool)$Qmcp->valueInt('create_data');
      $config['delete_data'] = (bool)$Qmcp->valueInt('delete_data');
      $config['create_db'] = (bool)$Qmcp->valueInt('create_db');
    } else {
      error_log('[MCPConnector] No active MCP configuration found in database');
      throw new McpException('No active MCP configuration found');
    }

    // App-level settings from constants (backward compatibility)
    // These can override database settings if defined
    if (\defined('CLICSHOPPING_APP_MCP_MC_TOKEN') && !empty(CLICSHOPPING_APP_MCP_MC_TOKEN)) {
      $config['token'] = CLICSHOPPING_APP_MCP_MC_TOKEN;
    } else {
      $config['token'] = $config['mcp_key'] ?? '';
    }
    
    if (\defined('CLICSHOPPING_APP_MCP_MC_STATUS')) {
      $config['app_status'] = CLICSHOPPING_APP_MCP_MC_STATUS;
    }

    // Default endpoint
    if (!isset($config['mcp_endpoint'])) {
      $config['mcp_endpoint'] = '/api/ai/chat/process';
    }

    if (empty($config['server_host'])) {
      error_log('[MCPConnector] Missing required config key: server_host');
      throw new McpException('Missing required config: server_host');
    }

    return $config;
  }

  /**
   * Gets the singleton instance of the `MCPConnector`.
   *
   * If an instance does not yet exist, it creates a new one by fetching the configuration from the database.
   * This ensures that only one instance of the connector is used throughout the application's lifecycle.
   *
   * @return self The single instance of the `MCPConnector` class.
   */
  public static function getInstance(): self
  {
    if (self::$instance === null) {
      $config = self::getConfigDb();

      self::$instance = new self($config);
    }

    return self::$instance;
  }

  /**
   * Sends a request to the MCP server.
   *
   * This method acts as a proxy to the underlying protocol handler, sending a request with a specified method
   * and parameters to the MCP service.
   *
   * @param string $method The name of the method to call on the server.
   * @param array $params An associative array of parameters to pass with the request.
   * @return array The result returned by the server.
   */
  public function request(string $method, array $params = []): array
  {
    $result = $this->protocol->sendRequest($method, $params);

    return $result;
  }

  /**
   * Retrieves connection statistics from the protocol handler.
   *
   * This can be used for monitoring or debugging purposes to get information about the connection.
   *
   * @return array An array containing connection statistics.
   */
  public function getStats(): array
  {
    return $this->protocol->getStats();
  }

  /**
   * Gets the current configuration settings.
   *
   * @return array The associative array of the current configuration.
   */
  public function getConfig(): array
  {
    return $this->config;
  }

  /**
   * Updates the connector's configuration with new values.
   *
   * Merges the provided array of new settings with the existing configuration and updates the transport layer.
   * It casts `server_port` to an integer and `ssl` to a boolean for proper type handling.
   *
   * @param array $newConfig An associative array of new configuration settings to apply.
   * @throws McpConfigurationException if the new configuration is invalid.
   */
  public function updateConfig(array $newConfig): void
  {
    $newConfig['server_port'] = (int)($newConfig['server_port'] ?? $this->config['server_port'] ?? 3001);
    $newConfig['ssl'] = filter_var($newConfig['ssl'] ?? $this->config['ssl'] ?? false, FILTER_VALIDATE_BOOLEAN);

    $this->config = array_merge($this->config, $newConfig);
    $this->protocol->getTransport()->setOptions($this->config);
  }


  /**
   * Retrieves the current MCP session token.
   *
   * This method reads the token from the configuration (database mcp_key or constant).
   * Priority: 1) Database mcp_key, 2) Constant CLICSHOPPING_APP_MCP_MC_TOKEN
   *
   * @return string The MCP session token.
   * @throws \RuntimeException if the token is not configured.
   */
  public function getSessionToken(): string
  {
    // Priority 1: Token from configuration (database mcp_key)
    if (!empty($this->config['mcp_key'])) {
      return $this->config['mcp_key'];
    }
    
    // Priority 2: Token from configuration (merged from constant)
    if (!empty($this->config['token'])) {
      return $this->config['token'];
    }
    
    // Priority 3: Fallback to constant
    if (defined('CLICSHOPPING_APP_MCP_MC_TOKEN')) {
      return CLICSHOPPING_APP_MCP_MC_TOKEN;
    }

    throw new \RuntimeException('MCP Token is not configured');
  }

  /**
   * Destructor for the `MCPConnector` class.
   *
   * Ensures that the connection to the MCP server is properly closed when the object is destroyed, preventing
   * open connections and resource leaks.
   */
  public function __destruct()
  {
    if (isset($this->protocol)) {
      $this->protocol->disconnect();
    }
  }
}