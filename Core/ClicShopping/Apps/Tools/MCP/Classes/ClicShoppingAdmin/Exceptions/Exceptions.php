<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Exceptions;

/**
 * Class McpException
 *
 * This class serves as the base exception for all custom exceptions related to the MCP
 * (Model Context Protocol) component. By extending \Exception, it allows for specific
 * handling of MCP-related errors, separating them from other application exceptions.
 */
class McpException extends \Exception {}

/**
 * Class McpConnectionException
 *
 * This exception is thrown when an error occurs during the process of connecting to
 * the MCP service. It signifies issues such as network failures, incorrect host/port,
 * or the service being unavailable.
 */
class McpConnectionException extends McpException {}

/**
 * Class McpProtocolException
 *
 * This exception is used for errors that violate the communication protocol with the MCP
 * service. This can include malformed requests, unexpected response formats, or
 * server-side protocol errors.
 */
class McpProtocolException extends McpException {}

/**
 * Class McpConfigurationException
 *
 * This exception is thrown when the configuration for the MCP service is invalid or
 * incomplete. It indicates a problem with the setup data, such as missing required
 * fields or invalid values.
 */
class McpConfigurationException extends McpException {}