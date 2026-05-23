<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

define('MODE_B2B_B2C', 'True'); // true ou false
define('MODE_DEMO', 'False'); // only demo mode
define('DEBUG_MODE', 'False'); // only for development

// ============================================================================
// TECHNICAL CONFIGURATION
// ============================================================================
// Load all technical constants from TechnicalConfig class
// Only loaded if RAG is enabled
if (defined('CLICSHOPPING_APP_CHATGPT_RA_STATUS') && CLICSHOPPING_APP_CHATGPT_RA_STATUS === 'True') {
  require_once(__DIR__ . '/ClicShopping/AI/Config/TechnicalConfig.php');
}