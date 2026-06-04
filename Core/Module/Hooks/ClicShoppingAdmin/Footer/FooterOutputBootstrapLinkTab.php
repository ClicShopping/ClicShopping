<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\ClicShoppingAdmin\Footer;

/**
 * Class FooterOutputBootstrapLinkTab
 *
 * This class is responsible for generating a JavaScript snippet that helps dynamically activate
 * Bootstrap navigation tabs based on certain conditions. It checks if specific criteria are
 * fulfilled, such as the presence of a query string and a session key, to generate and return
 * the JavaScript code.
 */
class FooterOutputBootstrapLinkTab
{
  /**
   * Generates a JavaScript snippet for dynamically activating Bootstrap navigation tabs
   * based on the URL fragment if certain conditions are met (e.g., the session contains
   * an 'admin' key and a query string is present).
   *
   * @return string|bool Returns the generated JavaScript code as a string if conditions
   *         are fulfilled, otherwise returns false.
   */
  public function display(): string|bool
  {
    $params = $_SERVER['QUERY_STRING'] ?? '';

    if (empty($params)) {
      return false;
    }

    $output = '';

    if (isset($_SESSION['admin'])) {
      $output .= '<!-- Bootstrap Link tab Script start-->' . "\n";
      $output .= '
  <!-- if the page request contains a link to a tab, open that tab on page load -->
  <script>
      $(function () {
          var url = document.location.toString();
  
          if (url.match(\'#\')) {
              if ($(\'.nav-tabs a[data-bs-target="#\' + url.split(\'#\')[1] + \'"]\').length === 1) {
                  $(\'.nav-tabs a[data-bs-target="#\' + url.split(\'#\')[1] + \'"]\').tab(\'show\');
              }
          }
      });
  </script>
          ' . "\n";
      $output .= '<!--Bootstrap Link tab end -->' . "\n";
    } else {
      return false;
    }

    return $output;
  }
}