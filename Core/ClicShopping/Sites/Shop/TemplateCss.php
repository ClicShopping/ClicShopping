<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Sites\Shop;

  /**
   * Class TemplateCss
   *
   * Discovers, merges, sanitizes and compresses the front-office CSS.
   *
   * The CSS is resolved as a two-layer override system, mirroring the file/module
   * resolution of the template engine: a Default base layer and an active-theme
   * override layer. A theme only needs to ship the CSS files it actually
   * overrides; every missing file falls back to Default automatically.
   *
   * @package ClicShopping\Sites\Shop
   */
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

    // Default maximum size of a single CSS file (2 MB) when MAX_FILE_SIZE is not defined.
    private const DEFAULT_MAX_FILE_SIZE = 2097152;

    // Default cumulative size of all merged CSS files (10 MB) when MAX_TOTAL_SIZE is not defined.
    private const DEFAULT_MAX_TOTAL_SIZE = 10485760;

    // Default browser cache duration in seconds (24 hours) when CACHE_DURATION is not defined.
    private const DEFAULT_CACHE_DURATION = 86400;

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
     * Resolves the maximum allowed size of a single CSS file.
     *
     * @return int Size limit in bytes
     */
    private function maxFileSize(): int
    {
      return \defined('MAX_FILE_SIZE') ? (int)MAX_FILE_SIZE : self::DEFAULT_MAX_FILE_SIZE;
    }

    /**
     * Resolves the maximum cumulative size of all merged CSS files.
     *
     * @return int Size limit in bytes
     */
    private function maxTotalSize(): int
    {
      return \defined('MAX_TOTAL_SIZE') ? (int)MAX_TOTAL_SIZE : self::DEFAULT_MAX_TOTAL_SIZE;
    }

    /**
     * Resolves the browser cache duration applied to the compressed CSS response.
     *
     * @return int Duration in seconds
     */
    private function cacheDuration(): int
    {
      return \defined('CACHE_DURATION') ? (int)CACHE_DURATION : self::DEFAULT_CACHE_DURATION;
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
      $content = preg_replace('/(:|\s)0(px|em|rem|%|vh|vw)/', '${1}0', $content);
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
     * Recursively collects every valid CSS file under $root_dir.
     *
     * Returns a map of relativePath => absolutePath (priority files included), so
     * the caller can merge several layers by relative path. Path traversal,
     * file size, extension and partial-file (`_*`) rules are enforced per layer.
     *
     * @param string      $root_dir  Directory to scan (absolute path).
     * @param string|null $base_root Root used to compute relative paths (set by recursion).
     * @param array       $all_data  Accumulator (used by internal recursion).
     * @param int         $depth     Current depth (infinite loop protection).
     * @return array<string,string> Map of relativePath => absolutePath.
     */
    private function collectCssFiles(string $root_dir, ?string $base_root = null, array $all_data = [], int $depth = 0): array
    {
      $root_dir = realpath($root_dir);

      if ($root_dir === false || !is_dir($root_dir) || !is_readable($root_dir)) {
        return $all_data;
      }

      // The top-level call defines the base used for relative-path computation.
      if ($base_root === null) {
        $base_root = $root_dir;
      }

      $allowed_extensions = ['css'];
      $ignore_dirs = ['.', '..', '.git', '.svn', 'node_modules', 'vendor'];

      $dir_content = @scandir($root_dir, SCANDIR_SORT_ASCENDING);

      if ($dir_content === false) {
        $this->logSecurityError("Failed to scan directory", $root_dir);
        return $all_data;
      }

      foreach ($dir_content as $entry) {
        if ($entry === '' || $entry === '.' || $entry === '..') {
          continue;
        }

        $path = $root_dir . DIRECTORY_SEPARATOR . $entry;
        $real_path = realpath($path);

        if ($real_path === false) {
          $this->logSecurityError("Invalid path detected", $path);
          continue;
        }

        // Path traversal protection: the resolved path must stay within the layer root.
        if (!str_starts_with($real_path . DIRECTORY_SEPARATOR, $base_root . DIRECTORY_SEPARATOR)) {
          $this->logSecurityError("Path traversal attempt detected", $real_path);
          continue;
        }

        if (is_file($real_path) && is_readable($real_path)) {
          // Skip partial files (e.g. _variables.css).
          if (str_starts_with($entry, '_')) {
            continue;
          }

          if (!in_array(strtolower(pathinfo($entry, PATHINFO_EXTENSION)), $allowed_extensions, true)) {
            continue;
          }

          $file_size = filesize($real_path);

          if ($file_size === false || $file_size > $this->maxFileSize()) {
            $this->logSecurityError("File too large or unreadable", $real_path);
            continue;
          }

          $relative = str_replace($base_root . DIRECTORY_SEPARATOR, '', $real_path);
          $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);

          $all_data[$relative] = $real_path;
        } elseif (is_dir($real_path) && is_readable($real_path)) {
          if (in_array($entry, $ignore_dirs, true)) {
            continue;
          }

          // Recursion depth limit to prevent directory exhaustion attacks.
          if ($depth < 10) {
            $all_data = $this->collectCssFiles($real_path, $base_root, $all_data, $depth + 1);
          } else {
            $this->logSecurityError("Maximum directory depth reached", $real_path);
          }
        }
      }

      return $all_data;
    }

    /**
     * Recursively scans $root_dir and returns all valid CSS files,
     * excluding priority files which are handled separately.
     *
     * Kept for backward compatibility; built on top of collectCssFiles().
     *
     * @param string $root_dir   Root directory to scan (absolute path).
     * @param array  $all_data   Accumulator (absolute paths).
     * @param int    $depth      Unused — kept for signature compatibility.
     * @return array             Absolute paths of discovered non-priority CSS files.
     */
    public function getFilesSecure(string $root_dir, array $all_data = [], int $depth = 0): array
    {
      $priority = $this->getPriorityCssFiles();

      foreach ($this->collectCssFiles($root_dir) as $relative => $absolute) {
        if (!in_array($relative, $priority, true)) {
          $all_data[] = $absolute;
        }
      }

      return $all_data;
    }

    /**
     * Builds the ordered, merged CSS list for the active theme.
     *
     * Two layers are merged by relative path: the Default base layer and the
     * active-theme override layer. A file present in the theme overrides the
     * Default file of the same relative path; files unique to either layer are
     * kept. Priority files are emitted first (in their defined order), followed
     * by the remaining files in natural, case-insensitive order.
     *
     * @param string      $defaultRoot Absolute path to the Default css/{lang} directory.
     * @param string|null $themeRoot   Absolute path to the theme css/{lang} directory, or null when no override applies.
     * @return array<string,string>    Ordered map of relativePath => absolutePath.
     */
    public function buildMergedCssMap(string $defaultRoot, ?string $themeRoot): array
    {
      // Lowest priority layer: Default base.
      $map = $this->collectCssFiles($defaultRoot);

      // Highest priority layer: active-theme overrides (win on identical relative path).
      if ($themeRoot !== null && $themeRoot !== '' && is_dir($themeRoot) && realpath($themeRoot) !== realpath($defaultRoot)) {
        foreach ($this->collectCssFiles($themeRoot) as $relative => $absolute) {
          $map[$relative] = $absolute;
        }
      }

      // Emit priority files first (defined order), then the remaining files sorted naturally.
      $ordered = [];

      foreach ($this->getPriorityCssFiles() as $relative) {
        if (isset($map[$relative])) {
          $ordered[$relative] = $map[$relative];
          unset($map[$relative]);
        }
      }

      ksort($map, SORT_NATURAL | SORT_FLAG_CASE);

      return $ordered + $map;
    }

    /**
     * Builds, sanitizes, compresses and outputs the merged CSS response.
     *
     * Handles ETag/Last-Modified revalidation (304), GZIP and cache headers.
     * The two roots are already validated layers; this method only reads the
     * resolved files and streams the compressed buffer.
     *
     * @param string      $defaultRoot Absolute path to the Default css/{lang} directory.
     * @param string|null $themeRoot   Absolute path to the theme css/{lang} directory, or null.
     * @return void
     */
    public function render(string $defaultRoot, ?string $themeRoot): void
    {
      try {
        $map = $this->buildMergedCssMap($defaultRoot, $themeRoot);

        $buffer = '';
        $total_size = 0;

        foreach ($map as $relative => $absolute) {
          $real_path = realpath($absolute);

          if ($real_path === false || !is_readable($real_path)) {
            $this->logSecurityError("CSS file not accessible", $relative);
            continue;
          }

          $file_size = filesize($real_path);

          if ($file_size === false || $file_size > $this->maxFileSize()) {
            $this->logSecurityError("CSS file too large", $relative);
            continue;
          }

          $total_size += $file_size;

          if ($total_size > $this->maxTotalSize()) {
            $this->logSecurityError("Total CSS size limit exceeded");
            break;
          }

          $content = file_get_contents($real_path);

          if ($content === false) {
            $this->logSecurityError("Failed to read CSS file", $real_path);
            continue;
          }

          $buffer .= $this->sanitizeCssContent($content) . "\n";
        }

        $this->emit($this->compressCss($buffer));
      } catch (\Throwable $e) {
        $this->logSecurityError("Critical error: " . $e->getMessage());

        http_response_code(500);
        header('Content-Type: text/css; charset=utf-8');
        echo '/* CSS compression error - check server logs */';
      }
    }

    /**
     * Sends the compressed CSS buffer with the appropriate cache headers,
     * honouring client-side revalidation (304 Not Modified).
     *
     * @param string $buffer The compressed CSS content.
     * @return void
     */
    private function emit(string $buffer): void
    {
      $cacheDuration = $this->cacheDuration();

      $content_hash = hash('sha256', $buffer);
      $etag = '"' . substr($content_hash, 0, 16) . '"';

      $last_modified = gmdate('D, d M Y H:i:s') . ' GMT';
      $expires = gmdate('D, d M Y H:i:s', time() + $cacheDuration) . ' GMT';

      $if_modified_since = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
      $if_none_match = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';

      // Client-side caching: return 304 when the content has not changed.
      if (($if_none_match !== '' && $if_none_match === $etag)
        || ($if_modified_since !== '' && strtotime($if_modified_since) >= strtotime($last_modified))) {
        http_response_code(304);
        header('Cache-Control: public, max-age=' . $cacheDuration);
        header('ETag: ' . $etag);
        return;
      }

      // Enable GZIP compression if supported by the server and the browser.
      if (extension_loaded('zlib') && !ini_get('zlib.output_compression') && !ob_get_level()) {
        ob_start('ob_gzhandler');
      }

      header('Content-Type: text/css; charset=utf-8');
      header('Cache-Control: public, max-age=' . $cacheDuration);
      header('Last-Modified: ' . $last_modified);
      header('Expires: ' . $expires);
      header('ETag: ' . $etag);
      header('X-Content-Type-Options: nosniff');
      header('X-Frame-Options: DENY');

      echo $buffer;
    }
  }
