<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\CLICSHOPPING;
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

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_gpt"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
          CREATE TABLE :table_gpt (
            gpt_id int(11) NOT NULL COMMENT 'Primary key - unique identifier for each GPT interaction',
            question text NOT NULL COMMENT 'User question or prompt submitted to GPT',
            response text NOT NULL COMMENT 'GPT generated response text',
            date_added date DEFAULT NULL COMMENT 'Date when the interaction was recorded',
            KEY idx_date_added (date_added)
          ) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GPT interaction history with questions and responses';

          ALTER TABLE :table_gpt  ADD PRIMARY KEY (gpt_id);
          ALTER TABLE :table_gpt  MODIFY gpt_id int(11) NOT NULL AUTO_INCREMENT;

          CREATE TABLE :table_gpt_usage (
            usage_id int(11) NOT NULL COMMENT 'Primary key - unique identifier for each usage record',
            gpt_id int(11) NOT NULL COMMENT 'FK to gpt table - links to the GPT interaction',
            promptTokens int(11) DEFAULT NULL COMMENT 'Number of tokens used in the prompt/input',
            completionTokens int(11) DEFAULT NULL COMMENT 'Number of tokens used in the completion/output',
            totalTokens int(11) DEFAULT NULL COMMENT 'Total tokens used (prompt + completion)',
            ia_type varchar(255) DEFAULT NULL COMMENT 'AI provider type - openai, ollama, anthropic, etc.',
            model varchar(255) DEFAULT NULL COMMENT 'Model name used - gpt-4, gpt-3.5-turbo, claude-3, etc.',
            date_added date DEFAULT NULL COMMENT 'Date when the usage was recorded',
            KEY idx_gpt_id (gpt_id),
            KEY idx_date_added (date_added),
            KEY idx_model (model),
            KEY idx_ia_type (ia_type)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Token usage tracking for GPT API calls and cost analysis';
          ALTER TABLE :table_gpt_usage  ADD PRIMARY KEY (usage_id);
          ALTER TABLE :table_gpt_usage  MODIFY usage_id int(11) NOT NULL AUTO_INCREMENT;
          EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_pages_manager_embedding"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
      CREATE TABLE IF NOT EXISTS :table_pages_manager_embedding (
          id SERIAL PRIMARY KEY COMMENT 'Primary key - unique identifier for each page embedding',
          content longtext DEFAULT NULL COMMENT 'Page content for embedding generation - title, body, metadata, etc.',
          type TEXT DEFAULT NULL COMMENT 'Type of content - page_content, metadata, full_text',
          sourcetype TEXT DEFAULT 'manual' COMMENT 'Source type - manual, automated, imported',
          sourcename TEXT DEFAULT 'manual' COMMENT 'Name of the source system or process',
          embedding VECTOR(3072) NOT NULL COMMENT 'Vector embedding (3072 dimensions) for semantic search',
          chunknumber INT DEFAULT 128 COMMENT 'Chunk size used for embedding generation',
          date_modified DATETIME DEFAULT NULL COMMENT 'Last modification timestamp',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp - immutable, auto-set on insert',
          entity_id INT COMMENT 'FK to pages_manager table - page ID',
          language_id INT COMMENT 'FK to languages table - language identifier',
          metadata longtext DEFAULT NULL COMMENT 'Additional metadata about the embedding - may include page_type, status, url',
          KEY idx_entity_id (entity_id),
          KEY idx_language_id (language_id),
          KEY idx_entity_lang (entity_id, language_id),
          KEY idx_date_modified (date_modified),
          KEY idx_created_at (created_at)
      ) COMMENT='Vector embeddings for CMS pages - enables semantic page search and content discovery';

      CREATE VECTOR INDEX embedding_index ON :table_pages_manager_embedding (embedding);

    EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

//--------------------------------------
// RAG
//--------------------------------------

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_rag_correction_patterns_embedding"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
     CREATE TABLE :table_rag_correction_patterns_embedding (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key - auto-incremented unique identifier',
        content text DEFAULT NULL COMMENT 'Correction pattern content for embedding generation',
        type text DEFAULT NULL COMMENT 'Type of correction pattern',
        sourcetype text DEFAULT NULL COMMENT 'Source type of the correction pattern',
        sourcename text DEFAULT NULL COMMENT 'Name of the source system or module',
        embedding vector(3072) NOT NULL COMMENT 'Embedding vector for semantic search',
        metadata JSON NOT NULL DEFAULT '{}' COMMENT 'Additional metadata in JSON format',
        chunknumber int(11) DEFAULT 128 COMMENT 'Chunk size used for embedding generation',
        date_modified datetime DEFAULT NULL COMMENT 'Last modification timestamp',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp - immutable, auto-set on insert',
        entity_id int(11) NULL DEFAULT 0 COMMENT 'Entity ID (0 = no specific entity, NULL = unknown)',
        entity_type VARCHAR(50) NULL COMMENT 'Type of entity (product, category, page, etc.)',
        language_id int(11) NOT NULL COMMENT 'Language identifier for the correction pattern',
        PRIMARY KEY (id),
        UNIQUE KEY id (id),
        VECTOR INDEX embedding_index (embedding),
        KEY idx_entity_id (entity_id),
        KEY idx_language_id (language_id),
        KEY idx_user_id (metadata(100)),
        KEY idx_entity (entity_id, entity_type),
        KEY idx_entity_language (entity_id, language_id),
        KEY idx_entity_type_language (entity_type, language_id, entity_id),
        KEY idx_date_modified (date_modified),
        KEY idx_created_at (created_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
     EOD;

      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_rag_conversation_memory_embedding"');


    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
        CREATE TABLE :table_rag_conversation_memory_embedding (
          id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key - auto-incremented unique identifier',
          content text DEFAULT NULL COMMENT 'Conversation content for embedding generation',
          type text DEFAULT NULL COMMENT 'Type of content (conversation, message, etc.)',
          sourcetype text DEFAULT NULL COMMENT 'Source type of the conversation',
          sourcename text DEFAULT NULL COMMENT 'Name of the source system or module',
          embedding vector(3072) NOT NULL COMMENT 'Embedding vector (NULL if not yet generated)',
          user_message TEXT COMMENT 'User message from conversation',
          assistant_response TEXT COMMENT 'Assistant response from conversation',
          chunknumber int(11) DEFAULT 128 COMMENT 'Chunk size used for embedding generation',
          date_modified datetime DEFAULT NULL COMMENT 'Last modification timestamp',
          entity_id int(11) NULL DEFAULT NULL COMMENT 'Entity ID (nullable for general conversations)',
          entity_type VARCHAR(50) NULL DEFAULT NULL COMMENT 'Entity type (nullable for general conversations)',
          language_id int(11) NOT NULL COMMENT 'Language identifier for the conversation',
          user_id VARCHAR(255) DEFAULT NULL COMMENT 'User ID for fast filtering',
          interaction_id VARCHAR(255) DEFAULT NULL COMMENT 'Interaction ID to prevent duplicates',
          metadata JSON NOT NULL DEFAULT '{}' COMMENT 'Additional metadata in JSON format',
          created_at timestamp NULL DEFAULT current_timestamp() COMMENT 'Creation timestamp',
          PRIMARY KEY (id),
          UNIQUE KEY id (id),
          VECTOR INDEX embedding_index (embedding),
          KEY idx_user_id (user_id),
          KEY idx_interaction_id (interaction_id),
          KEY idx_language_id (language_id),
          KEY idx_user_lang_date (user_id, language_id, date_modified),
          KEY idx_date_modified (date_modified),
          KEY idx_entity (entity_id, entity_type),
          KEY idx_interaction_user (interaction_id, user_id),
          KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_rag_web_cache_embedding"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
      CREATE TABLE :table_rag_web_cache_embedding (
        `id` int(11) NOT NULL,
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
        `entity_type` VARCHAR(50) DEFAULT NULL COMMENT 'Type of entity (web_search)',
        `date_modified` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp - immutable, auto-set on insert',
        `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`))
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

      ALTER TABLE :table_rag_web_cache_embedding
        ADD PRIMARY KEY (`id`),
        ADD KEY `idx_type` (`type`),
        ADD KEY `idx_sourcetype` (`sourcetype`),
        ADD KEY `idx_quality_score` (`quality_score`),
        ADD KEY `idx_usage_count` (`usage_count`),
        ADD KEY `idx_last_used` (`last_used`),
        ADD KEY `idx_quality_usage` (`quality_score`,`usage_count`),
        ADD KEY `idx_quality_usage_last` (`quality_score`, `usage_count`, `last_used`),
        ADD KEY `idx_search_engine_quality` (`search_engine`, `quality_score`),
        ADD KEY `idx_language_quality` (`language_id`, `quality_score`),
        ADD KEY `idx_created_at` (`created_at`);

      ALTER TABLE :table_rag_web_cache_embedding MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
      
      CREATE VECTOR INDEX embedding_index ON :table_rag_web_cache_embedding (embedding);
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
        id SERIAL PRIMARY KEY COMMENT 'Primary key - unique identifier for each schema embedding',
        table_name VARCHAR(255) NOT NULL UNIQUE COMMENT 'Database table name - unique identifier for the schema',
        schema_text TEXT NOT NULL COMMENT 'Complete schema definition including columns, types, and comments',
        embedding_vector VECTOR(3072) NOT NULL COMMENT 'Vector embedding of the schema for semantic search',
        token_count INT DEFAULT 0 COMMENT 'Number of tokens in the schema text',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'When the schema embedding was created',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last time the schema embedding was updated',
        KEY idx_table_name (table_name),
        KEY idx_updated_at (updated_at)
      ) COMMENT='Vector embeddings of database schemas for semantic schema discovery and natural language to SQL';
      
      CREATE VECTOR INDEX embedding_index ON :table_rag_schema_embedding (embedding_vector);
    EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
  }

  /**
   * Add taxonomy and metadata columns to embedding tables
   *
   * This method adds the required JSON columns for taxonomy and metadata separation
   * to all specified embedding tables. It ensures that embeddings contain only pure
   * content while taxonomy and metadata are stored separately.
   *
   * @param array $tables List of table names to update (without prefix)
   * @return array Results of schema updates with status for each table
   */
  public static function addTaxonomyColumns(array $tables = []): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    $results = [];

    // Default to all known embedding tables if none specified
    if (empty($tables)) {
      $tables = [
        'categories_embedding',
        'products_embedding',
        'orders_embedding',
        'manufacturers_embedding',
        'suppliers_embedding',
        'pages_manager_embedding',
        'return_orders_embedding',
        'reviews_embedding',
        'reviews_sentiment_embedding'
      ];
    }

    foreach ($tables as $table) {
      $fullTableName = $prefix . $table;
      $tableResult = [
        'table' => $fullTableName,
        'taxonomy_added' => false,
        'metadata_added' => false,
        'errors' => []
      ];

      try {
        // Check if table exists
        $checkTable = $CLICSHOPPING_Db->query("SHOW TABLES LIKE '{$fullTableName}'");
        if ($checkTable->fetch() === false) {
          $tableResult['errors'][] = "Table does not exist";
          $results[] = $tableResult;
          continue;
        }

        // Add taxonomy column if it doesn't exist
        try {
          $CLICSHOPPING_Db->exec("
            ALTER TABLE {$fullTableName} 
            ADD COLUMN IF NOT EXISTS taxonomy JSON DEFAULT NULL 
            COMMENT 'Structured taxonomy metadata (separate from embedding content)'
          ");
          $tableResult['taxonomy_added'] = true;
        } catch (\Exception $e) {
          $tableResult['errors'][] = "Taxonomy column: " . $e->getMessage();
        }

        // Add metadata column if it doesn't exist
        try {
          $CLICSHOPPING_Db->exec("
            ALTER TABLE {$fullTableName} 
            ADD COLUMN IF NOT EXISTS metadata JSON DEFAULT NULL 
            COMMENT 'Document metadata for filtering and display'
          ");
          $tableResult['metadata_added'] = true;
        } catch (\Exception $e) {
          $tableResult['errors'][] = "Metadata column: " . $e->getMessage();
        }

        $tableResult['success'] = empty($tableResult['errors']);

      } catch (\Exception $e) {
        $tableResult['errors'][] = "General error: " . $e->getMessage();
        $tableResult['success'] = false;
      }

      $results[] = $tableResult;
    }

    return $results;
  }

  /**
   * Create indexes for JSON metadata queries
   *
   * Creates functional indexes on JSON fields to enable efficient filtering
   * by metadata fields like document_type, entity_type, etc.
   *
   * @param string $tableName Table name (without prefix) to create indexes on
   * @return bool Success status
   */
  public static function createMetadataIndexes(string $tableName): bool
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    $fullTableName = $prefix . $tableName;

    try {
      // Check if table exists
      $checkTable = $CLICSHOPPING_Db->query("SHOW TABLES LIKE '{$fullTableName}'");
      if ($checkTable->fetch() === false) {
        error_log("Table {$fullTableName} does not exist");
        return false;
      }

      // Create index on metadata->>'$.document_type' for efficient filtering
      // Using generated column approach for MariaDB compatibility
      try {
        $CLICSHOPPING_Db->exec("
          ALTER TABLE {$fullTableName} 
          ADD INDEX IF NOT EXISTS idx_metadata_document_type 
          ((CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.document_type')) AS CHAR(50))))
        ");
      } catch (\Exception $e) {
        error_log("Index creation for document_type failed: " . $e->getMessage());
        // Continue with other indexes even if this one fails
      }

      // Create index on metadata->>'$.entity_type' for efficient filtering
      try {
        $CLICSHOPPING_Db->exec("
          ALTER TABLE {$fullTableName} 
          ADD INDEX IF NOT EXISTS idx_metadata_entity_type 
          ((CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.entity_type')) AS CHAR(50))))
        ");
      } catch (\Exception $e) {
        error_log("Index creation for entity_type failed: " . $e->getMessage());
      }

      return true;

    } catch (\Exception $e) {
      error_log("Error creating metadata indexes for {$fullTableName}: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Validate schema changes
   *
   * Verifies that the taxonomy and metadata columns were added successfully
   * and that indexes were created properly.
   *
   * @param string $tableName Table name (without prefix) to validate
   * @return array Validation results with detailed status
   */
  public static function validateSchema(string $tableName): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    $fullTableName = $prefix . $tableName;

    $validation = [
      'table' => $fullTableName,
      'exists' => false,
      'has_taxonomy_column' => false,
      'has_metadata_column' => false,
      'has_content_column' => false,
      'has_embedding_column' => false,
      'indexes' => [],
      'record_count' => 0,
      'errors' => []
    ];

    try {
      // Check if table exists
      $checkTable = $CLICSHOPPING_Db->query("SHOW TABLES LIKE '{$fullTableName}'");
      if ($checkTable->fetch() === false) {
        $validation['errors'][] = "Table does not exist";
        return $validation;
      }
      $validation['exists'] = true;

      // Check columns
      $columns = $CLICSHOPPING_Db->query("SHOW COLUMNS FROM {$fullTableName}");
      while ($column = $columns->fetch()) {
        $columnName = $column['Field'];

        if ($columnName === 'taxonomy') {
          $validation['has_taxonomy_column'] = true;
          $validation['taxonomy_type'] = $column['Type'];
        }
        if ($columnName === 'metadata') {
          $validation['has_metadata_column'] = true;
          $validation['metadata_type'] = $column['Type'];
        }
        if ($columnName === 'content') {
          $validation['has_content_column'] = true;
        }
        if ($columnName === 'embedding') {
          $validation['has_embedding_column'] = true;
        }
      }

      // Check indexes
      $indexes = $CLICSHOPPING_Db->query("SHOW INDEX FROM {$fullTableName}");
      while ($index = $indexes->fetch()) {
        $validation['indexes'][] = $index['Key_name'];
      }

      // Get record count
      $countResult = $CLICSHOPPING_Db->query("SELECT COUNT(*) as cnt FROM {$fullTableName}");
      $countRow = $countResult->fetch();
      $validation['record_count'] = (int)($countRow['cnt'] ?? 0);

      // Overall validation status
      $validation['valid'] =
        $validation['exists'] &&
        $validation['has_taxonomy_column'] &&
        $validation['has_metadata_column'] &&
        $validation['has_content_column'] &&
        $validation['has_embedding_column'];

    } catch (\Exception $e) {
      $validation['errors'][] = $e->getMessage();
      $validation['valid'] = false;
    }

    return $validation;
  }
}