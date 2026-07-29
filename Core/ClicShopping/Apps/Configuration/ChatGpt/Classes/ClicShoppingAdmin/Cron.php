<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */
namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin;


use ClicShopping\OM\Registry;
use ClicShopping\Sites\Common\HTMLOverrideCommon;
use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\AI\DomainsAI\Shared\Embedding\NewVector;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\AI\Infrastructure\Schema\SchemaEmbedder;


class Cron {
  private mixed $app;
  private mixed $lang;
  private mixed $semantics;
  /**
   * Class constructor.
   *
   * Initializes the ChatGpt instance in the Registry if it doesn't already exist,
   * and loads the necessary definitions for the application.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('ChatGpt')) {
      Registry::set('ChatGpt', new ChatGpt());
    }

    $this->app = Registry::get('ChatGpt');
    $this->lang = Registry::get('Language');

    if (!Registry::exists('Semantics')) {
      Registry::set('Semantics', new SemanticAgent());
    }

    $this->semantics = Registry::get('Semantics');
  }

/**
 * Updates the embedding page manager for a specific language.
 *
 * @param int $language_id The ID of the language to update.
 * @return void
 */
  private function updateAllEmbeddingPageManager(int $language_id): void
  {
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/PageManager/rag');

    $Qcheck = $this->app->db->prepare('select pm.pages_id,
                                             pm.page_type,       
                                             pmd.pages_title,
                                             pmd.pages_html_text,
                                             pmd.page_manager_head_title_tag,
                                             pmd.page_manager_head_desc_tag,
                                             pmd.page_manager_head_keywords_tag,
                                             pmd.language_id
                                       from  :table_pages_manager pm,
                                             :table_pages_manager_description pmd
                                       where pm.pages_id = pmd.pages_id 
                                       and pmd.language_id = :language_id
                                       and pm.page_type = 4
                                      ');

    $Qcheck->bindInt(':language_id', $language_id);
    $Qcheck->execute();

    $check_array = $Qcheck->fetchAll();

    foreach ($check_array as $item) {
      $Qcheck = $this->app->db->prepare('select id,
                                                entity_id
                                         from :table_pages_manager_embedding
                                         where entity_id = :entity_id
                                        ');
      $Qcheck->bindInt(':entity_id', $item['pages_id']);
      $Qcheck->execute();

      if ($Qcheck->fetch() === false) {
        $language_code = $this->lang->getLanguageCodeById((int)$item['language_id']);
        $page_manager_name = isset($item['pages_title']) ? HTMLOverrideCommon::cleanHtmlForEmbedding($item['pages_title']) : '';
        $page_manager_description = isset($item['pages_html_text']) ? HTMLOverrideCommon::cleanHtmlForEmbedding($item['pages_html_text']) : '';
        $seo_page_manager_title = isset($item['page_manager_head_title_tag']) ? HTMLOverrideCommon::cleanHtmlForEmbedding($item['page_manager_head_title_tag']) : '';
        $seo_page_manager_description = isset($item['page_manager_head_desc_tag']) ? HTMLOverrideCommon::cleanHtmlForEmbedding($item['page_manager_head_desc_tag']) : '';
        $seo_page_manager_keywords = isset($item['page_manager_head_keywords_tag']) ? HTMLOverrideCommon::cleanHtmlForEmbedding($item['page_manager_head_keywords_tag']) : '';
//********************
// add embedding
//********************
        $embedding_data = "\n" . $this->app->getDef('text_page_manager_name', ['page_title' => $page_manager_name]) . "\n";

        if (!empty($seo_page_manager_title)) {
          $embedding_data .= $this->app->getDef('text_page_manager_seo_title', ['page_title' => $page_manager_name]) . ' : ' . $seo_page_manager_title . "\n";
        }

        if (!empty($seo_page_manager_description)) {
          $embedding_data .= $this->app->getDef('text_page_manager_seo_description', ['page_title' => $page_manager_name]) . ' : ' . $seo_page_manager_description . "\n";
        }

        if (!empty($seo_page_manager_keywords)) {
          $embedding_data .= $this->app->getDef('text_page_manager_seo_keywords', ['page_title' => $page_manager_name]) . ' : ' . $seo_page_manager_keywords . "\n";
        }

        if (!empty($page_manager_description)) {
          $embedding_data .= $this->app->getDef('text_page_manager_description', ['page_title' => $page_manager_name]) . ' : ' . $page_manager_description . "\n";

          $taxonomy = $this->semantics->createTaxonomy($page_manager_description, $this->app->getDef('text_create_taxonomy', ['document_text' => $page_manager_description]), $language_code, 300);
          if (!empty($taxonomy)) {
            $embedding_data .= $this->app->getDef('text_page_manager_taxonomy') . ' : ' . "\n" . $taxonomy . "\n";
          }
        }

        $embeddedDocuments = NewVector::createEmbedding(null, $embedding_data);

        // Extract atomic keys for metadata tags (similar to hook)
        $tags = [];
        if (preg_match_all('/^\[([^\]]+)\]:/m', $embedding_data, $matches)) {
          $tags = array_unique($matches[1]);
        }

        // Prepare base metadata (matching PageManager hook structure)
        $baseMetadata = [
          'brand_name' => $page_manager_name,
          'content' => $page_manager_description,
          'type' => 'pages_manager',  // Entity type (goes in 'type' column)
          'document_type' => 'general_page',  // Default for cron (no LLM extraction)
          'tags' => $tags,
          'legal_clauses' => [],  // Empty for cron (no LLM extraction)
          'source' => ['type' => 'manual', 'name' => 'manual']  // Goes in 'sourcetype' and 'sourcename' columns
        ];

        // Save all chunks using centralized method
        $result = NewVector::saveEmbeddingsWithChunks(
          $embeddedDocuments,
          'pages_manager_embedding',  // Table name (different from entity type!)
          (int)$item['pages_id'],
          (int)$item['language_id'],
          $baseMetadata,
          $this->app->db,
          false  // isUpdate = false (cron only creates missing embeddings)
        );

        if (!$result['success']) {
          error_log("Cron PageManager: Failed to save embeddings for page {$item['pages_id']} - " . $result['error']);
        } else {
          error_log("Cron PageManager: Successfully saved {$result['chunks_saved']} chunks for page {$item['pages_id']}");
        }
      }
    }
  }

  /**
   * Updates the page manager embeddings for every active language, then syncs
   * the schema embedding store.
   *
   * Only the page manager (kept here — not an e-commerce entity) and the
   * schema sync remain in this class. Categories, manufacturers, products,
   * reviews and suppliers embeddings are now generated by the Ecommerce app's
   * EmbeddingCronRunner (Shop/Cronjob/Process dispatch).
   *
   * @return void
   * @throws \JsonException
   */
  public function updateAllEmbeddings(): void
  {
    $language_array = $this->lang->getLanguages();

    foreach ($language_array as $value) {
      $this->updateAllEmbeddingPageManager($value['id']);
    }

    $this->updateSchemaEmbeddings();
  }

  /**
   * Keeps the schema embedding store in sync with the database schema.
   *
   * Entity embeddings follow the data, this one follows the schema: without it any
   * table added, dropped or re-commented degrades schema retrieval silently.
   *
   * @return void
   */
  private function updateSchemaEmbeddings(): void
  {
    $stats = (new SchemaEmbedder())->syncAllTables();

    if ($stats['created'] > 0 || $stats['updated'] > 0 || $stats['deleted'] > 0 || $stats['failed'] > 0) {
      error_log("Cron Schema: {$stats['created']} created, {$stats['updated']} updated, {$stats['deleted']} deleted, {$stats['failed']} failed");
    }
  }

  /**
   * Handles the execution of a cron job related to page manager embedding updates.
   *
   * This method checks if GPT functionality is enabled and if OpenAI embedding is enabled.
   * If both conditions are met, it updates the page manager embeddings for each
   * language and syncs the schema embedding store.
   *
   * @return bool|void Returns false if GPT or embedding is disabled, void otherwise
   */
  public function execute()
  {
    if (Gpt::checkGptStatus() === false) {
      return false;
    }

    if (!defined('CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING') || CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING == 'False') {
      return false;
    }

    $this->updateAllEmbeddings();
  }
}
