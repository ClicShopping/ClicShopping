<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\ClicShoppingAdmin;

use function defined;
/**
 * Class LoggerAdmin
 * Handles logging and execution time tracking for the ClicShoppingAdmin site.
 */
class LoggerAdmin
{
  public $timerStart;
  public $timerStop;
  public $timer_total;

// class constructor

  /**
   * Constructor method that initializes the timer by invoking the timerStart method.
   *
   * @return void
   */
  public function __construct()
  {
    $this->timerStart();
  }

  /**
   * Initializes the timer by setting the start time.
   * If a predefined constant PAGE_PARSE_START_TIME exists, it uses that value; otherwise, it uses the current time in microseconds.
   *
   * @return void
   */
  public function timerStart()
  {
    if (defined("PAGE_PARSE_START_TIME")) {
      $this->timerStart = PAGE_PARSE_START_TIME;
    } else {
      $this->timerStart = microtime();
    }
  }

  /**
   * Stops the timer and calculates the total elapsed time since the timer started.
   * Optionally displays the elapsed time if the parameter is set to true.
   *
   * @param bool $display Determines whether to display the elapsed time. Default is false.
   * @return mixed Returns the elapsed time if $display is true, otherwise returns false.
   */
  public function timerStop(bool $display = false)
  {
    $this->timerStop = microtime();

    $time_start = explode(' ', $this->timerStart);
    $time_end = explode(' ', $this->timerStop);

    $this->timer_total = number_format(($time_end[1] + $time_end[0] - ($time_start[1] + $time_start[0])), 3);

    $this->write($_SERVER['REQUEST_URI'], $this->timer_total . 's');

    if ($display == 'true') {
      return $this->timerDisplay();
    } else {
      return false;
    }
  }

  /**
   * Displays the total parse time in a formatted string.
   *
   * @return string Returns a string indicating the parse time in seconds.
   */
  public function timerDisplay()
  {
    return '<span>Parse Time: ' . $this->timer_total . 's</span>';
  }

  /**
   * Logs a message with a specified type if page parse time logging is enabled.
   *
   * @param string $message The message to be logged.
   * @param string $type The type or category of the log message.
   * @return string|false Returns a formatted string with the log entry if logging is enabled and the log file is writable,
   *                      a warning string if the log file is not found or writable, or false if logging is disabled.
   */
  public static function write(string $message, string $type)
  {
    if (STORE_PAGE_PARSE_TIME == 'True') {
      if (is_file(STORE_PAGE_PARSE_TIME_LOG) && is_writable(STORE_PAGE_PARSE_TIME_LOG)) {
        return '<div class="alert alert-warning text-center">' . date(STORE_PARSE_DATE_TIME_FORMAT) . ' [' . $type . '] ' . $message . "\n" . STORE_PAGE_PARSE_TIME_LOG . '</div>';
      } else {
        return '<div class="alert alert-warning text-center">The time log directory or file is not found</div>';
      }
    } else {
      return false;
    }
  }
}

