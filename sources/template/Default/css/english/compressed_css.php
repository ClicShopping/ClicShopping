<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\Registry;
  use ClicShopping\Sites\Shop\TemplateCss;

  // Start the timer for the page parse time log
  define('PAGE_PARSE_START_TIME', microtime());
  // Define the base directory for the core files
  define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../../../Core/ClicShopping') . DIRECTORY_SEPARATOR);

  // Load the main framework class and register the autoloader
  require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
  spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

  // Initialize the framework and load the Shop site context
  CLICSHOPPING::initialize();
  CLICSHOPPING::loadSite('Shop');

  // Register the CSS Template management class in the Registry
  Registry::set('TemplateCss', new TemplateCss());
  $CLICSHOPPING_templateCss = Registry::get('TemplateCss');

  // Security Configuration
  if (!defined('MAX_FILE_SIZE'))  define('MAX_FILE_SIZE',  2097152);  // 2 MB per CSS file
  if (!defined('MAX_TOTAL_SIZE')) define('MAX_TOTAL_SIZE', 10485760); // 10 MB total for all combined files
  if (!defined('CACHE_DURATION')) define('CACHE_DURATION', 86400);    // Cache duration (24 hours)

  /**
   * Extension Point: Add priority CSS files here if necessary.
   * These will be loaded after the base list.
   * Example:
   * $CLICSHOPPING_templateCss->addPriorityCssFiles(['plugins/slider/slider.css']);
   */

  try {
    // Determine the root directory (usually .../sources/templates/<THEME>/css/<lang>/)
    $root_dir = realpath(__DIR__);

    if (!$root_dir) {
      throw new \Exception("Cannot determine root directory");
    }

    // Discover additional CSS files while automatically excluding priority files to avoid duplicates
    $discovered = $CLICSHOPPING_templateCss->getFilesSecure($root_dir);

    $cssFilesAddon = [];
    foreach ($discovered as $absPath) {
      // Convert absolute paths to relative paths for the processing loop
      $relative = str_replace($root_dir . DIRECTORY_SEPARATOR, '', $absPath);
      $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
      $cssFilesAddon[] = $relative;
    }

    // Retrieve the priority list from the class (base files + any added extras)
    $cssFiles = $CLICSHOPPING_templateCss->getPriorityCssFiles();

    // Merge priority files with discovered files and remove duplicates
    $cssFiles = array_unique(array_merge($cssFiles, $cssFilesAddon));

    $validated_files = [];
    $total_size = 0;

    // Security and Integrity Validation Loop
    foreach ($cssFiles as $cssFile) {
      $full_path = $root_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cssFile);
      $real_path = realpath($full_path);

      // Check if file exists and is readable
      if (!$real_path || !file_exists($real_path) || !is_readable($real_path)) {
        $CLICSHOPPING_templateCss->logSecurityError("CSS file not accessible", $cssFile);
        continue;
      }

      // Directory Traversal protection: ensure the file is within the allowed root directory
      if (strpos($real_path, $root_dir . DIRECTORY_SEPARATOR) !== 0) {
        $CLICSHOPPING_templateCss->logSecurityError("CSS file outside allowed directory", $cssFile);
        continue;
      }

      // Check individual file size
      $file_size = filesize($real_path);
      if ($file_size === false || $file_size > MAX_FILE_SIZE) {
        $CLICSHOPPING_templateCss->logSecurityError("CSS file too large", $cssFile);
        continue;
      }

      // Check cumulative size limit
      $total_size += $file_size;
      if ($total_size > MAX_TOTAL_SIZE) {
        $CLICSHOPPING_templateCss->logSecurityError("Total CSS size limit exceeded");
        break;
      }

      $validated_files[] = $real_path;
    }

    // Concatenate and sanitize the content of all validated files
    $buffer = '';
    foreach ($validated_files as $css_file) {
      $content = file_get_contents($css_file);
      if ($content === false) {
        $CLICSHOPPING_templateCss->logSecurityError("Failed to read CSS file", $css_file);
        continue;
      }

      $buffer .= $CLICSHOPPING_templateCss->sanitizeCssContent($content) . "\n";
    }

    // Apply CSS compression (removing whitespace, comments, etc.)
    $buffer = $CLICSHOPPING_templateCss->compressCss($buffer);

    // Generate ETag and Cache headers
    $content_hash = hash('sha256', $buffer);
    $etag = '"' . substr($content_hash, 0, 16) . '"';

    $timestamp = time() + CACHE_DURATION;
    $last_modified = gmdate('D, d M Y H:i:s', time()) . ' GMT';
    $expires = gmdate('D, d M Y H:i:s', $timestamp) . ' GMT';

    // Handle Client-Side Caching (304 Not Modified)
    $if_modified_since = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
    $if_none_match = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';

    if (($if_none_match && $if_none_match === $etag) ||
      ($if_modified_since && strtotime($if_modified_since) >= strtotime($last_modified))) {
      http_response_code(304);
      header('Cache-Control: public, max-age=' . CACHE_DURATION);
      header('ETag: ' . $etag);
      exit();
    }

    // Enable GZIP compression if supported by the server and browser
    if (extension_loaded('zlib') && !ini_get('zlib.output_compression') && !ob_get_level()) {
      ob_start('ob_gzhandler');
    }

    // Set HTTP response headers
    header('Content-Type: text/css; charset=utf-8');
    header('Cache-Control: public, max-age=' . CACHE_DURATION);
    header('Last-Modified: ' . $last_modified);
    header('Expires: ' . $expires);
    header('ETag: ' . $etag);
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');

    // Output the final CSS buffer
    echo $buffer;


// Finalize the output
    /*
    if (ob_get_level() > 0) {
      $handlers = ob_list_handlers();
      $current = end($handlers);

      // ob_gzhandler se ferme seul en fin de script, forcer ob_end_flush() provoque l'erreur
      if ($current !== 'ob_gzhandler') {
        ob_end_flush();
      }
    }
    */

  } catch (Exception $e) {
    // Log the error and return a 500 server error
    $CLICSHOPPING_templateCss->logSecurityError("Critical error: " . $e->getMessage());

    http_response_code(500);
    header('Content-Type: text/css; charset=utf-8');
    echo '/* CSS compression error - check server logs */';
  }