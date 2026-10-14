<?php
  /**
   * Copyright (c) 2008–2026 Loic Richard
   *
   * Licensed under AGPLv3 or commercial license.
   * See LICENSE file.
   */

  namespace ClicShopping\Security;

  /**
   * Class Security
   *
   * A class designed to handle cleansing of input data with options for managing exclusions,
   * recursive sanitization, and global variable handling. The class is configurable in terms of
   * enabling key cleansing and managing specific file exclusions.
   *
   * Members include:
   * - $_excluded_from_cleansing: Array of files excluded from cleansing.
   * - $_enabled: Boolean to enable or disable the security logic.
   * - $_basename: Base name of the current data being cleansed.
   * - $_cleanse_keys: Boolean indicating whether to cleanse keys during operations.
   *
   * This class implements methods to manage exclusions, cleanse input data recursively, and sanitize strings.
   */
  class RequestSanitizer
  {

    public array $_excluded_from_cleansing = [];
    public bool $_enabled = true; // Turn on or off - bool true / false
    public $_basename;
    public $_cleanse_keys; // Turn on or off - bool true / false

    /**
     * Constructor method for initializing the object with optional cleansing keys logic
     * and setting default exclusions.
     *
     * @param bool $cleanse_keys Determines whether keys should be cleansed. Default is false.
     * @return void
     */
    public function __construct(bool $cleanse_keys = false)
    {
      if ($cleanse_keys) $this->_cleanse_keys = true;
      $this->addExclusions(array(defined('FILENAME_PROTX_PROCESS') ? FILENAME_PROTX_PROCESS : 'protx_process.php'));
    } // end constructor

    /**
     * Adds a file to the list of exclusions to prevent it from undergoing a cleansing process.
     *
     * @param string $file_to_exclude The file to be excluded from the cleansing process.
     * @return self Returns the current instance to allow method chaining.
     */
    public function addExclusion($file_to_exclude = '')
    {
      if (!in_array($file_to_exclude, $this->_excluded_from_cleansing, true)) {
        $this->_excluded_from_cleansing[] = (string)$file_to_exclude;
      }

      return $this;
    } // end method

    /**
     * Adds multiple exclusions to the internal exclusion list.
     *
     * @param array $args An array of exclusion files to be added. If the array is empty, no action is performed.
     * @return void
     */
    public function addExclusions(array $args = array()): void
    {
      if (empty ($args)) return;
      foreach ($args as $index => $exclusion_file) {
        $this->addExclusion($exclusion_file);
      }
    } // end method

    /**
     * Cleanses input data by applying various sanitization steps to $_GET and updates $_REQUEST accordingly.
     * Ensures excluded data is not processed and additional global variable cleaning is performed when necessary.
     *
     * @param string $data Optional data string to be cleansed.
     * @return void No value is returned.
     */

    public function cleanse(string $data = '')
    {
      if (false === $this->_enabled) {
        return;
      }
      if (empty($data)) {
        return;
      }
      $this->_basename = $data;
      if (in_array($this->_basename, $this->_excluded_from_cleansing, true)) {
        return;
      }
      $this->cleanseGetRecursive($_GET);
      $_REQUEST = $_GET + $_POST; // $_REQUEST now holds the cleansed $_GET and unchanged $_POST. $_COOKIE has been removed.
      if (!function_exists('ini_get')) {
        $this->cleanGlobals();
      }
    } // end method

    /**
     * Recursively cleanses the data in a given GET array by sanitizing keys and values.
     *
     * @param array &$get The GET array to be cleaned recursively. Keys might be removed if they do not conform to expectations,
     *                    and values are cleansed depending on whether they are arrays or strings.
     * @return void
     */
    public function cleanseGetRecursive(&$get): void
    {
      foreach ($get as $key => &$value) {
        // If cleanse keys is set to on we unset array keys if they don't conform to expectations
        if ($this->_cleanse_keys && ($this->cleanseKeyString($key) != $key)) {
          unset ($get[$key]);
          continue;
        }
        if (is_array($value)) {
          // We have an array so well run it through again
          $this->cleanseGetRecursive($value);
          // We have a string value so we'll cleanse it
        } else $value = $this->cleanseValueString($value);
      }
    } // end method

    /**
     * Cleanses a given string by removing disallowed characters and patterns and ensuring safe formatting.
     *
     * @param string $string The input string to be sanitized.
     * @return string The cleansed and formatted string.
     */
    public function cleanseKeyString(string $string): string
    {
      $banned_string_pattern = '@GLOBALS|_REQUEST|base64_encode|UNION|%3C|%3E@i';
      // Apply the whitelist
      $cleansed = preg_replace("/[^\s{}a-z0-9_\.\-]/i", "", urldecode($string));
      // Remove banned words
      $cleansed = preg_replace($banned_string_pattern, '', $cleansed);
      // Ensure that a clever hacker hasn't gained himself a naughty double hyphen -- after our cleansing
      return preg_replace('@[-]+@', '-', $cleansed);
    } // end method


    /**
     * Cleanses an input string by removing disallowed characters, removing banned patterns,
     * and normalizing sequences such as repeated hyphens.
     *
     * @param string $string The input string to be cleansed.
     * @return string The cleansed string with disallowed characters and banned patterns removed.
     */
    public function cleanseValueString(string $string): string
    {
      $banned_string_pattern = '@GLOBALS|_REQUEST|base64_encode|UNION|%3C|%3E@i';
// Apply the whitelist
      $lang_additions = '@ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ'; // Special language characters go here - see the example above

// decode utf8 ==> search engine problem
      $cleansed = preg_replace("/[^\s{}a-z0-9_\.\-@$lang_additions]/i", "", urldecode(mb_convert_encoding($string, 'ISO-8859-1', 'UTF-8')));

// Remove banned words
      $cleansed = preg_replace($banned_string_pattern, '', $cleansed);
// Ensure that a clever hacker hasn't gained himself a naughty double hyphen -- after our cleansing
      $cleansed = mb_convert_encoding($cleansed, 'UTF-8', 'ISO-8859-1');

      return preg_replace('@[-]+@', '-', $cleansed);
    } // end method


    /**
     * Cleans up global variables by synchronizing values from the $_GET superglobal.
     *
     * Iterates through the $_GET array and, for each key-value pair, checks if the key
     * exists in the $GLOBALS array. If the key exists, the value from $_GET is assigned
     * to the corresponding key in $GLOBALS. This ensures that global variables are
     * updated with the corresponding values from the $_GET superglobal array.
     *
     * @return void
     */
    public function cleanGlobals(): void
    {
      foreach ($_GET as $key => $value) {
        if (array_key_exists($key, $GLOBALS)) {
          $GLOBALS[$key] = $value;
        }
      }
    } // end method
  } // end class
