<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  /**
   * Shared, language-agnostic CSS loader.
   *
   * A single entry point serves the CSS for any theme and any language by
   * merging two layers, both resolved dynamically from the request:
   *   1. Default/css/{lang}      → base layer (universal fallback)
   *   2. {ActiveTheme}/css/{lang} → override layer (only the files it ships)
   *
   * The active theme and language are provided by Template::getTemplateCSS()
   * through the "theme" and "lang" query parameters and are strictly validated
   * below. A custom theme therefore only needs to ship the CSS files it
   * overrides — no loader copy, no per-language loader, no full css/ tree.
   */

  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\HTML;
  use ClicShopping\OM\Registry;
  use ClicShopping\Sites\Shop\TemplateCss;

  // Start the timer for the page parse time log
  define('PAGE_PARSE_START_TIME', microtime());
  // Define the base directory for the core files
  define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../../Core/ClicShopping') . DIRECTORY_SEPARATOR);

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

  // This loader lives in .../sources/template/Default/css
  $defaultCssDir = realpath(__DIR__);
  // Root of all themes: .../sources/template
  $templatesDir = realpath(__DIR__ . '/../../');

  if ($defaultCssDir === false || $templatesDir === false) {
    http_response_code(500);
    header('Content-Type: text/css; charset=utf-8');
    echo '/* CSS loader error: base directory not found */';
    return;
  }

  // Resolve and validate the language directory (simple lowercase name, e.g. english, french).
  $lang = (isset($_GET['lang']) && preg_match('/^[a-z]+$/', (string)$_GET['lang']) === 1)
    ? (string)$_GET['lang']
    : 'english';

  // Base layer: Default in the requested language, falling back to english.
  $defaultRoot = realpath($defaultCssDir . DIRECTORY_SEPARATOR . $lang);

  if ($defaultRoot === false) {
    $defaultRoot = realpath($defaultCssDir . DIRECTORY_SEPARATOR . 'english');
  }

  if ($defaultRoot === false) {
    http_response_code(500);
    header('Content-Type: text/css; charset=utf-8');
    echo '/* CSS loader error: Default css directory not found */';
    return;
  }

  // Resolve and strictly validate the requested active theme.
  $requestedTheme = isset($_GET['theme'])
    ? HTML::sanitize((string)$_GET['theme'])
    : (defined('SITE_THEMA') ? SITE_THEMA : 'Default');

  $themeRoot = null;

  // Whitelist: single-segment directory name, must exist under sources/template, and not be Default itself.
  if ($requestedTheme !== 'Default'
    && preg_match('/^[A-Za-z0-9_\-]+$/', $requestedTheme) === 1
    && is_dir($templatesDir . DIRECTORY_SEPARATOR . $requestedTheme)) {

    // Prefer the theme directory for the requested language; fall back to its english directory.
    $candidate = realpath($templatesDir . DIRECTORY_SEPARATOR . $requestedTheme . '/css/' . $lang);

    if ($candidate === false) {
      $candidate = realpath($templatesDir . DIRECTORY_SEPARATOR . $requestedTheme . '/css/english');
    }

    // Containment check: the resolved override layer must stay inside sources/template.
    if ($candidate !== false && str_starts_with($candidate . DIRECTORY_SEPARATOR, $templatesDir . DIRECTORY_SEPARATOR)) {
      $themeRoot = $candidate;
    }
  }

  // Merge Default + active theme, then sanitize, compress and stream the response.
  $CLICSHOPPING_templateCss->render($defaultRoot, $themeRoot);
