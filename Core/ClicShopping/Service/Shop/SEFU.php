<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Service\Shop;
/**
 * Service class SEFU for handling Shop URL rewriting and language parameter extraction.
 */
class SEFU implements \ClicShopping\OM\Interfaces\ServiceInterface
{
  /**
   * Retrieves the path information from the server's global variables.
   *
   * @return string The path information obtained from the server, or an empty string if not available.
   */
  private static function getPathInfo(): string
  {
    $path_info = $_SERVER['PATH_INFO'] ?? ($_SERVER['ORIG_PATH_INFO'] ?? '');

    return $path_info;
  }

  /**
   * Processes the path information to populate the global $_GET array.
   *
   * This method retrieves the path information, extracts parameters from
   * it, and populates the global $_GET array with key-value pairs derived
   * from the processed parameters. Additionally, it handles array-style
   * parameters within the path by populating them into $_GET as arrays.
   *
   * @return bool Always returns true upon completion.
   */
  public static function start(): bool
  {
    $path_info = static::getPathInfo();

    if (isset($path_info) && (\strlen($path_info) > 1)) {
      $parameters = explode('/', substr($path_info, 1));

      $_GET = [];
      $GET_array = [];

      foreach ($parameters as $parameter) {
        $param_array = explode('-', $parameter, 2);

        if (!isset($param_array[1])) {
          $param_array[1] = '';
        }

        $raw_key = $param_array[0];
        $key = preg_replace('/[^a-zA-Z0-9_\[\]]/', '', $raw_key);
        $value = htmlspecialchars($param_array[1], ENT_QUOTES, 'UTF-8');

        if (str_ends_with($key, '[]')) {
          $clean_key = substr($key, 0, -2);
          $GET_array[$clean_key][] = $value;
        } else {
          $_GET[$key] = $value;
        }
      }

      if (\count($GET_array) > 0) {
        foreach ($GET_array as $key => $value) {
          $_GET[$key] = $value;
        }
      }
    }

    return true;
  }

  /**
   * Stops the execution or process and ensures a successful termination.
   *
   * @return bool Returns true to indicate the process was stopped successfully.
   */
  public static function stop(): bool
  {
    return true;
  }

  /**
   * Retrieves the value of the 'language' parameter from the URL path information.
   *
   * @return string|null Returns the value of the 'language' parameter if found, otherwise null.
   */
  public static function getUrlValue()
  {
    $path_info = static::getPathInfo();
    $value_language = null;

    if (isset($path_info) && (\strlen($path_info) > 1)) {
      $parameters = explode('/', substr($path_info, 1));

      foreach ($parameters as $parameter) {
        $param_array = explode('-', $parameter, 2);

        if (!isset($param_array[1])) {
          $param_array[1] = '';
        }

        if ($param_array[0] == 'language') {
          $value_language = $param_array[1];
        } else {
          $value_language = null;
        }
      }

      return $value_language;
    }
  }
}