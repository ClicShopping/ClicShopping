<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Transport;

use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Exceptions\McpConnectionException;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Exceptions\McpProtocolException;

/**
 * Interface TransportInterface
 *
 * This interface defines the contract for any transport layer implementation
 * that wishes to communicate with a Model Context Protocol (MCP) server.
 * It ensures that all transport classes provide a consistent set of methods
 * for connection management, data transfer, and state monitoring.
 */
interface TransportInterface
{
  /**
   * Sets or updates the transport's configuration options.
   *
   * @param array $options An associative array of configuration settings.
   */
  public function setOptions(array $options): void;

  /**
   * Attempts to establish a connection to the MCP server.
   *
   * @return bool True on a successful connection.
   * @throws McpConnectionException If the connection attempt fails.
   */
  public function connect(): bool;

  /**
   * Sends a message to the MCP server and returns the response.
   *
   * @param array $message The message payload to send.
   * @return array|null The response from the server, or null on failure.
   * @throws McpConnectionException If the connection is lost.
   * @throws McpProtocolException If a protocol-level error occurs.
   */
  public function send(array $message): ?array;

  /**
   * Gracefully disconnects from the MCP server.
   */
  public function disconnect(): void;

  /**
   * Checks the current connection status.
   *
   * @return bool True if the transport is currently connected, false otherwise.
   */
  public function isConnected(): bool;

  /**
   * Retrieves a set of performance and usage statistics for the transport.
   *
   * @return array An associative array of statistics (e.g., messages sent, errors).
   */
  public function getStats(): array;

  /**
   * Sets a callback function to handle messages received asynchronously
   * (e.g., in a streaming or notification-based transport).
   *
   * @param callable $callback The callback function to execute.
   */
  public function onMessage(callable $callback): void;

  /**
   * Closes and cleans up all resources associated with the transport.
   * This method should be idempotent and safe to call multiple times.
   */
  public function close(): void;
}