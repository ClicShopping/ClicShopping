<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\HttpRequest;

use ClicShopping\OM\CLICSHOPPING;
use Exception;
use InvalidArgumentException;

/**
 * Class Stream
 *
 * Provides low-level HTTP(S) communication using socket streams.
 * Supports basic HTTP requests, SSL configuration, and streaming data consumption.
 */
class Stream
{
  /**
   * Default SSL context options for HTTPS connections.
   */
  private static array $defaultSslOptions = [
    'verify_peer' => true,
    'verify_peer_name' => true
  ];

  /**
   * Default read timeout in seconds.
   */
  private static int $defaultTimeout = 30;

  /**
   * Default connection timeout in seconds.
   */
  private static int $defaultConnectionTimeout = 10;

  /**
   * Execute an HTTP request with error handling.
   *
   * @param array $parameters Request configuration.
   * @return string|false Response body or false on failure.
   */
  public static function execute($parameters)
  {
    try {
      return self::executeHttpRequest($parameters);
    } catch (Exception $e) {
      trigger_error($e->getMessage());
      return false;
    }
  }

  /**
   * Perform an HTTP request and return the response body.
   *
   * @param array $parameters Request configuration (url, method, parameters, header).
   * @return string Response body.
   * @throws Exception on error or invalid response.
   */
  public static function executeHttpRequest(array $parameters): string
  {
    self::validateParameters($parameters);

    $parsedUrl = parse_url($parameters['url']);
    if (!$parsedUrl || !isset($parsedUrl['host'])) {
      throw new InvalidArgumentException("Invalid URL: {$parameters['url']}");
    }

    $socket = self::createSocket($parsedUrl, $parameters);

    try {
      self::sendHttpRequest($socket, $parsedUrl, $parameters);
      return self::readHttpResponse($socket);
    } finally {
      fclose($socket);
    }
  }

  /**
   * Perform a streaming HTTP request and invoke a callback on each chunk of data.
   *
   * @param array $parameters Request configuration.
   * @param callable $onData Callback receiving string chunks of response body.
   * @throws Exception on error or invalid response.
   */
  public static function executeStreaming(array $parameters, callable $onData): void
  {
    self::validateParameters($parameters);

    $parsedUrl = parse_url($parameters['url']);
    if (!$parsedUrl || !isset($parsedUrl['host'])) {
      throw new InvalidArgumentException("Invalid URL: {$parameters['url']}");
    }

    $socket = self::createSocket($parsedUrl, $parameters);

    try {
      self::sendHttpRequest($socket, $parsedUrl, $parameters);
      self::readHttpHeaders($socket); // Skip headers
      self::streamHttpBody($socket, $onData);
    } finally {
      fclose($socket);
    }
  }

  /**
   * Validate required parameters for HTTP requests.
   *
   * @param array $parameters
   * @throws InvalidArgumentException
   */
  private static function validateParameters(array $parameters): array
  {
    if (!isset($parameters['url'])) {
      throw new InvalidArgumentException('URL parameter is required');
    }

    $parameters['method']     = $parameters['method']     ?? 'get';
    $parameters['parameters'] = $parameters['parameters'] ?? '';
    $parameters['header']     = $parameters['header']     ?? [];

    return $parameters;
  }

  /**
   * Create a socket connection to the target server.
   *
   * @param array $parsedUrl Parsed result of parse_url().
   * @param array $parameters Connection options.
   * @return resource Socket resource.
   * @throws Exception on connection failure.
   */
  private static function createSocket(array $parsedUrl, array $parameters)
  {
    $scheme = $parsedUrl['scheme'] ?? 'http';
    $host = $parsedUrl['host'];
    $port = $parsedUrl['port'] ?? ($scheme === 'https' ? 443 : 80);
    $socketUrl = ($scheme === 'https' ? 'ssl://' : '') . $host . ':' . $port;
    $timeout = $parameters['connection_timeout'] ?? self::$defaultConnectionTimeout;

    $context = null;
    if ($scheme === 'https') {
      $sslOptions = self::$defaultSslOptions;

      if (isset($parameters['cafile']) && file_exists($parameters['cafile'])) {
        $sslOptions['cafile'] = $parameters['cafile'];
      } elseif (defined('CLICSHOPPING::BASE_DIR')) {
        $defaultCaFile = CLICSHOPPING::BASE_DIR . 'External/cacert.pem';
        if (file_exists($defaultCaFile)) {
          $sslOptions['cafile'] = $defaultCaFile;
        }
      }

      if (isset($parameters['certificate']) && file_exists($parameters['certificate'])) {
        $sslOptions['local_cert'] = $parameters['certificate'];
      }

      $context = stream_context_create(['ssl' => $sslOptions]);
    }

    $socket = stream_socket_client(
      $socketUrl,
      $errno,
      $errstr,
      $timeout,
      STREAM_CLIENT_CONNECT,
      $context
    );

    if (!$socket) {
      throw new Exception("Failed to connect to {$socketUrl}: {$errstr} ({$errno})");
    }

    $readTimeout = $parameters['read_timeout'] ?? self::$defaultTimeout;
    stream_set_timeout($socket, $readTimeout);

    return $socket;
  }

  /**
   * Send an HTTP request through the socket.
   *
   * @param resource $socket
   * @param array $parsedUrl
   * @param array $parameters
   * @throws Exception on write failure.
   */
  private static function sendHttpRequest($socket, array $parsedUrl, array $parameters): void
  {
    $method = strtoupper($parameters['method']);
    $path = ($parsedUrl['path'] ?? '/') . (isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '');

    $headers = [
      'Host' => $parsedUrl['host'],
      'User-Agent' => 'ClicShopping-HttpClient/1.0',
      'Connection' => 'close'
    ];

    if (!empty($parameters['header'])) {
      foreach ($parameters['header'] as $header) {
        if (str_contains($header, ':')) {
          [$name, $value] = explode(':', $header, 2);
          $headers[trim($name)] = trim($value);
        }
      }
    }

    $body = '';
    if ($method === 'POST' && !empty($parameters['parameters'])) {
      $body = $parameters['parameters'];
      $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/x-www-form-urlencoded';
      $headers['Content-Length'] = strlen($body);
    } elseif ($method === 'POST' && empty($parameters['parameters'])) {
      $method = 'GET';
    }

    $request = "{$method} {$path} HTTP/1.1\r\n";
    foreach ($headers as $name => $value) {
      $request .= "{$name}: {$value}\r\n";
    }
    $request .= "\r\n";

    if (!empty($body)) {
      $request .= $body;
    }

    $written = fwrite($socket, $request);
    if ($written === false || $written < strlen($request)) {
      throw new Exception('Failed to send HTTP request');
    }
  }

  /**
   * Read and parse the HTTP response.
   *
   * @param resource $socket
   * @return string Response body.
   * @throws Exception on protocol or status error.
   */
  private static function readHttpResponse($socket): string
  {
    $headers = self::readHttpHeaders($socket);
    return self::readHttpBody($socket, $headers);
  }

  /**
   * Read and parse the HTTP headers.
   *
   * @param resource $socket
   * @return array Parsed headers.
   * @throws Exception on malformed or error response.
   */
  private static function readHttpHeaders($socket): array
  {
    $statusLine = fgets($socket);

    if ($statusLine === false) {
      throw new Exception('Failed to read HTTP status line');
    }

    if (!preg_match('/^HTTP\/\d\.\d\s+(\d+)/', trim($statusLine), $matches)) {
      throw new Exception('Invalid HTTP response: ' . trim($statusLine));
    }

    $statusCode = (int)$matches[1];
    $headers = ['status_code' => $statusCode];

    while (($line = fgets($socket)) !== false) {
      $line = trim($line);
      if ($line === '') break;
      if (str_contains($line, ':')) {
        [$name, $value] = explode(':', $line, 2);
        $headers[strtolower(trim($name))] = trim($value);
      }
    }

    if ($statusCode >= 400) {
      throw new Exception("HTTP error: {$statusCode}");
    }

    return $headers;
  }

  /**
   * Read the HTTP body based on headers.
   *
   * @param resource $socket
   * @param array $headers
   * @return string Response body.
   */
  private static function readHttpBody($socket, array $headers): string
  {
    $body = '';
    $contentLength = isset($headers['content-length']) ? (int)$headers['content-length'] : null;
    $isChunked = isset($headers['transfer-encoding']) &&
      stripos($headers['transfer-encoding'], 'chunked') !== false;

    if ($isChunked) {
      $body = self::readChunkedBody($socket);
    } elseif ($contentLength !== null) {
      $body = self::readFixedLengthBody($socket, $contentLength);
    } else {
      while (!feof($socket)) {
        $chunk = fread($socket, 8192);
        if ($chunk === false) break;
        $body .= $chunk;
      }
    }

    return $body;
  }

  /**
   * Stream HTTP body chunks and pass them to a callback.
   *
   * @param resource $socket
   * @param callable $onData
   */
  private static function streamHttpBody($socket, callable $onData): void
  {
    while (!feof($socket)) {
      $chunk = fread($socket, 8192);
      if ($chunk === false || $chunk === '') {
        usleep(1000);
        continue;
      }

      $onData($chunk);
    }
  }

  /**
   * Read chunked HTTP body.
   *
   * @param resource $socket
   * @return string
   */
  private static function readChunkedBody($socket): string
  {
    $body = '';

    while (true) {
      $sizeLine = fgets($socket);
      if ($sizeLine === false) break;

      $chunkSize = hexdec(trim($sizeLine));
      if ($chunkSize === 0) {
        while (($line = fgets($socket)) !== false) {
          if (trim($line) === '') break;
        }
        break;
      }

      $chunk = fread($socket, $chunkSize);
      if ($chunk === false) break;

      $body .= $chunk;
      fgets($socket);
    }

    return $body;
  }

  /**
   * Read fixed-length HTTP body.
   *
   * @param resource $socket
   * @param int $length
   * @return string
   */
  private static function readFixedLengthBody($socket, int $length): string
  {
    $body = '';
    $remaining = $length;

    while ($remaining > 0 && !feof($socket)) {
      $chunk = fread($socket, min(8192, $remaining));
      if ($chunk === false) break;

      $body .= $chunk;
      $remaining -= strlen($chunk);
    }

    return $body;
  }

  /**
   * Check if the environment supports HTTP requests via stream.
   *
   * @return bool
   */
  public static function canUse(): bool
  {
    return extension_loaded('openssl') && function_exists('stream_socket_client');
  }

  /**
   * Check if the environment supports streaming operations.
   *
   * @return bool
   */
  public static function canStream(): bool
  {
    return self::canUse() && function_exists('stream_set_timeout');
  }

  /**
   * Override default SSL context options.
   *
   * @param array $options
   */
  public static function setDefaultSslOptions(array $options): void
  {
    self::$defaultSslOptions = array_merge(self::$defaultSslOptions, $options);
  }

  /**
   * Set the default socket read timeout.
   *
   * @param int $seconds
   */
  public static function setDefaultTimeout(int $seconds): void
  {
    self::$defaultTimeout = $seconds;
  }

  /**
   * Set the default socket connection timeout.
   *
   * @param int $seconds
   */
  public static function setDefaultConnectionTimeout(int $seconds): void
  {
    self::$defaultConnectionTimeout = $seconds;
  }
}
