<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Sql\MariaDb;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class MariaDb
{
  /**
   * Executes the installation process for the Ecommerce module.
   * This method loads necessary definitions and initializes the database setup.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Ecommerce = Registry::get('Ecommerce');
    $CLICSHOPPING_Ecommerce->loadDefinitions('Sites/ClicShoppingAdmin/install');

    self::installDbMenuAdministration();
    self::installDb();
  }

  /**
   * Installs the database entries for the administration menu related to the Page Manager module.
   *
   * This method checks if the required menu entry exists in the 'administrator_menu' table.
   * If the entry does not exist, it creates a new entry with its corresponding metadata and language-specific descriptions.
   * Once the entries are added, it clears the administrator menu cache to ensure the changes are applied.
   *
   * @return void
   */
  private static function installDbMenuAdministration(): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Ecommerce = Registry::get('Ecommerce');
    $CLICSHOPPING_Language = Registry::get('Language');

    $Qcheck = $CLICSHOPPING_Db->get('administrator_menu', 'app_code', ['app_code' => 'app_ai_ecommerce']);

    if ($Qcheck->fetch() === false) {
      $sql_data_array = ['sort_order' => 0,
        'link' => 'index.php?A&AI\Ecommerce&Ecommerce',
        'image' => '',
        'b2b_menu' => 0,
        'access' => 0,
        'app_code' => 'app_ai_ecommerce'
      ];

      $insert_sql_data = ['parent_id' => 6];
      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $CLICSHOPPING_Db->save('administrator_menu', $sql_data_array);

      $id = $CLICSHOPPING_Db->lastInsertId();
      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $language_id = $languages[$i]['id'];
        $sql_data_array = ['label' => $CLICSHOPPING_Ecommerce->getDef('title_menu')];

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
   * Installs the necessary database tables required for the Page Manager module if they do not already exist.
   *
   * @return void
   */
  private static function installDb()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

     $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_categories_embedding"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
      CREATE TABLE IF NOT EXISTS :table_categories_embedding (
        `id` int(11) NOT NULL auto_increment COMMENT 'Primary key - unique identifier for each embedding chunk',
        `content` text DEFAULT NULL COMMENT 'Text content that was embedded - original text from category',
        `type` text DEFAULT NULL COMMENT 'Type of content - description, name, or metadata',
        `sourcetype` text DEFAULT 'manual' COMMENT 'Source type - manual, auto, or import',
        `sourcename` text DEFAULT 'manual' COMMENT 'Source name - identifies the origin of the content',
        `embedding` vector(3072) NOT NULL COMMENT 'Vector embedding with 3072 dimensions - OpenAI text-embedding-3-large format',
        `chunknumber` int(11) DEFAULT 128 COMMENT 'Chunk number for splitting large content - default 128 tokens per chunk',
        `date_modified` datetime DEFAULT NULL COMMENT 'Timestamp of last modification to this embedding',
        `created_at` timestamp DEFAULT current_timestamp COMMENT 'Creation timestamp - immutable, auto-set on insert',
        `entity_id` int(11) NOT NULL COMMENT 'FK to categories table - references the category this embedding represents',
        `language_id` int(11) NOT NULL COMMENT 'FK to languages table - language of the embedded content',
        `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional metadata about the embedding - structure varies by type' CHECK (json_valid(`metadata`)),
        PRIMARY KEY (`id`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_entity_id` (`entity_id`),
        KEY `idx_language_id` (`language_id`),
        KEY `idx_entity_lang` (`entity_id`, `language_id`),
        KEY `idx_date_modified` (`date_modified`),
        VECTOR KEY `embedding_index` (`embedding`)
      ) ENGINE innodb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
      CREATE TABLE IF NOT EXISTS :table_products_embedding (
        `id` int(11) NOT NULL auto_increment COMMENT 'Primary key - unique identifier for each embedding chunk',
        `content` text DEFAULT NULL COMMENT 'Text content that was embedded - original text from product',
        `type` text DEFAULT NULL COMMENT 'Type of content - description, name, attributes, or specifications',
        `sourcetype` text DEFAULT 'manual' COMMENT 'Source type - manual, auto, or import',
        `sourcename` text DEFAULT 'manual' COMMENT 'Source name - identifies the origin of the content',
        `embedding` vector(3072) NOT NULL COMMENT 'Vector embedding with 3072 dimensions - OpenAI text-embedding-3-large format',
        `chunknumber` int(11) DEFAULT 128 COMMENT 'Chunk number for splitting large content - default 128 tokens per chunk',
        `date_modified` datetime DEFAULT NULL COMMENT 'Timestamp of last modification to this embedding',
        `created_at` timestamp DEFAULT current_timestamp COMMENT 'Creation timestamp - immutable, auto-set on insert',
        `entity_id` int(11) NOT NULL COMMENT 'FK to products table - references the product this embedding represents',
        `language_id` int(11) NOT NULL COMMENT 'FK to languages table - language of the embedded content',
        `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional metadata about the embedding - may include price, category, manufacturer info' CHECK (json_valid(`metadata`)),
        PRIMARY KEY (`id`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_entity_id` (`entity_id`),
        KEY `idx_language_id` (`language_id`),
        KEY `idx_entity_lang` (`entity_id`, `language_id`),
        KEY `idx_date_modified` (`date_modified`),
        VECTOR KEY `embedding_index` (`embedding`)
      ) ENGINE innodb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
      CREATE TABLE IF NOT EXISTS :table_manufacturers_embedding (
        `id` int(11) NOT NULL auto_increment COMMENT 'Primary key - unique identifier for each embedding chunk',
        `content` text DEFAULT NULL COMMENT 'Text content that was embedded - original text from manufacturer',
        `type` text DEFAULT NULL COMMENT 'Type of content - description, name, or info',
        `sourcetype` text DEFAULT 'manual' COMMENT 'Source type - manual, auto, or import',
        `sourcename` text DEFAULT 'manual' COMMENT 'Source name - identifies the origin of the content',
        `embedding` vector(3072) NOT NULL COMMENT 'Vector embedding with 3072 dimensions - OpenAI text-embedding-3-large format',
        `chunknumber` int(11) DEFAULT 128 COMMENT 'Chunk number for splitting large content - default 128 tokens per chunk',
        `date_modified` datetime DEFAULT NULL COMMENT 'Timestamp of last modification to this embedding',
        `created_at` timestamp DEFAULT current_timestamp COMMENT 'Creation timestamp - immutable, auto-set on insert',
        `entity_id` int(11) NOT NULL COMMENT 'FK to manufacturers table - references the manufacturer this embedding represents',
        `language_id` int(11) NOT NULL COMMENT 'FK to languages table - language of the embedded content',
        `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional metadata about the embedding - may include product count, supplier info' CHECK (json_valid(`metadata`)),
        PRIMARY KEY (`id`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_entity_id` (`entity_id`),
        KEY `idx_language_id` (`language_id`),
        KEY `idx_entity_lang` (`entity_id`, `language_id`),
        KEY `idx_date_modified` (`date_modified`),
        VECTOR KEY `embedding_index` (`embedding`)
      ) ENGINE innodb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
      CREATE TABLE IF NOT EXISTS :table_suppliers_embedding (
        `id` int(11) NOT NULL auto_increment COMMENT 'Primary key - unique identifier for each embedding chunk',
        `content` text DEFAULT NULL COMMENT 'Text content that was embedded - original text from supplier',
        `type` text DEFAULT NULL COMMENT 'Type of content - description, name, address, or contact info',
        `sourcetype` text DEFAULT 'manual' COMMENT 'Source type - manual, auto, or import',
        `sourcename` text DEFAULT 'manual' COMMENT 'Source name - identifies the origin of the content',
        `embedding` vector(3072) NOT NULL COMMENT 'Vector embedding with 3072 dimensions - OpenAI text-embedding-3-large format',
        `chunknumber` int(11) DEFAULT 128 COMMENT 'Chunk number for splitting large content - default 128 tokens per chunk',
        `date_modified` datetime DEFAULT NULL COMMENT 'Timestamp of last modification to this embedding',
        `created_at` timestamp DEFAULT current_timestamp COMMENT 'Creation timestamp - immutable, auto-set on insert',
        `entity_id` int(11) NOT NULL COMMENT 'FK to suppliers table - references the supplier this embedding represents',
        `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional metadata about the embedding - may include product count, contact details' CHECK (json_valid(`metadata`)),
        PRIMARY KEY (`id`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_entity_id` (`entity_id`),
        KEY `idx_date_modified` (`date_modified`),
        VECTOR KEY `embedding_index` (`embedding`)
      ) ENGINE innodb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
      CREATE TABLE IF NOT EXISTS :table_reviews_embedding (
        `id` int(11) NOT NULL auto_increment COMMENT 'Primary key - unique identifier for each embedding chunk',
        `content` text DEFAULT NULL COMMENT 'Text content that was embedded - original review text',
        `type` text DEFAULT NULL COMMENT 'Type of content - review_text, review_summary, or sentiment',
        `sourcetype` text DEFAULT 'manual' COMMENT 'Source type - manual, auto, or import',
        `sourcename` text DEFAULT 'manual' COMMENT 'Source name - identifies the origin of the content',
        `embedding` vector(3072) NOT NULL COMMENT 'Vector embedding with 3072 dimensions - OpenAI text-embedding-3-large format',
        `chunknumber` int(11) DEFAULT 128 COMMENT 'Chunk number for splitting large content - default 128 tokens per chunk',
        `date_modified` datetime DEFAULT NULL COMMENT 'Timestamp of last modification to this embedding',
        `created_at` timestamp DEFAULT current_timestamp COMMENT 'Creation timestamp - immutable, auto-set on insert',
        `entity_id` int(11) NOT NULL COMMENT 'FK to reviews table - references the review this embedding represents',
        `language_id` int(11) NOT NULL COMMENT 'FK to languages table - language of the embedded content',
        `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional metadata about the embedding - may include rating, product_id, customer_id' CHECK (json_valid(`metadata`)),
        PRIMARY KEY (`id`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_entity_id` (`entity_id`),
        KEY `idx_language_id` (`language_id`),
        KEY `idx_entity_lang` (`entity_id`, `language_id`),
        KEY `idx_date_modified` (`date_modified`),
        VECTOR KEY `embedding_index` (`embedding`)
      ) ENGINE innodb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
      CREATE TABLE IF NOT EXISTS :table_reviews_sentiment_embedding (
        `id` int(11) NOT NULL auto_increment COMMENT 'Primary key - unique identifier for each embedding chunk',
        `content` text DEFAULT NULL COMMENT 'Text content that was embedded - sentiment analysis text from review',
        `type` text DEFAULT NULL COMMENT 'Type of content - sentiment_positive, sentiment_negative, or sentiment_neutral',
        `sourcetype` text DEFAULT 'manual' COMMENT 'Source type - manual, auto, or import',
        `sourcename` text DEFAULT 'manual' COMMENT 'Source name - identifies the origin of the content',
        `embedding` vector(3072) NOT NULL COMMENT 'Vector embedding with 3072 dimensions - OpenAI text-embedding-3-large format',
        `chunknumber` int(11) DEFAULT 128 COMMENT 'Chunk number for splitting large content - default 128 tokens per chunk',
        `date_modified` datetime DEFAULT NULL COMMENT 'Timestamp of last modification to this embedding',
        `created_at` timestamp DEFAULT current_timestamp COMMENT 'Creation timestamp - immutable, auto-set on insert',
        `entity_id` int(11) NOT NULL COMMENT 'FK to reviews_sentiment table - references the sentiment analysis this embedding represents',
        `language_id` int(11) NOT NULL COMMENT 'FK to languages table - language of the embedded content',
        `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional metadata about the embedding - may include sentiment_score, review_id, product_id' CHECK (json_valid(`metadata`)),
        PRIMARY KEY (`id`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_entity_id` (`entity_id`),
        KEY `idx_language_id` (`language_id`),
        KEY `idx_entity_lang` (`entity_id`, `language_id`),
        KEY `idx_date_modified` (`date_modified`),
        VECTOR KEY `embedding_index` (`embedding`)
      ) ENGINE innodb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
      CREATE TABLE IF NOT EXISTS :table_return_orders_embedding (
        `id` int(11) NOT NULL auto_increment COMMENT 'Primary key - unique identifier for each embedding chunk',
        `content` text DEFAULT NULL COMMENT 'Text content that was embedded - return order details, reason, action',
        `type` text DEFAULT NULL COMMENT 'Type of content - return_reason, return_action, or customer_notes',
        `sourcetype` text DEFAULT 'manual' COMMENT 'Source type - manual, auto, or import',
        `sourcename` text DEFAULT 'manual' COMMENT 'Source name - identifies the origin of the content',
        `embedding` vector(3072) NOT NULL COMMENT 'Vector embedding with 3072 dimensions - OpenAI text-embedding-3-large format',
        `chunknumber` int(11) DEFAULT 128 COMMENT 'Chunk number for splitting large content - default 128 tokens per chunk',
        `date_modified` datetime DEFAULT NULL COMMENT 'Timestamp of last modification to this embedding',
        `created_at` timestamp DEFAULT current_timestamp COMMENT 'Creation timestamp - immutable, auto-set on insert',
        `entity_id` int(11) NOT NULL COMMENT 'FK to return_orders table - references the return order this embedding represents',
        `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional metadata about the embedding - may include return_status, order_id, product_id' CHECK (json_valid(`metadata`)),
        PRIMARY KEY (`id`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_entity_id` (`entity_id`),
        KEY `idx_date_modified` (`date_modified`),
        VECTOR KEY `embedding_index` (`embedding`)
      ) ENGINE innodb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
      CREATE TABLE IF NOT EXISTS :table_orders_embedding (
        `id` int(11) NOT NULL auto_increment COMMENT 'Primary key - unique identifier for each embedding chunk',
        `content` text DEFAULT NULL COMMENT 'Text content that was embedded - order details, products, customer info',
        `type` text DEFAULT NULL COMMENT 'Type of content - order_summary, products_list, or customer_notes',
        `sourcetype` text DEFAULT 'manual' COMMENT 'Source type - manual, auto, or import',
        `sourcename` text DEFAULT 'manual' COMMENT 'Source name - identifies the origin of the content',
        `embedding` vector(3072) NOT NULL COMMENT 'Vector embedding with 3072 dimensions - OpenAI text-embedding-3-large format',
        `chunknumber` int(11) DEFAULT 128 COMMENT 'Chunk number for splitting large content - default 128 tokens per chunk',
        `date_modified` datetime DEFAULT NULL COMMENT 'Timestamp of last modification to this embedding',
        `created_at` timestamp DEFAULT current_timestamp COMMENT 'Creation timestamp - immutable, auto-set on insert',
        `entity_id` int(11) NOT NULL COMMENT 'FK to orders table - references the order this embedding represents',
        `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional metadata about the embedding - may include order_status, total, customer_id' CHECK (json_valid(`metadata`)),
        PRIMARY KEY (`id`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_entity_id` (`entity_id`),
        KEY `idx_date_modified` (`date_modified`),
        VECTOR KEY `embedding_index` (`embedding`)
      ) ENGINE innodb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    // Create embeddings table for order insights
    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_rag_agent_order_insights_embedding"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
        CREATE TABLE IF NOT EXISTS :table_rag_agent_order_insights_embedding (
          `id` bigint(20) unsigned NOT NULL auto_increment COMMENT 'Primary key - unique identifier for each insight embedding',
          `content` longtext DEFAULT NULL COMMENT 'Insight content for embedding generation - summary, recommendations, analysis',
          `type` text DEFAULT NULL COMMENT 'Type of content - summary, recommendations, full_insights',
          `sourcetype` text DEFAULT 'automated' COMMENT 'Source type - automated (from LLM), manual, imported',
          `sourcename` text DEFAULT 'insights_agent' COMMENT 'Name of the source system or process',
          `embedding` vector(3072) NOT NULL COMMENT 'Vector embedding (3072 dimensions) for semantic search',
          `chunknumber` int(11) DEFAULT 128 COMMENT 'Chunk size used for embedding generation',
          `date_modified` datetime DEFAULT NULL COMMENT 'Last modification timestamp',
          `created_at` timestamp DEFAULT current_timestamp COMMENT 'Creation timestamp - immutable, auto-set on insert',
          `entity_id` int(11) DEFAULT NULL COMMENT 'FK to rag_agent_order_insights table - insight ID',
          `metadata` longtext DEFAULT NULL COMMENT 'JSON metadata for the embedding',
          `language_id` int(11) DEFAULT NULL COMMENT 'Language ID from languages table',
          PRIMARY KEY (`id`),
          KEY `idx_entity_id` (`entity_id`),
          KEY `idx_language_id` (`language_id`),
          KEY `idx_date_modified` (`date_modified`),
          KEY `idx_created_at` (`created_at`),
          VECTOR KEY `embedding_index` (`embedding`)
        ) ENGINE innodb CHARACTER SET utf8mb4 COLLATE utf8mb4_uca1400_ai_ci COMMENT='Vector embeddings for order insights - enables semantic insight search and pattern analysis across orders';
        EOD;
      $CLICSHOPPING_Db->exec($sql);

    }

    // Create products_seo_embedding table
    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_products_seo_embedding"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
      CREATE TABLE IF NOT EXISTS :table_products_seo_embedding (
        `id` int(11) NOT NULL auto_increment COMMENT 'Primary key - unique identifier for each SEO embedding chunk',
        `content` text DEFAULT NULL COMMENT 'Text content embedded - serialized SEO report data (title, meta, H1-H3, keywords, scores)',
        `type` text DEFAULT NULL COMMENT 'Type of SEO content: initial_report | optimized_report | audit_summary | suggestion',
        `sourcetype` text DEFAULT NULL COMMENT 'Trigger origin: manual | hook | cron',
        `sourcename` text DEFAULT NULL COMMENT 'Source identifier: SeoReport | AgentSeo | AgentAuditSeo | AgentSerp',
        `embedding` vector(3072) NOT NULL COMMENT 'Vector embedding 3072 dimensions - OpenAI text-embedding-3-large',
        `chunknumber` int(11) DEFAULT 128 COMMENT 'Chunk number for large reports - default 128 tokens per chunk',
        `date_modified` datetime DEFAULT NULL COMMENT 'Timestamp of last modification',
        `created_at` timestamp DEFAULT current_timestamp COMMENT 'Creation timestamp - immutable, auto-set on insert',
        `entity_id` int(11) NOT NULL COMMENT 'FK - references the entity (category, product, cms page)',
        `entity_type` varchar(50) DEFAULT NULL COMMENT 'Entity type: category | product | cms',
        `language_id` int(11) NOT NULL COMMENT 'FK to languages table',
        `metadata` longtext DEFAULT NULL COMMENT 'JSON: url, page_type, seo_score_before, seo_score_after, status, report_raw, suggestions, audit_result, serp_data',
        PRIMARY KEY (`id`),
        KEY `idx_entity_lang` (`entity_id`, `language_id`),
        KEY `idx_type` (`type`(50)),
        KEY `idx_sourcetype` (`sourcetype`(50)),
        KEY `idx_date_modified` (`date_modified`),
        KEY `idx_created_at` (`created_at`)
      ) ENGINE innodb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    // Create products_seo_embedding table
    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_categories_seo_embedding"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
      CREATE TABLE IF NOT EXISTS :table_categories_seo_embedding (
        `id` int(11) NOT NULL auto_increment COMMENT 'Primary key - unique identifier for each SEO embedding chunk',
        `content` text DEFAULT NULL COMMENT 'Text content embedded - serialized SEO report data (title, meta, H1-H3, keywords, scores)',
        `type` text DEFAULT NULL COMMENT 'Type of SEO content: initial_report | optimized_report | audit_summary | suggestion',
        `sourcetype` text DEFAULT NULL COMMENT 'Trigger origin: manual | hook | cron',
        `sourcename` text DEFAULT NULL COMMENT 'Source identifier: SeoReport | AgentSeo | AgentAuditSeo | AgentSerp',
        `embedding` vector(3072) NOT NULL COMMENT 'Vector embedding 3072 dimensions - OpenAI text-embedding-3-large',
        `chunknumber` int(11) DEFAULT 128 COMMENT 'Chunk number for large reports - default 128 tokens per chunk',
        `date_modified` datetime DEFAULT NULL COMMENT 'Timestamp of last modification',
        `created_at` timestamp DEFAULT current_timestamp COMMENT 'Creation timestamp - immutable, auto-set on insert',
        `entity_id` int(11) NOT NULL COMMENT 'FK - references the entity (category, product, cms page)',
        `entity_type` varchar(50) DEFAULT NULL COMMENT 'Entity type: category | product | cms',
        `language_id` int(11) NOT NULL COMMENT 'FK to languages table',
        `metadata` longtext DEFAULT NULL COMMENT 'JSON: url, page_type, seo_score_before, seo_score_after, status, report_raw, suggestions, audit_result, serp_data',
        PRIMARY KEY (`id`),
        KEY `idx_entity_lang` (`entity_id`, `language_id`),
        KEY `idx_type` (`type`(50)),
        KEY `idx_sourcetype` (`sourcetype`(50)),
        KEY `idx_date_modified` (`date_modified`),
        KEY `idx_created_at` (`created_at`)
      ) ENGINE innodb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    // Create CockpitAI embedding table
    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_products_cockpit_ai_embedding"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
        CREATE TABLE IF NOT EXISTS :table_products_cockpit_ai_embedding (
          `id` int(11) NOT NULL auto_increment COMMENT 'Primary key - unique identifier for each CockpitAI analysis embedding',
          `content` text DEFAULT NULL COMMENT 'Generated from metadata using normalized template v1.0',
          `type` enum('score_product','score_commercial','analysis','action_plan','history') DEFAULT NULL COMMENT 'Type of analysis content',
          `sourcetype` enum('manual','auto') DEFAULT NULL COMMENT 'Trigger origin: manual (merchant) | auto (MCP/hook - future)',
          `sourcename` text DEFAULT NULL COMMENT 'Source identifier: merchant username | system component',
          `chunknumber` int(11) DEFAULT 128 COMMENT 'Chunk size for embedding generation',
          `embedding` vector(3072) NOT NULL COMMENT 'Vector embedding 3072 dimensions - OpenAI text-embedding-3-large',
          `date_modified` datetime DEFAULT NULL COMMENT 'Timestamp of analysis generation',
          `created_at` timestamp DEFAULT current_timestamp COMMENT 'Creation timestamp - immutable, auto-set on insert',
          `entity_id` int(11) NOT NULL COMMENT 'FK to products table - product ID',
          `entity_type` varchar(50) DEFAULT NULL COMMENT 'Entity type (product, category, etc.)',
          `language_id` int(11) NOT NULL COMMENT 'FK to languages table - language identifier',
          `metadata` longtext DEFAULT NULL COMMENT 'JSON structure with versioned analysis details (scores, factors, actions, history)',
          PRIMARY KEY (`id`),
          KEY `idx_entity_id` (`entity_id`),
          KEY `idx_date_modified` (`date_modified`),
          KEY `idx_entity_date` (`entity_id`, `date_modified`),
          KEY `idx_created_at` (`created_at`)
        ) ENGINE innodb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT='Cockpit IA strategic product analysis embeddings - dual-axis scoring with RAG context';
        EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    // Create products_cockpit_ai_tracking_impressions_summary table view
    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_products_cockpit_ai_tracking_impressions_summary"');

    if ($Qcheck->fetch() === false) {
    #CREATE ALGORITHM=UNDEFINED DEFINER=root@localhost SQL SECURITY INVOKER VIEW clic_products_cockpit_ai_tracking_impressions_summary  AS SELECT clic_products_cockpit_ai_tracking_impressions.products_id AS `products_id`, clic_products_cockpit_ai_tracking_impressions.language_id AS `language_id`, sum(clic_products_cockpit_ai_tracking_impressions.weight * exp(-timestampdiff(HOUR,clic_products_cockpit_ai_tracking_impressions.displayed_at,current_timestamp()) / 48)) / (1 + log(count(0) + 1)) AS `popularity_heat`, count(0) AS `total_impressions`, count(distinct clic_products_cockpit_ai_tracking_impressions.module_code) AS `module_spread`, sum(case when clic_products_cockpit_ai_tracking_impressions.weight >= 0.5 then 1 else 0 end) / nullif(count(0),0) AS `high_intent_ratio`, std(clic_products_cockpit_ai_tracking_impressions.weight) AS `weight_stddev`, max(clic_products_cockpit_ai_tracking_impressions.displayed_at) AS `last_seen_at` FROM clic_products_cockpit_ai_tracking_impressions WHERE clic_products_cockpit_ai_tracking_impressions.displayed_at >= current_timestamp() - interval 7 day GROUP BY clic_products_cockpit_ai_tracking_impressions.products_id, clic_products_cockpit_ai_tracking_impressions.language_id ;

      $sql = <<<EOD
      CREATE OR REPLACE VIEW :table_products_cockpit_ai_tracking_impressions_summary AS
        SELECT
          products_id,
          language_id,
          SUM(weight * EXP(-TIMESTAMPDIFF(HOUR, displayed_at, CURRENT_TIMESTAMP()) / 48)) / (1 + LOG(COUNT(*) + 1)) AS popularity_heat,
          COUNT(*) AS total_impressions,
          COUNT(DISTINCT module_code) AS module_spread,
          SUM(CASE WHEN weight >= 0.5 THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0) AS high_intent_ratio,
          STD(weight) AS weight_stddev,
          MAX(displayed_at) AS last_seen_at
        FROM :table_products_cockpit_ai_tracking_impressions
        WHERE displayed_at >= CURRENT_TIMESTAMP() - INTERVAL 7 DAY
        GROUP BY products_id, language_id;
      EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

  //--------------------------------------
  // product description faq embedding Schema Embeddings
  //--------------------------------------

  $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_products_description_faq_embedding"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
      CREATE TABLE IF NOT EXISTS :table_products_description_faq_embedding (
        `id` int(11) NOT NULL auto_increment COMMENT 'Primary key',
        `content` text DEFAULT NULL COMMENT 'Text content that was embedded',
        `type` text DEFAULT NULL COMMENT 'Content type - always "faq"',
        `sourcetype` text DEFAULT NULL COMMENT 'Source type',
        `sourcename` text DEFAULT NULL COMMENT 'Source name',
        `embedding` vector(3072) NOT NULL COMMENT 'Vector embedding - 3072 dimensions',
        `chunknumber` int(11) DEFAULT 128 COMMENT 'Chunk size in tokens',
        `date_modified` datetime DEFAULT current_timestamp ON UPDATE current_timestamp COMMENT 'Last modification timestamp',
        `entity_id` int(11) NOT NULL COMMENT 'Reference to products_id',
        `language_id` int(11) NOT NULL COMMENT 'Reference to languages_id',
        `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON metadata' CHECK (json_valid(`metadata`)),
        `taxonomy` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Structured taxonomy metadata' CHECK (json_valid(`taxonomy`)),
        `created_at` timestamp DEFAULT current_timestamp COMMENT 'Creation timestamp',
        PRIMARY KEY (`id`),
        KEY `idx_entity_id` (`entity_id`),
        KEY `idx_language_id` (`language_id`),
        VECTOR KEY `embedding_index` (`embedding`)
      ) ENGINE innodb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

    EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

  //--------------------------------------
  // CockpitAI action log (canonical form: Schema/MariaDb/products_cockpit_ai_action_log.txt)
  //--------------------------------------
    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_products_cockpit_ai_action_log"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
        CREATE TABLE IF NOT EXISTS :table_products_cockpit_ai_action_log (
          `log_id` INT(11) unsigned NOT NULL auto_increment COMMENT 'Primary key - unique log entry identifier',
          `product_id` INT(11) unsigned NOT NULL COMMENT 'Foreign key - references clic_products.products_id',
          `language_id` INT(11) unsigned DEFAULT null COMMENT 'Language context for the action - NULL when the action is not language-specific (refresh flag)',
          `action_type` ENUM('featured','favorites','specials','system_update_flag') NOT NULL COMMENT 'Type of action: marketing action applied to the product, or system_update_flag when an admin edit requests a fresh analysis',
          `revocation_token` VARCHAR(64) DEFAULT null COMMENT 'Token used to cancel/revoke the action if needed',
          `action_code` VARCHAR(50) DEFAULT null COMMENT 'Unique code identifying the specific action instance',
          `action_subtype` ENUM('insert','delete','update') NOT NULL COMMENT 'Operation performed: insert=new action, delete=removed, update=modified',
          `special_price` DECIMAL(15,4) DEFAULT null COMMENT 'Promotional price applied to the product (used when action_type=specials)',
          `margin_rate_applied` DECIMAL(5,2) DEFAULT null COMMENT 'Margin rate (%) used to calculate the special price',
          `promotion_step` TINYINT(3) unsigned DEFAULT null COMMENT 'Step number in a multi-stage promotion sequence',
          `status` ENUM('executed','skipped','pending_admin','no_action','failed') DEFAULT 'no_action' COMMENT 'Execution status: executed=done, skipped=conditions not met, pending_admin=awaiting approval, no_action=nothing done, failed=error',
          `triggered_by` VARCHAR(50) DEFAULT 'auto' COMMENT 'Who triggered the action: auto=system, or user identifier',
          `user_id` INT(11) unsigned DEFAULT null COMMENT 'Foreign key - admin user who manually triggered the action (NULL if auto)',
          `score_x_at_trigger` DECIMAL(5,2) DEFAULT null COMMENT 'X-axis score (e.g. sales performance) at the moment the action was triggered',
          `score_y_at_trigger` DECIMAL(5,2) DEFAULT null COMMENT 'Y-axis score (e.g. margin/visibility) at the moment the action was triggered',
          `quadrant_at_trigger` VARCHAR(20) DEFAULT null COMMENT 'Matrix quadrant position of the product when action was triggered (e.g. Q1, Q2, star, dog)',
          `validation_reason` VARCHAR(500) DEFAULT null COMMENT 'Explanation of why the action was validated or skipped',
          `input_scores` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT null COMMENT 'JSON snapshot of all X/Y scores at decision time' CHECK (json_valid(input_scores)),
          `cancel_token` VARCHAR(64) DEFAULT null COMMENT 'Token required to cancel a pending or executed action',
          `cancel_token_expires` DATETIME DEFAULT null COMMENT 'Expiry date/time of the cancel_token - after this date cancellation is no longer possible',
          `date_created` DATETIME NOT NULL COMMENT 'Timestamp when the log entry was created',
          `date_cancelled` DATETIME DEFAULT null COMMENT 'Timestamp when the action was cancelled (NULL if not cancelled)',
          `score_y_after` DECIMAL(5,2) DEFAULT null COMMENT 'Y-axis score measured N days after the action - used to evaluate action effectiveness',
          `feedback_collected_at` DATETIME DEFAULT null COMMENT 'Timestamp when post-action feedback/measurement was collected',
          `conversion_velocity` DECIMAL(8,4) DEFAULT null COMMENT 'Speed of conversion change after the action (sales rate delta per day)',
          `trigger_strategy` VARCHAR(50) DEFAULT 'standard' COMMENT 'Strategy used to trigger the action: standard=default rules, or custom strategy name',
          PRIMARY KEY (`log_id`),
          KEY `idx_cockpit_ia_action_products` (`product_id`),
          KEY `idx_revocation_token` (`revocation_token`),
          KEY `idx_action_type_status` (`action_type`, `status`)
        ) ENGINE innodb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
      EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
  }
  
}
