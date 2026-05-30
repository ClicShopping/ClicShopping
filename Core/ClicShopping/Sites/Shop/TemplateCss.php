<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Sites\Shop;

  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\Sites\Shop\Template;

  class TemplateCss
  {
    // Priority CSS files loaded first (in this specific order)
    private const PRIORITY_CSS_FILES = [
      'general/stylesheet.css',
      'general/stylesheet_responsive.css',
      'general/link_general.css',
      'general/link_general_responsive.css',
      'modules_boxes/modules_boxes_general.css',
      'modules_checkout_payment/modules_checkout_payment_general.css',
      'modules_checkout_shipping/modules_checkout_shipping_general.css',
      'modules_footer/modules_footer_general.css',
      'modules_front_page/modules_front_page_general.css',
      'modules_header/modules_header_general.css',
      'modules_index_categories/modules_index_categories_general.css',
      'modules_login/modules_login_general.css',
      'modules_products_info/modules_products_info_general.css',
      'modules_products_listing/modules_products_listing_general.css',
      'modules_products_new/modules_products_new_general.css',
      'modules_products_specials/modules_products_specials_general.css',
      'modules_shopping_cart/modules_shopping_cart_general.css',
      'modules_products_search/modules_products_search_general.css',
      'general/bootstrap_customize.css',
      'general/grid_list.css',
    ];

    // Dynamic storage for additional priority CSS files added at runtime
    private array $extraPriorityCssFiles = [];

    public function __construct()
    {
    }

    /**
     * Adds additional priority CSS files to the existing list.
     *
     * @param array $files List of relative file paths
     * @return void
     */
    public function addPriorityCssFiles(array $files): void
    {
      $this->extraPriorityCssFiles = array_merge($this->extraPriorityCssFiles, $files);
    }

    /**
     * Returns the complete list of priority files (base constant + dynamically added extras).
     *
     * @return array Unique list of CSS file paths
     */
    public function getPriorityCssFiles(): array
    {
      return array_unique(array_merge(self::PRIORITY_CSS_FILES, $this->extraPriorityCssFiles));
    }

    /**
     * Logs security-related errors to the server's error log.
     *
     * @param string $message The error description
     * @param string|null $file The file path related to the error
     * @return void
     */
    public function logSecurityError(string $message, ?string $file = null): void
    {
      $log_message = "[WARNING CSS] " . date('Y-m-d H:i:s') . "] CSS Compressor Security: " . $message;

      if ($file) {
        $log_message .= " - File: " . $file;
      }

      error_log($log_message);
    }

    /**
     * Sanitizes CSS content by removing potentially dangerous expressions or XSS vectors.
     *
     * @param string $content Raw CSS content
     * @return string Sanitized CSS content
     */
    public function sanitizeCssContent(string $content): string
    {
      // Remove IE expressions and script injections
      $content = preg_replace('/expression\s*\(/i', '', $content);
      $content = preg_replace('/javascript\s*:/i', '', $content);
      $content = preg_replace('/vbscript\s*:/i', '', $content);
      $content = preg_replace('/data\s*:\s*text\/html/i', '', $content);

      // Block remote CSS imports via HTTP/HTTPS for security
      $content = preg_replace('/@import\s+url\s*\(\s*["\']?https?:\/\/[^"\']*["\']?\s*\)/i', '', $content);

      return $content;
    }

    /**
     * Removes comments, whitespace, and unnecessary characters from CSS content to reduce size.
     *
     * @param string $content The raw CSS content to compress.
     * @return string The compressed CSS content.
     */
    public function compressCss(string $content): string
    {
      // 1. Preserve license comments starting with /*! ... */
      preg_match_all('/\/\*![\s\S]*?\*\//', $content, $licenses);
      $content = preg_replace('/\/\*![\s\S]*?\*\//', '##LICENSE##', $content);

      // 2. Remove standard CSS comments
      $content = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $content);

      // 3. Remove line breaks and tabs
      $content = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $content);

      // 4. Reduce multiple spaces to a single space
      $content = preg_replace('/\s+/', ' ', $content);

      // 5. Remove spaces around structural characters
      $content = preg_replace('/\s*([{}:;,>~+])\s*/', '$1', $content);

      // 6. Handle specific cases: spaces in selector combinators
      $content = preg_replace('/\s*>\s*/', '>', $content);  // e.g., div > p
      $content = preg_replace('/\s*~\s*/', '~', $content);  // e.g., p ~ span
      $content = preg_replace('/\s*\+\s*/', '+', $content); // e.g., p + span

      // 7. Remove unnecessary zeros in units
      $content = preg_replace('/(:|\s)0(px|em|rem|%|vh|vw)/', '$10', $content);
      $content = preg_replace('/(:|\s)0\.(\d)/', '$1.$2', $content); // e.g., 0.5 → .5

      // 8. Remove semicolon before trailing brace
      $content = str_replace(';}', '}', $content);

      // 9. Re-inject preserved license comments
      foreach ($licenses[0] as $license) {
        $content = preg_replace('/##LICENSE##/', $license, $content, 1);
      }

      return trim($content);
    }

    /**
     * Recursively scans $root_dir and returns all valid CSS files,
     * excluding priority files which are handled separately.
     *
     * @param string $root_dir   Root directory to scan (absolute path).
     * @param array  $all_data   Accumulator (used by internal recursion).
     * @param int    $depth      Current depth (infinite loop protection).
     * @return array             Absolute paths of discovered CSS files.
     */
    public function getFilesSecure(string $root_dir, array $all_data = [], int $depth = 0): array
    {
      // Resolve the real path once at this level
      $root_dir = realpath($root_dir);

      if (!$root_dir || !is_dir($root_dir) || !is_readable($root_dir)) {
        $this->logSecurityError("Invalid or unreadable root directory", $root_dir ?: 'unknown');
        return $all_data;
      }

      // Allowed file extensions
      $allowed_extensions = ['css'];

      // Directories to ignore during scanning
      $ignore_dirs = ['.', '..', '.git', '.svn', 'node_modules', 'vendor'];

      // Regex pattern to ignore files starting with an underscore (e.g., partials)
      $ignore_regex = '/^_/';

      $dir_content = @scandir($root_dir, SCANDIR_SORT_ASCENDING);
      if (!$dir_content) {
        $this->logSecurityError("Failed to scan directory", $root_dir);
        return $all_data;
      }

      foreach ($dir_content as $entry) {
        if (empty($entry) || $entry === '.' || $entry === '..') {
          continue;
        }

        $path = $root_dir . DIRECTORY_SEPARATOR . $entry;

        // Resolve and verify path for path traversal protection
        $real_path = realpath($path);
        if (!$real_path) {
          $this->logSecurityError("Invalid path detected", $path);
          continue;
        }

        // Ensure the resolved path is still within the root directory
        if (strpos($real_path, $root_dir) !== 0) {
          $this->logSecurityError("Path traversal attempt detected", $real_path);
          continue;
        }

        if (is_file($real_path) && is_readable($real_path)) {

          // Validate file size against the global constant
          $file_size = filesize($real_path);
          if ($file_size === false || $file_size > MAX_FILE_SIZE) {
            $this->logSecurityError("File too large or unreadable", $real_path);
            continue;
          }

          // Calculate relative path to compare against priority files list
          $relative_path = str_replace($root_dir . DIRECTORY_SEPARATOR, '', $real_path);
          $relative_path = str_replace(DIRECTORY_SEPARATOR, '/', $relative_path);

          // Exclude priority files as they are loaded separately in the main script
          if (in_array($relative_path, $this->getPriorityCssFiles(), true)) {
            continue;
          }

          // Exclude files starting with _
          if (preg_match($ignore_regex, basename($entry))) {
            continue;
          }

          // Validate extension
          $file_extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
          if (!in_array($file_extension, $allowed_extensions, true)) {
            continue;
          }

          // Basic read check to validate accessibility
          $sample = file_get_contents($real_path, false, null, 0, 100);
          if ($sample === false) {
            $this->logSecurityError("Cannot read file sample", $real_path);
            continue;
          }

          $all_data[] = $real_path;

        } elseif (is_dir($real_path) && is_readable($real_path)) {

          if (in_array($entry, $ignore_dirs, true)) {
            continue;
          }

          // Recursion depth limit to prevent directory exhaustion attacks
          if ($depth < 10) {
            $all_data = $this->getFilesSecure($real_path, $all_data, $depth + 1);
          } else {
            $this->logSecurityError("Maximum directory depth reached", $real_path);
          }
        }
      }

      return $all_data;
    }
  }