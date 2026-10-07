<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process for the ChatGpt module.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');
    $CLICSHOPPING_ChatGpt->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
    self::installDb();
  }

  /**
   * Installs the ChatGPT administration menu entry in the database.
   *
   * This method checks if the ChatGPT entry already exists in the `administrator_menu` table.
   * If it does not exist, it creates a new entry with appropriate details, including menu ordering,
   * link, image, and associated application code. It also inserts the corresponding labels in the
   * `administrator_menu_description` table for each available language. After the operation, it clears
   * the administrator menu cache.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_configuration_chatgpt']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = [
        'sort_order' => 100,
        'link' => 'index.php?A&Configuration\ChatGpt&ChatGpt&Configure',
        'image' => 'chatgpt.gif',
        'b2b_menu' => 0,
        'access' => 1,
        'app_code' => 'app_configuration_chatgpt'
      ];

      $insert_sql_data = ['parent_id' => 14];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_ChatGpt->getDef('title_menu')];

        $insert_sql_data = [
          'id' => (int)$id,
          'language_id' => (int)$language_id
        ];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $CLICSHOPPING_Db->save('administrator_menu_description', $sql_data_array);
      }

      Cache::clear('menu-administrator');
    }
  }

  /**
   * Installs the database tables required for the GPT functionality if they do not already exist.
   *
   * @return void
   */
  private static function installDb()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_pages_manager_embedding"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
      CREATE TABLE IF NOT EXISTS :table_pages_manager_embedding (
        `id` int(11) NOT NULL auto_increment COMMENT 'Primary key - unique identifier for each embedding chunk',
        `content` text DEFAULT NULL COMMENT 'Text content that was embedded - page content, title, description',
        `type` text DEFAULT NULL COMMENT 'Type of content - page_content, title, meta_description, or keywords',
        `sourcetype` text DEFAULT 'manual' COMMENT 'Source type - manual, auto, or import',
        `sourcename` text DEFAULT 'manual' COMMENT 'Source name - identifies the origin of the content',
        `embedding` vector(3072) NOT NULL COMMENT 'Vector embedding with 3072 dimensions - OpenAI text-embedding-3-large format',
        `chunknumber` int(11) DEFAULT 128 COMMENT 'Chunk number for splitting large content - default 128 tokens per chunk',
        `date_modified` datetime DEFAULT NULL COMMENT 'Timestamp of last modification to this embedding',
        `created_at` timestamp DEFAULT current_timestamp COMMENT 'Creation timestamp - immutable, auto-set on insert',
        `entity_id` int(11) NOT NULL COMMENT 'FK to pages_manager table - references the page this embedding represents',
        `language_id` int(11) NOT NULL COMMENT 'FK to languages table - language of the embedded content',
        `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional metadata about the embedding - may include page_type, status, url' CHECK (json_valid(`metadata`)),
        PRIMARY KEY (`id`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_entity_id` (`entity_id`),
        KEY `idx_language_id` (`language_id`),
        KEY `idx_entity_lang` (`entity_id`, `language_id`),
        KEY `idx_date_modified` (`date_modified`),
        VECTOR KEY `embedding_index` (`embedding`)
      ) ENGINE innodb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

    EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

//--------------------------------------
// RAG
//--------------------------------------

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_rag_correction_patterns_embedding"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
      CREATE TABLE IF NOT EXISTS :table_rag_correction_patterns_embedding (
        `id` bigint(20) unsigned NOT NULL auto_increment COMMENT 'Primary key - auto-incremented unique identifier',
        `content` text DEFAULT NULL COMMENT 'Correction pattern content for embedding generation',
        `type` text DEFAULT NULL COMMENT 'Type of correction pattern',
        `sourcetype` text DEFAULT NULL COMMENT 'Source type of the correction pattern',
        `sourcename` text DEFAULT NULL COMMENT 'Name of the source system or module',
        `embedding` vector(3072) NOT NULL COMMENT 'Embedding vector for semantic search',
        `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '{}' NOT NULL COMMENT 'Additional metadata in JSON format' CHECK (json_valid(`metadata`)),
        `chunknumber` int(11) DEFAULT 128 COMMENT 'Chunk size used for embedding generation',
        `date_modified` datetime DEFAULT NULL COMMENT 'Last modification timestamp',
        `created_at` timestamp DEFAULT current_timestamp COMMENT 'Creation timestamp - immutable, auto-set on insert',
        `entity_id` int(11) DEFAULT 0 COMMENT 'Entity ID (0 = no specific entity, NULL = unknown)',
        `entity_type` varchar(50) DEFAULT NULL COMMENT 'Type of entity (product, category, page, etc.)',
        `language_id` int(11) NOT NULL COMMENT 'Language identifier for the correction pattern',
        PRIMARY KEY (`id`),
        UNIQUE KEY `id` (`id`),
        KEY `idx_entity_id` (`entity_id`),
        KEY `idx_language_id` (`language_id`),
        KEY `idx_user_id` (`metadata`(100)),
        KEY `idx_entity` (`entity_id`, `entity_type`),
        KEY `idx_entity_language` (`entity_id`, `language_id`),
        KEY `idx_entity_type_language` (`entity_type`, `language_id`, `entity_id`),
        KEY `idx_date_modified` (`date_modified`),
        KEY `idx_created_at` (`created_at`),
        VECTOR KEY `embedding` (`embedding`)
      ) ENGINE innodb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     EOD;

      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_rag_conversation_memory_embedding"');


    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
      CREATE TABLE IF NOT EXISTS :table_rag_conversation_memory_embedding (
        `id` bigint(20) unsigned NOT NULL auto_increment COMMENT 'Primary key - unique identifier for each conversation memory chunk',
        `content` text DEFAULT NULL COMMENT 'Text content of the conversation - user queries and system responses',
        `type` text DEFAULT NULL COMMENT 'Type of memory - user_query, system_response, or context',
        `sourcetype` text DEFAULT NULL COMMENT 'Source type - chat, api, or system',
        `sourcename` text DEFAULT NULL COMMENT 'Source name - identifies the conversation session or user',
        `embedding` vector(3072) NOT NULL COMMENT 'Vector embedding with 1536 dimensions - OpenAI text-embedding-ada-002 format for semantic search',
        `user_message` text DEFAULT NULL COMMENT 'User message from conversation',
        `assistant_response` text DEFAULT NULL COMMENT 'Assistant response from conversation',
        `chunknumber` int(11) DEFAULT 128 COMMENT 'Chunk number for splitting long conversations - sequential numbering',
        `date_modified` datetime DEFAULT NULL COMMENT 'Timestamp of last modification to this memory entry',
        `entity_id` int(11) DEFAULT NULL COMMENT 'FK to conversation or user identifier - references the conversation session',
        `entity_type` varchar(50) DEFAULT NULL COMMENT 'Entity type (nullable for general conversations)',
        `language_id` int(11) NOT NULL COMMENT 'FK to languages table - language of the conversation content',
        `user_id` varchar(255) DEFAULT NULL COMMENT 'User ID for fast filtering',
        `interaction_id` varchar(255) DEFAULT NULL COMMENT 'Interaction ID to prevent duplicates',
        `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '{}' NOT NULL COMMENT 'Additional metadata in JSON format' CHECK (json_valid(`metadata`)),
        `created_at` timestamp DEFAULT current_timestamp COMMENT 'Creation timestamp',
        PRIMARY KEY (`id`),
        UNIQUE KEY `id` (`id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_interaction_id` (`interaction_id`),
        KEY `idx_language_id` (`language_id`),
        KEY `idx_user_lang_date` (`user_id`, `language_id`, `date_modified`),
        KEY `idx_date_modified` (`date_modified`),
        KEY `idx_entity` (`entity_id`, `entity_type`),
        KEY `idx_interaction_user` (`interaction_id`, `user_id`),
        KEY `idx_created_at` (`created_at`),
        VECTOR KEY `embedding` (`embedding`)
      ) ENGINE innodb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_rag_web_cache_embedding"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
      CREATE TABLE IF NOT EXISTS :table_rag_web_cache_embedding (
        `id` int(11) NOT NULL auto_increment,
        `content` longtext NOT NULL COMMENT 'Contenu complet (query + synthèse + sources)',
        `type` varchar(50) DEFAULT 'web_search_cache' COMMENT 'Type de document',
        `sourcetype` varchar(50) DEFAULT 'web_search' COMMENT 'Source du document',
        `sourcename` varchar(255) DEFAULT 'serpapi' COMMENT 'Nom de la source (serpapi, bing, etc.)',
        `embedding` vector(3072) NOT NULL COMMENT 'Vecteur d embedding (adapter selon modèle)',
        `original_query` text DEFAULT NULL COMMENT 'Requête originale ayant généré ce résultat',
        `search_engine` varchar(50) DEFAULT NULL COMMENT 'Moteur de recherche utilisé',
        `quality_score` float DEFAULT 0 COMMENT 'Score de qualité du résultat (0-1)',
        `usage_count` int(11) DEFAULT 0 COMMENT 'Nombre de fois que ce résultat a été réutilisé',
        `last_used` datetime DEFAULT NULL COMMENT 'Dernière date d utilisation',
        `entity_id` int(11) DEFAULT NULL COMMENT 'ID de la requête source (optionnel)',
        `language_id` int(11) DEFAULT 1 COMMENT 'ID de la langue',
        `chunknumber` int(11) DEFAULT 128 COMMENT 'Taille du chunk utilisé',
        `entity_type` varchar(50) DEFAULT NULL COMMENT 'Type of entity (web_search)',
        `date_modified` datetime DEFAULT current_timestamp ON UPDATE current_timestamp,
        `created_at` timestamp DEFAULT current_timestamp COMMENT 'Creation timestamp - immutable, auto-set on insert',
        `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
        PRIMARY KEY (`id`),
        KEY `idx_type` (`type`),
        KEY `idx_sourcetype` (`sourcetype`),
        KEY `idx_quality_score` (`quality_score`),
        KEY `idx_usage_count` (`usage_count`),
        KEY `idx_last_used` (`last_used`),
        KEY `idx_quality_usage` (`quality_score`, `usage_count`),
        KEY `idx_quality_usage_last` (`quality_score`, `usage_count`, `last_used`),
        KEY `idx_search_engine_quality` (`search_engine`, `quality_score`),
        KEY `idx_language_quality` (`language_id`, `quality_score`),
        KEY `idx_created_at` (`created_at`),
        VECTOR KEY `embedding_index` (`embedding`)
      ) ENGINE innodb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
      EOD;

      $CLICSHOPPING_Db->exec($sql);
    }

//--------------------------------------
// RAG Schema Embeddings
//--------------------------------------

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_rag_schema_embedding"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
      CREATE TABLE IF NOT EXISTS :table_rag_schema_embedding (
        `id` int(11) NOT NULL auto_increment COMMENT 'Unique identifier for schema embedding',
        `table_name` varchar(255) NOT NULL COMMENT 'Database table name (e.g., clic_products)',
        `schema_text` text NOT NULL COMMENT 'Schema description including column names, types, and comments',
        `embedding_vector` vector(3072) NOT NULL COMMENT 'Vector embedding of schema text for similarity search',
        `token_count` int(11) DEFAULT 0 COMMENT 'Estimated token count of schema text',
        `created_at` datetime DEFAULT current_timestamp COMMENT 'Timestamp when embedding was created',
        `updated_at` datetime DEFAULT current_timestamp ON UPDATE current_timestamp COMMENT 'Timestamp when embedding was last updated',
        PRIMARY KEY (`id`),
        UNIQUE KEY `table_name` (`table_name`),
        KEY `idx_table_name` (`table_name`),
        KEY `idx_updated_at` (`updated_at`),
        VECTOR KEY `embedding_index` (`embedding_vector`)
      ) ENGINE innodb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT='Stores database schema embeddings with column comments for LLM-powered query generation';
    EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
  }

  
  
  
/*
  
//--------------------------------------
// Chatbot rate limit
//--------------------------------------

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_rag_rate_limit"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
      CREATE TABLE IF NOT EXISTS :table_rag_rate_limit (
        id INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary key - one row per accepted question',
        identifier VARCHAR(255) NOT NULL COMMENT 'Caller key, stored in clear so an excess stays attributable: admin:<id>, mcp:<id>, system:<job>',
        timestamp INT(11) NOT NULL COMMENT 'Unix timestamp of the accepted question - drives the sliding window',
        ip VARCHAR(45) DEFAULT NULL COMMENT 'IP address of the caller - IPv4 or IPv6 format',
        PRIMARY KEY (id),
        KEY idx_identifier_timestamp (identifier, timestamp),
        KEY idx_timestamp (timestamp)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      COMMENT='Sliding window rate limit of the AI chatbot, keyed by channel and user';
    EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
    
//IMPORTANT ; not implemented

    // Check if rag_security_config table exists
    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_rag_security_config"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
      CREATE TABLE IF NOT EXISTS :table_rag_security_config (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
        `config_key` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Configuration key (e.g., threat_threshold, llm_timeout)',
        `config_value` TEXT NOT NULL COMMENT 'Configuration value (JSON for complex values)',
        `config_type` ENUM('string', 'integer', 'float', 'boolean', 'json') NOT NULL DEFAULT 'string' COMMENT 'Data type of the value',
        `description` TEXT DEFAULT NULL COMMENT 'Description of the configuration',
        `category` VARCHAR(50) DEFAULT 'general' COMMENT 'Configuration category: thresholds, timeouts, features, alerting',
        `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 if configuration is active',
        `min_value` DECIMAL(10,4) DEFAULT NULL COMMENT 'Minimum allowed value (for numeric types)',
        `max_value` DECIMAL(10,4) DEFAULT NULL COMMENT 'Maximum allowed value (for numeric types)',
        `allowed_values` JSON DEFAULT NULL COMMENT 'List of allowed values (for enum-like configs)',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp',
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp',
        `updated_by` VARCHAR(255) DEFAULT NULL COMMENT 'User who last updated the config',
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_config_key` (`config_key`),
        KEY `idx_category` (`category`),
        KEY `idx_is_active` (`is_active`),
        KEY `idx_updated_at` (`updated_at`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
      COMMENT='Security configuration for RAG system';
EOD;
      $CLICSHOPPING_Db->exec($sql);

      // Insert default configuration values
      $sql = <<<EOD
      INSERT INTO :table_rag_security_config (`config_key`, `config_value`, `config_type`, `description`, `category`, `min_value`, `max_value`) VALUES
        ('threat_threshold', '0.7', 'float', 'Threat score threshold for blocking (0.0-1.0)', 'thresholds', 0.0, 1.0),
        ('high_confidence_threshold', '0.9', 'float', 'High confidence threshold (0.0-1.0)', 'thresholds', 0.0, 1.0),
        ('false_positive_threshold', '0.3', 'float', 'Threshold below which to flag as potential false positive', 'thresholds', 0.0, 1.0),
        ('llm_timeout_ms', '5000', 'integer', 'LLM security analysis timeout in milliseconds', 'timeouts', 1000, 30000),
        ('pattern_timeout_ms', '100', 'integer', 'Pattern detection timeout in milliseconds', 'timeouts', 10, 1000),
        ('total_security_timeout_ms', '6000', 'integer', 'Total security check timeout in milliseconds', 'timeouts', 1000, 30000),
        ('use_llm_primary_security', 'true', 'boolean', 'Use LLM as primary security method', 'features', NULL, NULL),
        ('use_pattern_fallback', 'false', 'boolean', 'Use pattern-based detection as fallback', 'features', NULL, NULL),
        ('enable_response_validation', 'true', 'boolean', 'Enable response validation layer', 'features', NULL, NULL),
        ('log_all_queries', 'false', 'boolean', 'Log all queries (not just threats)', 'features', NULL, NULL),
        ('log_blocked_only', 'true', 'boolean', 'Log only blocked queries', 'features', NULL, NULL),
        ('log_retention_days', '90', 'integer', 'Number of days to retain security logs', 'retention', 1, 365),
        ('auto_archive_enabled', 'true', 'boolean', 'Enable automatic archiving of old logs', 'retention', NULL, NULL),
        ('email_alerts_enabled', 'false', 'boolean', 'Enable email alerts for security events', 'alerting', NULL, NULL),
        ('alert_email', '', 'string', 'Email address for security alerts', 'alerting', NULL, NULL),
        ('alert_threshold_per_hour', '10', 'integer', 'Number of threats per hour to trigger alert', 'alerting', 1, 1000),
        ('alert_on_critical_only', 'true', 'boolean', 'Only send alerts for critical severity events', 'alerting', NULL, NULL)
      ON DUPLICATE KEY UPDATE 
        `config_value` = VALUES(`config_value`),
        `updated_at` = CURRENT_TIMESTAMP;
EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
  }
  */
}