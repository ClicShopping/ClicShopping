<?php
/**
 * Technical Configuration Constants for RAG System
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * This file defines all technical RAG configuration constants.
 * These are implementation details that rarely need to be changed.
 * Admin-level controls remain in Core/config_clicshopping.php
 *
 * @created 2026-01-09
 * @see .kiro/specs/active/technical-config-migration.md
 */

// ============================================================================
// Active domain for RAG BI system (multi-domain support)
// ============================================================================
// Possible values: 'Ecommerce', 'Hr', 'Finance', 'Trading' ....
// This determines which domain-specific prompts and configurations are loaded
// Default: 'Ecommerce' (backward compatibility with AI app naming)
  if (!defined('CLICSHOPPING_APP_CHATGPT_RA_ACTIVITIES')) define('CLICSHOPPING_APP_CHATGPT_RA_ACTIVITIES', 'Ecommerce');


// ============================================================================
// LIMITS & THRESHOLDS (7 constants)
// ============================================================================

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_MAX_PROMPT_LENGTH'))
  define('CLICSHOPPING_APP_CHATGPT_RA_MAX_PROMPT_LENGTH', 100000);

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_SQL_SAFETY_LIMIT'))
  define('CLICSHOPPING_APP_CHATGPT_RA_SQL_SAFETY_LIMIT', 10000);

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_MIN_SIMILARITY_SCORE'))
  define('CLICSHOPPING_APP_CHATGPT_RA_MIN_SIMILARITY_SCORE', 0.25);

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_MEMORY_MIN_SCORE'))
  define('CLICSHOPPING_APP_CHATGPT_RA_MEMORY_MIN_SCORE', 0.85);

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_MAX_RESULTS_PER_STORE'))
  define('CLICSHOPPING_APP_CHATGPT_RA_MAX_RESULTS_PER_STORE', 5);

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_RERANKING_OUTPUT'))
  define('CLICSHOPPING_APP_CHATGPT_RA_RERANKING_OUTPUT', 5);

// ============================================================================
// CACHE TTL (1 constant)
// ============================================================================

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_TTL'))
  define('CLICSHOPPING_APP_CHATGPT_RA_CACHE_TTL', 2592000); // 30 days

// ============================================================================
// FALLBACK (2 constants)
// ============================================================================

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_ENABLE_WEB_FALLBACK'))
  define('CLICSHOPPING_APP_CHATGPT_RA_ENABLE_WEB_FALLBACK', 'True');

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_ENABLE_LLM_FALLBACK'))
  define('CLICSHOPPING_APP_CHATGPT_RA_ENABLE_LLM_FALLBACK', 'True');

// ============================================================================
// SCHEMA RAG (3 constants)
// ============================================================================

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_SCHEMA_RAG'))
  define('CLICSHOPPING_APP_CHATGPT_RA_SCHEMA_RAG', 'True');

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_SCHEMA_USE_EMBEDDINGS'))
  define('CLICSHOPPING_APP_CHATGPT_RA_SCHEMA_USE_EMBEDDINGS', 'False');

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_SCHEMA_MAX_TABLES'))
  define('CLICSHOPPING_APP_CHATGPT_RA_SCHEMA_MAX_TABLES', 5);

// ============================================================================
// REASONING AGENT (3 constants)
// ============================================================================

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_MAX_REASONING_STEPS'))
  define('CLICSHOPPING_APP_CHATGPT_RA_MAX_REASONING_STEPS', 10);

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_CONSISTENCY_PATHS'))
  define('CLICSHOPPING_APP_CHATGPT_RA_CONSISTENCY_PATHS', 3);

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_TREE_PATHS'))
  define('CLICSHOPPING_APP_CHATGPT_RA_TREE_PATHS', 3);

// ============================================================================
// SECURITY TECHNICAL (6 constants)
// ============================================================================

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_THREAT_THRESHOLD'))
  define('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_THREAT_THRESHOLD', 0.7);

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_PATTERN_FALLBACK'))
  define('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_PATTERN_FALLBACK', false);

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_LLM_TIMEOUT'))
  define('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_LLM_TIMEOUT', 5000);

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_LOG_ALL_QUERIES'))
  define('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_LOG_ALL_QUERIES', false);

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_LOG_BLOCKED_ONLY'))
  define('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_LOG_BLOCKED_ONLY', true);

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_RESPONSE_VALIDATION'))
  define('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_RESPONSE_VALIDATION', true);

// ============================================================================
// SECURITY ALERTING (5 constants)
// ============================================================================

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERT_THRESHOLD'))
  define('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERT_THRESHOLD', 10);

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_HIGH_THREAT_THRESHOLD'))
  define('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_HIGH_THREAT_THRESHOLD', 20);

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERT_COOLDOWN'))
  define('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERT_COOLDOWN', 60);

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERT_DIGEST_MODE'))
  define('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERT_DIGEST_MODE', true);

if (!defined('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_FAILURE_ALERTS'))
  define('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_FAILURE_ALERTS', true);

// ============================================================================
// Calculator Tool Configuration (2026-01-09)
// ============================================================================
// Enable/Disable CalculatorTool (admin-level control)
// This is the only Calculator setting that should be in global config
// Technical settings (history size, validation, timeouts, cache TTL) are
// defined as class constants in CalculatorTool.php

if (!defined('CLICSHOPPING_APP_CHATGPT_CALCULATOR_ENABLED'))
  define('CLICSHOPPING_APP_CHATGPT_CALCULATOR_ENABLED', 'True');

// ============================================================================
// PARALLEL LLM EXECUTOR (2 constants)
// ============================================================================

// Timeout for parallel LLM calls (in seconds)
// Each individual call will timeout after this duration
// Default: 30 seconds
if (!defined('CLICSHOPPING_APP_CHATGPT_RA_PARALLEL_TIMEOUT'))
  define('CLICSHOPPING_APP_CHATGPT_RA_PARALLEL_TIMEOUT', 30);

// Maximum concurrent LLM calls
// Limits the number of simultaneous requests to avoid overloading the API
// Default: 5 concurrent calls
if (!defined('CLICSHOPPING_APP_CHATGPT_RA_PARALLEL_MAX_CONCURRENT'))
  define('CLICSHOPPING_APP_CHATGPT_RA_PARALLEL_MAX_CONCURRENT', 5);

// ============================================================================
// QUERY EXECUTION TIMEOUT (1 constant)
// ============================================================================

// Maximum execution time for RAG queries (in seconds)
// This timeout must be >= HybridQueryProcessor cold cache timeout (120s)
// to allow hybrid queries to complete successfully
// Default: 120 seconds (matches cold cache timeout)
// BUG FIX 2026-02-09: Increased from 60s to 120s to fix hybrid query timeouts
if (!defined('CLICSHOPPING_APP_CHATGPT_RA_MAX_EXECUTION_TIME'))
  define('CLICSHOPPING_APP_CHATGPT_RA_MAX_EXECUTION_TIME', 120);

// ============================================================================
// HYBRID QUERY DECOMPOSITION (3 constants)
// ============================================================================

// Enable/Disable hybrid query decomposition
// When enabled, hybrid queries are decomposed into separate sub-queries using LLM
// When disabled, hybrid queries are processed as single-step queries
// Default: True (enabled)
// @see .kiro/specs/hybrid-query-decomposition/requirements.md (Requirement 12.1)
if (!defined('CLICSHOPPING_APP_CHATGPT_RA_HYBRID_DECOMPOSITION_STATUS'))
  define('CLICSHOPPING_APP_CHATGPT_RA_HYBRID_DECOMPOSITION_STATUS', 'True');

// LLM provider for hybrid query decomposition
// Uses the default LLM configuration from CLICSHOPPING_APP_CHATGPT_CH_MODEL
// This constant is reserved for future use if we need a separate provider
// Default: null (uses default LLM provider)
// @see .kiro/specs/hybrid-query-decomposition/requirements.md (Requirement 12.2)
if (!defined('CLICSHOPPING_APP_CHATGPT_RA_HYBRID_DECOMPOSITION_LLM_PROVIDER'))
  define('CLICSHOPPING_APP_CHATGPT_RA_HYBRID_DECOMPOSITION_LLM_PROVIDER', null);

// Debug mode for hybrid query decomposition
// Uses the default RAG debug configuration from CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER
// This constant is reserved for future use if we need separate debug control
// Default: null (uses default RAG debug setting)
// @see .kiro/specs/hybrid-query-decomposition/requirements.md (Requirement 12.3)
if (!defined('CLICSHOPPING_APP_CHATGPT_RA_HYBRID_DECOMPOSITION_DEBUG'))
  define('CLICSHOPPING_APP_CHATGPT_RA_HYBRID_DECOMPOSITION_DEBUG', null);
