<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Footer;

use ClicShopping\OM\CLICSHOPPING;

class FooterOutputServiceWorker
{
  /**
   * Generates and returns a JavaScript script block that includes a service worker registration implementation for offline functionality.
   *
   * @return string The generated script block as a string.
   */
  public function display(): string
  {
    $base_path = CLICSHOPPING::getConfig('http_path', 'Shop');

    $output = '<script defer>
// This is the "Offline page" service worker
// Add this below content to your HTML page, or add the js file to your page at the very top to register service worker

// Check compatibility for the browser we\'re running this in
if ("serviceWorker" in navigator) {
  if (navigator.serviceWorker.controller) {
    console.log("[PWA Builder] active service worker found, no need to register");
  } else {
    // Register the service worker
    navigator.serviceWorker
      .register("' . $base_path . 'pwabuilder-sw.js", {
        scope: "' . $base_path . '"
      })
      .then(function (reg) {
        console.log("[PWA Builder] Service worker has been registered for scope: " + reg.scope);
      });
  }
}
      </script>' . "\n";

    return $output;
  }
}