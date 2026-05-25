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
        id SERIAL PRIMARY KEY COMMENT 'Primary key - unique identifier for each category embedding',
          content longtext DEFAULT NULL COMMENT 'Category content for embedding generation - description, metadata, etc.',
          type text DEFAULT NULL COMMENT 'Type of content - description, metadata, full_text',
          sourcetype text default 'manual' COMMENT 'Source type - manual, automated, imported',
          sourcename text default 'manual' COMMENT 'Name of the source system or process',
          embedding vector(3072) NOT NULL COMMENT 'Vector embedding (3072 dimensions) for semantic search',
          chunknumber int default 128 COMMENT 'Chunk size used for embedding generation',
          date_modified datetime DEFAULT NULL COMMENT 'Last modification timestamp',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp - immutable, auto-set on insert',
          entity_id INT COMMENT 'FK to categories table - category ID',
          language_id INT COMMENT 'FK to languages table - language identifier',
          metadata longtext DEFAULT NULL COMMENT 'Additional metadata about the embedding - structure varies by type',
          KEY idx_entity_id (entity_id),
          KEY idx_language_id (language_id),
          KEY idx_entity_lang (entity_id, language_id),
          KEY idx_date_modified (date_modified),
          KEY idx_created_at (created_at)
      ) COMMENT='Vector embeddings for categories - enables semantic category search and recommendations';

      CREATE VECTOR INDEX embedding_index ON :table_categories_embedding (embedding);

       CREATE TABLE IF NOT EXISTS :table_products_embedding (
          id SERIAL PRIMARY KEY COMMENT 'Primary key - unique identifier for each product embedding',
          content longtext DEFAULT NULL COMMENT 'Product content for embedding generation - name, description, specifications, etc.',
          type text DEFAULT NULL COMMENT 'Type of content - description, specifications, reviews, full_text',
          sourcetype text default 'manual' COMMENT 'Source type - manual, automated, imported',
          sourcename text default 'manual' COMMENT 'Name of the source system or process',
          embedding vector(3072) NOT NULL COMMENT 'Vector embedding (3072 dimensions) for semantic search',
          chunknumber int default 128 COMMENT 'Chunk size used for embedding generation',
          date_modified datetime DEFAULT NULL COMMENT 'Last modification timestamp',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp - immutable, auto-set on insert',
          entity_id INT COMMENT 'FK to products table - product ID',
          language_id INT COMMENT 'FK to languages table - language identifier',
          metadata longtext DEFAULT NULL COMMENT 'Additional metadata about the embedding - may include price, category, manufacturer info',
          KEY idx_entity_id (entity_id),
          KEY idx_language_id (language_id),
          KEY idx_entity_lang (entity_id, language_id),
          KEY idx_date_modified (date_modified),
          KEY idx_created_at (created_at)
        ) COMMENT='Vector embeddings for products - enables semantic product search and recommendations';

      CREATE VECTOR INDEX embedding_index ON :table_products_embedding (embedding);
      
      CREATE TABLE IF NOT EXISTS :table_manufacturers_embedding (
          id SERIAL PRIMARY KEY COMMENT 'Primary key - unique identifier for each manufacturer embedding',
          content longtext DEFAULT NULL COMMENT 'Manufacturer content for embedding generation - name, description, history, etc.',
          type TEXT DEFAULT NULL COMMENT 'Type of content - description, history, metadata, full_text',
          sourcetype TEXT DEFAULT 'manual' COMMENT 'Source type - manual, automated, imported',
          sourcename TEXT DEFAULT 'manual' COMMENT 'Name of the source system or process',
          embedding VECTOR(3072) NOT NULL COMMENT 'Vector embedding (3072 dimensions) for semantic search',
          chunknumber INT DEFAULT 128 COMMENT 'Chunk size used for embedding generation',
          date_modified DATETIME DEFAULT NULL COMMENT 'Last modification timestamp',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp - immutable, auto-set on insert',
          entity_id INT COMMENT 'FK to manufacturers table - manufacturer ID',
          language_id INT COMMENT 'FK to languages table - language identifier',
          metadata longtext DEFAULT NULL COMMENT 'Additional metadata about the embedding - may include product count, supplier info',
          KEY idx_entity_id (entity_id),
          KEY idx_language_id (language_id),
          KEY idx_entity_lang (entity_id, language_id),
          KEY idx_date_modified (date_modified),
          KEY idx_created_at (created_at)
      ) COMMENT='Vector embeddings for manufacturers - enables semantic manufacturer search and brand discovery';

      CREATE VECTOR INDEX embedding_index ON :table_manufacturers_embedding (embedding);

      CREATE TABLE IF NOT EXISTS :table_suppliers_embedding (
          id SERIAL PRIMARY KEY COMMENT 'Primary key - unique identifier for each supplier embedding',
          content longtext DEFAULT NULL COMMENT 'Supplier content for embedding generation - name, description, capabilities, etc.',
          type TEXT DEFAULT NULL COMMENT 'Type of content - description, capabilities, metadata, full_text',
          sourcetype TEXT DEFAULT 'manual' COMMENT 'Source type - manual, automated, imported',
          sourcename TEXT DEFAULT 'manual' COMMENT 'Name of the source system or process',
          embedding VECTOR(3072) NOT NULL COMMENT 'Vector embedding (3072 dimensions) for semantic search',
          chunknumber INT DEFAULT 128 COMMENT 'Chunk size used for embedding generation',
          date_modified DATETIME DEFAULT NULL COMMENT 'Last modification timestamp',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp - immutable, auto-set on insert',
          entity_id INT COMMENT 'FK to suppliers table - supplier ID',
          metadata longtext DEFAULT NULL COMMENT 'Additional metadata about the embedding - may include product count, contact details',
          KEY idx_entity_id (entity_id),
          KEY idx_date_modified (date_modified),
          KEY idx_created_at (created_at)
      ) COMMENT='Vector embeddings for suppliers - enables semantic supplier search and sourcing recommendations';

      CREATE VECTOR INDEX embedding_index ON :table_suppliers_embedding (embedding);

      CREATE TABLE IF NOT EXISTS :table_reviews_embedding (
          id SERIAL PRIMARY KEY COMMENT 'Primary key - unique identifier for each review embedding',
          content longtext DEFAULT NULL COMMENT 'Review content for embedding generation - review text, title, etc.',
          type TEXT DEFAULT NULL COMMENT 'Type of content - review_text, summary, full_text',
          sourcetype TEXT DEFAULT 'manual' COMMENT 'Source type - manual, automated, imported',
          sourcename TEXT DEFAULT 'manual' COMMENT 'Name of the source system or process',
          embedding VECTOR(3072) NOT NULL COMMENT 'Vector embedding (3072 dimensions) for semantic search',
          chunknumber INT DEFAULT 128 COMMENT 'Chunk size used for embedding generation',
          date_modified DATETIME DEFAULT NULL COMMENT 'Last modification timestamp',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp - immutable, auto-set on insert',
          entity_id INT COMMENT 'FK to reviews table - review ID',
          language_id INT COMMENT 'FK to languages table - language identifier',
          metadata longtext DEFAULT NULL COMMENT 'Additional metadata about the embedding - may include rating, product_id, customer_id',
          KEY idx_entity_id (entity_id),
          KEY idx_language_id (language_id),
          KEY idx_entity_lang (entity_id, language_id),
          KEY idx_date_modified (date_modified),
          KEY idx_created_at (created_at)
      ) COMMENT='Vector embeddings for product reviews - enables semantic review search and sentiment analysis';

      CREATE VECTOR INDEX embedding_index ON :table_reviews_embedding (embedding);

      CREATE TABLE IF NOT EXISTS :table_reviews_sentiment_embedding (
          id SERIAL PRIMARY KEY COMMENT 'Primary key - unique identifier for each sentiment embedding',
          content longtext DEFAULT NULL COMMENT 'Sentiment-analyzed review content with emotional context',
          type TEXT DEFAULT NULL COMMENT 'Type of content - sentiment_analysis, emotional_context, full_text',
          sourcetype TEXT DEFAULT 'manual' COMMENT 'Source type - manual, automated, ai_analyzed',
          sourcename TEXT DEFAULT 'manual' COMMENT 'Name of the source system or AI model',
          embedding VECTOR(3072) NOT NULL COMMENT 'Vector embedding (3072 dimensions) for sentiment-aware semantic search',
          chunknumber INT DEFAULT 128 COMMENT 'Chunk size used for embedding generation',
          date_modified DATETIME DEFAULT NULL COMMENT 'Last modification timestamp',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp - immutable, auto-set on insert',
          entity_id INT COMMENT 'FK to reviews table - review ID',
          language_id INT COMMENT 'FK to languages table - language identifier',
          metadata longtext DEFAULT NULL COMMENT 'Additional metadata about the embedding - may include sentiment_score, review_id, product_id',
          KEY idx_entity_id (entity_id),
          KEY idx_language_id (language_id),
          KEY idx_entity_lang (entity_id, language_id),
          KEY idx_date_modified (date_modified),
          KEY idx_created_at (created_at)
      ) COMMENT='Vector embeddings for review sentiment analysis - enables emotion-aware search and trend detection';

      CREATE VECTOR INDEX embedding_index ON :table_reviews_sentiment_embedding (embedding);

      CREATE TABLE IF NOT EXISTS :table_return_orders_embedding (
          id SERIAL PRIMARY KEY COMMENT 'Primary key - unique identifier for each return order embedding',
          content longtext DEFAULT NULL COMMENT 'Return order content for embedding generation - reason, description, notes, etc.',
          type TEXT DEFAULT NULL COMMENT 'Type of content - return_reason, customer_notes, full_text',
          sourcetype TEXT DEFAULT 'manual' COMMENT 'Source type - manual, automated, imported',
          sourcename TEXT DEFAULT 'manual' COMMENT 'Name of the source system or process',
          embedding VECTOR(3072) NOT NULL COMMENT 'Vector embedding (3072 dimensions) for semantic search',
          chunknumber INT DEFAULT 128 COMMENT 'Chunk size used for embedding generation',
          date_modified DATETIME DEFAULT NULL COMMENT 'Last modification timestamp',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp - immutable, auto-set on insert',
          entity_id INT COMMENT 'FK to return_orders table - return order ID',
          metadata longtext DEFAULT NULL COMMENT 'Additional metadata about the embedding - may include order_status, total, customer_id',
          KEY idx_entity_id (entity_id),
          KEY idx_date_modified (date_modified),
          KEY idx_created_at (created_at)
      ) COMMENT='Vector embeddings for return orders - enables semantic return analysis and pattern detection';

      CREATE VECTOR INDEX embedding_index ON :table_return_orders_embedding (embedding);

      CREATE TABLE IF NOT EXISTS :table_orders_embedding (
          id SERIAL PRIMARY KEY COMMENT 'Primary key - unique identifier for each order embedding',
          content longtext DEFAULT NULL COMMENT 'Order content for embedding generation - items, notes, customer info, etc.',
          type TEXT DEFAULT NULL COMMENT 'Type of content - order_details, customer_notes, full_text',
          sourcetype TEXT DEFAULT 'manual' COMMENT 'Source type - manual, automated, imported',
          sourcename TEXT DEFAULT 'manual' COMMENT 'Name of the source system or process',
          embedding VECTOR(3072) NOT NULL COMMENT 'Vector embedding (3072 dimensions) for semantic search',
          chunknumber INT DEFAULT 128 COMMENT 'Chunk size used for embedding generation',
          date_modified DATETIME DEFAULT NULL COMMENT 'Last modification timestamp',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp - immutable, auto-set on insert',
          entity_id INT COMMENT 'FK to orders table - order ID',
          metadata longtext DEFAULT NULL COMMENT 'Additional metadata about the embedding - may include order_status, total, customer_id',
          KEY idx_entity_id (entity_id),
          KEY idx_date_modified (date_modified),
          KEY idx_created_at (created_at)
      ) COMMENT='Vector embeddings for orders - enables semantic order search and pattern analysis';

      CREATE VECTOR INDEX embedding_index ON :table_orders_embedding (embedding);
    EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
    // Create embeddings table for order insights
    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_rag_agent_order_insights_embedding"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
        CREATE TABLE IF NOT EXISTS :table_rag_agent_order_insights_embedding (
            id SERIAL PRIMARY KEY COMMENT 'Primary key - unique identifier for each insight embedding',
            content longtext DEFAULT NULL COMMENT 'Insight content for embedding generation - summary, recommendations, analysis',
            type TEXT DEFAULT NULL COMMENT 'Type of content - summary, recommendations, full_insights',
            sourcetype TEXT DEFAULT 'automated' COMMENT 'Source type - automated (from LLM), manual, imported',
            sourcename TEXT DEFAULT 'insights_agent' COMMENT 'Name of the source system or process',
            embedding VECTOR(3072) NOT NULL COMMENT 'Vector embedding (3072 dimensions) for semantic search',
            chunknumber INT DEFAULT 128 COMMENT 'Chunk size used for embedding generation',
            date_modified DATETIME DEFAULT NULL COMMENT 'Last modification timestamp',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp - immutable, auto-set on insert',
            entity_id INT COMMENT 'FK to rag_agent_order_insights table - insight ID',
            metadata LONGTEXT COMMENT 'JSON metadata for the embedding',
            language_id INT DEFAULT NULL COMMENT 'Language ID from languages table',
            KEY idx_entity_id (entity_id),
            KEY idx_language_id (language_id),
            KEY idx_date_modified (date_modified),
            KEY idx_created_at (created_at)
        ) COMMENT='Vector embeddings for order insights - enables semantic insight search and pattern analysis across orders'
        EOD;
      $CLICSHOPPING_Db->exec($sql);

      // Create vector index
      $CLICSHOPPING_Db->exec('CREATE VECTOR INDEX embedding_index ON :table_rag_agent_order_insights_embedding (embedding)');
    }

    // Create products_seo_embedding table
    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_products_seo_embedding"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
    CREATE TABLE IF NOT EXISTS :table_products_seo_embedding (
     id              INT(11)       NOT NULL AUTO_INCREMENT COMMENT 'Primary key - unique identifier for each SEO embedding chunk',
     content         TEXT          COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Text content embedded - serialized SEO report data (title, meta, H1-H3, keywords, scores)',
     type            TEXT          COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Type of SEO content: initial_report | optimized_report | audit_summary | suggestion',
     sourcetype      TEXT          COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Trigger origin: manual | hook | cron',
     sourcename      TEXT          COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Source identifier: SeoReport | AgentSeo | AgentAuditSeo | AgentSerp',
     embedding       VECTOR(3072)  NOT NULL COMMENT 'Vector embedding 3072 dimensions - OpenAI text-embedding-3-large',
     chunknumber     INT(11)       DEFAULT 128 COMMENT 'Chunk number for large reports - default 128 tokens per chunk',
     date_modified   DATETIME      DEFAULT NULL COMMENT 'Timestamp of last modification',
     created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp - immutable, auto-set on insert',
     entity_id       INT(11)       NOT NULL COMMENT 'FK - references the entity (category, product, cms page)',
      entity_type     VARCHAR(50)   COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Entity type: category | product | cms',
     language_id     INT(11)       NOT NULL COMMENT 'FK to languages table',
     metadata        LONGTEXT      COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'JSON: url, page_type, seo_score_before, seo_score_after, status, report_raw, suggestions, audit_result, serp_data',
      PRIMARY KEY (id),
      KEY idx_entity_lang    (entity_id,language_id),
      KEY idx_type           (type(50)),
      KEY idx_sourcetype     (sourcetype(50)),
      KEY idx_date_modified  (date_modified),
      KEY idx_created_at     (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    // Create products_seo_embedding table
    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_categories_seo_embedding"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
    CREATE TABLE IF NOT EXISTS :table_categories_seo_embedding (
      id              INT(11)       NOT NULL AUTO_INCREMENT COMMENT 'Primary key - unique identifier for each SEO embedding chunk',
      content         TEXT          COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Text content embedded - serialized SEO report data (title, meta, H1-H3, keywords, scores)',
      type            TEXT          COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Type of SEO content: initial_report | optimized_report | audit_summary | suggestion',
      sourcetype      TEXT          COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Trigger origin: manual | hook | cron',
      sourcename      TEXT          COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Source identifier: SeoReport | AgentSeo | AgentAuditSeo | AgentSerp',
      embedding       VECTOR(3072)  NOT NULL COMMENT 'Vector embedding 3072 dimensions - OpenAI text-embedding-3-large',
      chunknumber     INT(11)       DEFAULT 128 COMMENT 'Chunk number for large reports - default 128 tokens per chunk',
      date_modified   DATETIME      DEFAULT NULL COMMENT 'Timestamp of last modification',
      created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp - immutable, auto-set on insert',
      entity_id       INT(11)       NOT NULL COMMENT 'FK - references the entity (category, product, cms page)',
        entity_type     VARCHAR(50)   COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Entity type: category | product | cms',
      language_id     INT(11)       NOT NULL COMMENT 'FK to languages table',
      metadata        LONGTEXT      COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'JSON: url, page_type, seo_score_before, seo_score_after, status, report_raw, suggestions, audit_result, serp_data',
      PRIMARY KEY (id),
      KEY idx_entity_lang    (entity_id, language_id),
      KEY idx_type           (type(50)),
      KEY idx_sourcetype     (sourcetype(50)),
      KEY idx_date_modified  (date_modified),
      KEY idx_created_at     (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    EOD;
      $CLICSHOPPING_Db->exec($sql);
    }

    // Create CockpitAI embedding table
    $Qcheck = $CLICSHOPPING_Db->query('show tables like ":table_products_cockpit_ai_embedding"');

    if ($Qcheck->fetch() === false) {
      $sql = <<<EOD
        CREATE TABLE IF NOT EXISTS :table_products_cockpit_ai_embedding  (
          id INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary key - unique identifier for each CockpitAI analysis embedding',
          content TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Generated from metadata using normalized template v1.0',
          type ENUM('score_product','score_commercial','analysis','action_plan','history') DEFAULT NULL COMMENT 'Type of analysis content',
          sourcetype ENUM('manual','auto') DEFAULT NULL COMMENT 'Trigger origin: manual (merchant) | auto (MCP/hook - future)',
          sourcename TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Source identifier: merchant username | system component',
          chunknumber int(11) DEFAULT 128 COMMENT 'Chunk size for embedding generation',
          embedding VECTOR(3072) NOT NULL COMMENT 'Vector embedding 3072 dimensions - OpenAI text-embedding-3-large',
          date_modified DATETIME DEFAULT NULL COMMENT 'Timestamp of analysis generation',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp - immutable, auto-set on insert',
          entity_id INT(11) NOT NULL COMMENT 'FK to products table - product ID',
          entity_type varchar(50) DEFAULT NULL COMMENT 'Entity type (product, category, etc.)',
          language_id INT(11) NOT NULL COMMENT 'FK to languages table - language identifier',
          metadata LONGTEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'JSON structure with versioned analysis details (scores, factors, actions, history)',
          PRIMARY KEY (id),
          KEY idx_entity_id (entity_id),
          KEY idx_date_modified (date_modified),
          KEY idx_entity_date (entity_id, date_modified),
          KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cockpit IA strategic product analysis embeddings - dual-axis scoring with RAG context'
        EOD;
      $CLICSHOPPING_Db->exec($sql);

      $CLICSHOPPING_Db->exec('CREATE VECTOR INDEX embedding_index ON :table_products_cockpit_ai_embedding  (embedding)');
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
          id INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Primary key - unique identifier for each embedding chunk',
          content TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Text content that was embedded - for reference and debugging',
          type TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Content type - always "faq" for this table',
          sourcetype TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Source type - origin of content (e.g., "manual", "generated", "cron")',
          sourcename TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Source name - specific source identifier (e.g., "seo_generator", "cron_job", "manual_edit")',
          embedding VECTOR(3072) NOT NULL COMMENT 'Vector embedding - 3072 dimensions for OpenAI text-embedding-3-large or VoyageAI',
          chunknumber INT(11) DEFAULT 128 COMMENT 'Chunk size in tokens - default 128 tokens per chunk',
          date_modified DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last modification timestamp - automatically updated',
          entity_id INT(11) NOT NULL COMMENT 'Reference to products_id - links to products table',
          language_id INT(11) NOT NULL COMMENT 'Reference to languages_id - links to languages table',
          metadata LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(metadata)) COMMENT 'JSON metadata - product info, tags, source details (e.g., {"product_id":123,"type":"faq","tags":["shipping","returns"],"source":"seo_generator"})',
          taxonomy LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(taxonomy)) COMMENT 'Structured taxonomy metadata - generated by SemanticAgent::createTaxonomy() - separate from embedding content',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation timestamp - automatically set on insert',
          PRIMARY KEY (id),
          KEY idx_id (id) COMMENT 'Fast lookup by primary key',
          KEY idx_entity_id (entity_id) COMMENT 'Fast lookup by product ID for product-based queries',
          KEY idx_language_id (language_id) COMMENT 'Fast lookup by language ID for language-based queries'
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
      CREATE VECTOR INDEX embedding_index ON :table_products_description_faq_embedding (embedding);

    EOD;
      $CLICSHOPPING_Db->exec($sql);
    }
  }
}
