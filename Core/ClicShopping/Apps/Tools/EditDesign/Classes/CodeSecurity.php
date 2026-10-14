<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\EditDesign\Classes;

/**
 * Class CodeSecuritySanitizer
 *
 * Provides static methods to detect and prevent dangerous PHP and CSS code patterns
 * in user-submitted content, especially for online editors.
 *
 * This class maintains lists of forbidden PHP and CSS patterns commonly associated
 * with code injection, remote code execution, or malicious behavior.
 *
 * Usage:
 * - Use isPhpSafe() to verify if PHP code contains forbidden constructs.
 * - Use isCssSafe() to verify if CSS code contains unsafe or exploit-prone expressions.
 *
 * Both methods return a boolean indicating whether the input code is free from dangerous patterns.
 */
class CodeSecurity
{
  /**
   * Check if a PHP code string contains any forbidden dangerous patterns.
   *
   * @param string $code The PHP code to check.
   * @return bool Returns true if no dangerous patterns are found, false otherwise.
   */
  public static function isPhpSafe(string $code): bool
  {
    $dangerous = [
      'eval',
      'system',
      'shell_exec',
      'exec',
      'passthru',
      'proc_open',
      'popen',
      'assert',
      'create_function',
      'base64_decode',
      'gzinflate',
      'bstr_rot13',
      'phpinfo'
    ];

    $tokens = token_get_all($code);

    foreach ($tokens as $token) {
      if (is_array($token) && $token[0] === T_STRING) {
        if (in_array(strtolower($token[1]), $dangerous, true)) {
          return false;
        }
      }
    }

    return true;
  }


  /**
   * Check if a CSS code string contains any forbidden dangerous patterns.
   *
   * @param string $css The CSS code to check.
   * @return bool Returns true if no dangerous patterns are found, false otherwise.
   */
  public static function isCssSafe(string $css): bool
  {
    $dangerous = [
      '/expression\s*\(/i',                  // Obsolete IE-only JS injection
      '/url\s*\(\s*["\']?\s*javascript:/i',  // JS URL injection
      '/@import\s+/i',                       // Remote import
      '/behavior\s*:/i',                     // IE behavior
      '/-moz-binding\s*:/i',                 // Mozilla XBL binding
    ];

    foreach ($dangerous as $pattern) {
      if (preg_match($pattern, $css)) {
        return false;
      }
    }

    return true;
  }

}

