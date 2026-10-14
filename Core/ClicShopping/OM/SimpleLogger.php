<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

use ClicShopping\OM\CLICSHOPPING;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

/**
 * A simple logger implementation that writes log messages to a file.
 *
 * This class implements the PSR-3 LoggerInterface and provides methods for logging
 * messages at various levels (emergency, alert, critical, error, warning, notice, info, debug).
 * All log messages are written to a file named 'simple_logger.log' in the 'Work/Log' directory
 * under the base directory defined by CLICSHOPPING::BASE_DIR.
 */
class SimpleLogger implements LoggerInterface
{
  public function emergency(Stringable|string $message, array $context = []): void { $this->log(LogLevel::EMERGENCY, $message, $context); }
  public function alert(Stringable|string $message, array $context = []): void     { $this->log(LogLevel::ALERT, $message, $context); }
  public function critical(Stringable|string $message, array $context = []): void  { $this->log(LogLevel::CRITICAL, $message, $context); }
  public function error(Stringable|string $message, array $context = []): void     { $this->log(LogLevel::ERROR, $message, $context); }
  public function warning(Stringable|string $message, array $context = []): void   { $this->log(LogLevel::WARNING, $message, $context); }
  public function notice(Stringable|string $message, array $context = []): void    { $this->log(LogLevel::NOTICE, $message, $context); }
  public function info(Stringable|string $message, array $context = []): void      { $this->log(LogLevel::INFO, $message, $context); }
  public function debug(Stringable|string $message, array $context = []): void     { $this->log(LogLevel::DEBUG, $message, $context); }

  /**
   * Logs a message with a given level to a file.
   *
   * @param string $level The log level (e.g., 'error', 'info', etc.).
   * @param Stringable|string $message The log message.
   * @param array $context Additional context for the log message (not used in this implementation).
   *
   * @return void
   */
  public function log($level, Stringable|string $message, array $context = []): void
  {
    $msg = DateTime::getNow() . ' ' . '[' . strtoupper($level) . '] ' . $message . '\n';

    file_put_contents(CLICSHOPPING::getWorkDirectory('Log') . '/simple_logger.log', $msg, FILE_APPEND);
  }
}
