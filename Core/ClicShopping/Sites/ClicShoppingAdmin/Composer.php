<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\ClicShoppingAdmin;

use ClicShopping\OM\CLICSHOPPING;
use function in_array;
use function is_null;
/**
 * The Composer class provides utility functions for managing Composer dependencies in a CLI/Shop context.
 * It supports checking the Composer environment, managing installed libraries, updating or installing new ones,
 * and clearing the Composer cache. Various utility methods ensure Composer and exec command functionality are available.
 */
class Composer
{
  private static string $root;
  private static string $composerJson;

  /**
   * Initializes the class by setting the root directory, composer.json file path,
   * and defining the composer environment variables.
   *
   * @return void
   */
  public function __construct()
  {
    static::$root = CLICSHOPPING::getConfig('dir_root', 'Shop');
    static::$composerJson = static::$root . 'composer.json';

//define  composer environment
    putenv('COMPOSER_HOME=' . self::$root);
    putenv('COMPOSER_CACHE_DIR=' . CLICSHOPPING::BASE_DIR . '/Work/Cache/Composer/');
    putenv('COMPOSER_HTACCESS_PROTECT=0');
  }

  /**
   * Checks if the 'exec' function is enabled on the server by inspecting the
   * 'disable_functions' directive from the PHP configuration.
   *
   * @return bool Returns true if the 'exec' function is enabled, false otherwise.
   */
  public static function checkExecEnabled(): bool
  {
    $disabled = explode(', ', ini_get('disable_functions'));

    return !in_array('exec', $disabled);
  }

  /**
   * Checks if Composer is installed and accessible in the current environment.
   *
   * This method verifies if the system has Composer installed by first checking
   * if the execution of shell commands is enabled. If execution is allowed,
   * it attempts to run `composer show` in the root directory. The method
   * returns true if the command executes successfully; otherwise, it returns false.
   *
   * @return bool Returns true if Composer is installed and accessible, false otherwise.
   */
  public static function checkComposerInstalled(): bool
  {
    if (!self::checkExecEnabled()) {
      return false;
    }

    $cmd = 'cd ' . escapeshellarg(self::$root) . ' && composer --no-ansi --no-interaction show 2>/dev/null';
    exec($cmd, $output, $return);

    return $return === 0;
  }

  /**
   * Checks if a specific library is installed using Composer.
   *
   * @param string|null $libray The name of the library to check. Defaults to null.
   * @return bool Returns true if the library is installed and command execution is enabled, otherwise false.
   */
public static function checkLibrayInstalled(?string $library = null): bool
{
  if (is_null($library) || !self::checkExecute()) {
    return false;
  }

  $cmd = 'cd ' . escapeshellarg(self::$root) . ' && composer show ' . escapeshellarg($library) . ' 2>/dev/null';
  exec($cmd, $output, $return);

  return $return === 0;
}
  /**
   * Verifies if the necessary conditions for executing commands are met.
   *
   * This method checks whether Composer is installed and the `exec` function is enabled.
   *
   * @return bool Returns true if Composer is installed and the `exec` function is enabled, otherwise false.
   */
  private static function checkExecute(): bool
  {
    return !(self::checkComposerInstalled() === false || self::checkExecEnabled() === false);
  }

  /**
   * Combines the provided output and return parameters into a formatted string.
   *
   * @param mixed $output The output to be included in the debug string.
   * @param string $return The return value to append to the debug string.
   * @return string The formatted debug string.
   */
  final public function debug(mixed $output, string $return): string
  {
    return print_r($output, true) . ' - ' . $return;
  }

  /**
   * Checks the online version of a specified library using Composer.
   *
   * @param string|null $library The name of the library whose version is to be checked. If null, the function will return false.
   * @return string|false The version information of the specified library if successful, or false if an error occurs or the library is not provided.
   */
  public static function checkOnlineVersion(?string $library = null): string|false
  {
    if (!self::checkExecute() || is_null($library)) {
      return false;
    }

    $cmd = 'cd ' . escapeshellarg(self::$root) . ' && composer show ' . escapeshellarg($library) . ' 2>/dev/null';
    exec($cmd, $output, $return);

    if ($return === 0 && !empty($output)) {
      // Recherche la ligne contenant la version
      foreach ($output as $line) {
        if (str_starts_with($line, 'versions')) {
          return trim($line);
        }
      }
      // Fallback : retourne la 4e ligne si présente
      return $output[3] ?? '';
    }

    return false;
  }

  /**
   * Retrieves the list of required libraries from the composer.json file.
   *
   * @return array An associative array of libraries and their version constraints, or an empty array if the file does not exist.
   */
  public static function getLibrary(): array
  {
    $composer_file = self::$composerJson;

    if (!file_exists($composer_file)) {
      return [];
    }

    $composer_json = json_decode(file_get_contents($composer_file), true);

    return $composer_json['require'] ?? [];
  }

  /**
   * Updates all composer dependencies or a specific library if provided.
   *
   * @param string|null $library The name of the specific library to update. If null, updates all dependencies.
   * @return string The output message from the update operation, typically the third line of the composer output.
   */
public static function update(?string $library = null): string
{
  if (!self::checkExecute()) {
    return '';
  }

  $cmd = 'cd ' . escapeshellarg(self::$root) . ' && composer update';
  if ($library !== null) {
    $cmd .= ' ' . escapeshellarg($library);
  }
  $cmd .= ' 2>&1';

  exec($cmd, $output, $return);

  // Retourne toute la sortie pour plus d'informations, ou une chaîne vide si rien
  return !empty($output) ? implode("\n", $output) : '';
}


  /**
   * Installs a specified library using Composer if execution is enabled.
   *
   * @param string|null $library The name of the library to install. If null, installation will not proceed.
   * @return string|bool Returns the output message of the installation process or false if the library parameter is null.
   */
public static function install(?string $library = null): string|bool
{
  if (!self::checkExecute() || is_null($library)) {
    return false;
  }

  $cmd = 'cd ' . escapeshellarg(self::$root) . ' && composer require ' . escapeshellarg($library) . ' 2>&1';
  exec($cmd, $output, $return);

  return !empty($output) ? implode("\n", $output) : '';
}

  /**
   *
   * Removes a specified library using composer if execution of commands is enabled.
   *
   * @param string|null $library The name of the library to remove. Defaults to null.
   * @return string The result message from the remove operation.
   */
  public static function remove(?string $library = null): string
  {
    if (!self::checkExecute() || is_null($library)) {
      return '';
    }

    $cmd = 'cd ' . escapeshellarg(self::$root) . ' && composer remove ' . escapeshellarg($library) . ' 2>&1';
    exec($cmd, $output, $return);

    return !empty($output) ? implode("\n", $output) : '';
  }

  /**
   * Clears the cache by executing the composer clearcache command.
   *
   * @return string The result of the cache clearing operation, typically a message or output from the command execution.
   */
  public static function clearCache(): string
  {
    if (!self::checkExecute()) {
      return '';
    }

    $cmd = 'cd ' . escapeshellarg(self::$root) . ' && composer clearcache 2>&1';
    exec($cmd, $output, $return);

    // Retourne toute la sortie pour plus d'informations, ou une chaîne vide si rien
    return !empty($output) ? implode("\n", $output) : '';
  }
}