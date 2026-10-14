<?php
/**
 * Technical Configuration Constants for RAG System
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * This file turns the declared technical defaults into constants. It holds NO value of its own:
 * every default lives in TechnicalDefaults, so that a consumer running with the ChatGpt App
 * uninstalled reads the same number this file would have defined.
 *
 * Loaded only when Core/config_clicshopping.php has opened the RA_STATUS gate — hence the explicit
 * require rather than the autoloader, which would run this side effect on a class lookup.
 *
 * @created 2026-01-09
 * @see \ClicShopping\AI\Config\TechnicalDefaults
 */

require_once __DIR__ . '/TechnicalDefaults.php';

foreach (\ClicShopping\AI\Config\TechnicalDefaults::all() as $technicalConfigKey => $technicalConfigValue) {
  if (!defined($technicalConfigKey)) {
    define($technicalConfigKey, $technicalConfigValue);
  }
}

unset($technicalConfigKey, $technicalConfigValue);
