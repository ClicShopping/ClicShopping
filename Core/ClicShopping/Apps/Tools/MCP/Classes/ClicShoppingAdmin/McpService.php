<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Exceptions\McpConnectionException;
use Psr\Log\LoggerInterface;
use ClicShopping\OM\SimpleLogger;

/**
 * Class McpService
 *
 * This class serves as the main entry point for interacting with the MCP (Management & Control Panel) service.
 * It provides a high-level API for various tasks like sending messages, generating content,
 * and checking system health, abstracting the underlying communication details.
 */
class McpService
{
  /**
   * @var MCPConnector The connector instance used for communication with the MCP.
   */
  private MCPConnector $connector;

  /**
   * @var LoggerInterface The logger instance for logging service-related events.
   */
  private LoggerInterface $logger;

  /**
   * @var self|null The singleton instance of the class.
   */
  private static ?McpService $instance = null;

  /**
   * McpService constructor.
   *
   * Initializes the service by getting the MCPConnector and setting up the logger.
   *
   * @param LoggerInterface|null $logger An optional PSR-3 logger instance.
   */
  public function __construct(?LoggerInterface $logger = null)
  {
    $this->connector = MCPConnector::getInstance();

    if (!Registry::exists('SimpleLogger')) {
      Registry::set('SimpleLogger', new SimpleLogger());
    }

    $this->logger = $logger ?? Registry::get('SimpleLogger');
  }

  /**
   * Gets the singleton instance of McpService.
   *
   * This method ensures that only one instance of the service is created throughout the application.
   *
   * @return self The single instance of the class.
   */
  public static function getInstance(): self
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Sends a message to the AI model for processing.
   *
   * This method handles the 'chat' request, validates the input, and sends it to the MCP
   * via the connector. It also logs any connection errors.
   *
   * @param string $message The message to send. It can be a string or an object convertible to a string.
   * @param array $context An optional array of context to provide to the AI model.
   * @return array The response from the AI model.
   * @throws \InvalidArgumentException If the message or context is invalid.
   * @throws McpConnectionException If the connection to the MCP fails.
   */
  public function sendMessage(string $message, array $context = []): array
  {
    if (!is_string($message)) {
      if (is_array($message)) {
        $message = implode(' ', array_map('strval', $message));
      } elseif (is_object($message) && method_exists($message, '__toString')) {
        $message = (string)$message;
      } else {
        throw new \InvalidArgumentException('Message must be a string or convertible to string');
      }
    }

    if (!is_array($context)) {
      throw new \InvalidArgumentException('Context must be an array');
    }

    try {
      $result = $this->connector->request('chat', [
        'message' => $message,
        'context' => $context
      ]);

      return $result;
    } catch (McpConnectionException $e) {
      $this->logger->error('MCP chat request failed', [
        'error' => $e->getMessage(),
        'context' => $context
      ]);

      throw $e;
    }
  }

  /**
   * Generates a product description using the AI model.
   *
   * This method sends a 'generate_description' request to the MCP with product data.
   *
   * @param array $productData An array containing product information (e.g., product ID, name, features).
   * @return string|null The generated description, or null if the request fails.
   */
  public function generateProductDescription(array $productData): ?string
  {
    try {
      $response = $this->connector->request('generate_description', $productData);
      return $response['description'] ?? null;
    } catch (McpConnectionException $e) {
      $this->logger->error('Failed to generate product description', [
        'error' => $e->getMessage(),
        'product' => $productData['products_id'] ?? 'unknown'
      ]);
      return null;
    }
  }

  /**
   * Optimizes product SEO metadata using the AI model.
   *
   * This method sends an 'optimize_seo' request to the MCP to get optimized SEO titles, keywords, etc.
   *
   * @param array $productData An array containing product data for SEO optimization.
   * @return array|null An array of optimized SEO metadata, or null if the request fails.
   */
  public function optimizeProductSeo(array $productData): ?array
  {
    try {
      return $this->connector->request('optimize_seo', $productData);
    } catch (McpConnectionException $e) {
      $this->logger->error('Failed to optimize product SEO', [
        'error' => $e->getMessage(),
        'product' => $productData['products_id'] ?? 'unknown'
      ]);
      return null;
    }
  }

  /**
   * Gets the current health status of the MCP service.
   *
   * This method retrieves statistics from the connector and formats them into a comprehensive
   * health status report, including uptime, request counts, and error rates.
   *
   * @return array An associative array with the service's health status.
   */
  public function getHealthStatus(): array
  {
    try {
      $mcpStats = $this->connector->getStats();
      $healthStatus = [
        'status' => 'healthy',
        'last_activity' => $mcpStats['last_activity'],
        'total_requests' => $mcpStats['requests_sent'],
        'errors' => $mcpStats['errors'],
        'uptime' => time() - ($mcpStats['connection_time'] ?? time())
      ];

      if ($mcpStats['errors'] > 0) {
        $healthStatus['status'] = 'warning';
      }

      if (!$this->connector->getConfig()['status']) {
        $healthStatus['status'] = 'disabled';
      }

      return $healthStatus;
    } catch (\Exception $e) {
      return [
        'status' => 'error',
        'message' => $e->getMessage()
      ];
    }
  }

  /**
   * Validates the current MCP service configuration.
   *
   * This method checks for the presence of required configuration fields and attempts a connection
   * to the MCP to verify that the settings are correct and the service is reachable.
   *
   * @return array An array with a 'valid' boolean and an 'issues' array detailing any problems found.
   */
  public function validateConfiguration(): array
  {
    $issues = [];
    $config = $this->connector->getConfig();
    $array_connexion = ['server_host', 'server_port'];

    // Check required fields
    foreach ($array_connexion as $field) {
      if (!isset($config[$field]) || $config[$field] === '') {
        $issues[] = "Missing {$field} configuration";
      }
    }

    // Test basic TCP connectivity if basic config is present
    if (empty($issues)) {
      $host = $config['server_host'] ?? 'localhost';
      $port = (int)($config['server_port'] ?? 0);
      $timeout = 2;
      $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
      if (is_resource($socket)) {
        fclose($socket);
      } else {
        $issues[] = "Connection test failed: {$errstr} ({$errno})";
      }
    }

    $array_result = [
      'valid' => empty($issues),
      'issues' => $issues
    ];

    return $array_result;
  }
}
