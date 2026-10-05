<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt as ChatGptApp;
use ClicShopping\AI\DomainsAI\Shared\Embedding\NewVector;
use ClicShopping\Sites\Common\HTMLOverrideCommon;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;

/**
 * Builds and stores the embedding document of one PageManager page.
 *
 * The ONLY builder of pages_manager_embedding: the PageManager Save hook and the
 * ChatGpt cron both call it, so the two writers cannot drift into two formats.
 */
class PageManagerEmbedder
{
  private mixed $app;
  private mixed $lang;
  private mixed $semantics;

  public function __construct()
  {
    if (!Registry::exists('ChatGpt')) {
      Registry::set('ChatGpt', new ChatGptApp());
    }

    $this->app = Registry::get('ChatGpt');
    $this->lang = Registry::get('Language');

    if (!Registry::exists('Semantics')) {
      Registry::set('Semantics', new SemanticAgent());
    }

    $this->semantics = Registry::get('Semantics');
  }

  /**
   * Embeds one page row (pages_manager + its description in one language).
   *
   * @param array $item Row with pages_id, pages_title, pages_html_text, page_manager_head_*_tag, language_id
   * @param bool $isUpdate true replaces the existing chunks of this page and language
   * @return bool true when the chunks were saved
   */
  public function embed(array $item, bool $isUpdate): bool
  {
    $language_code = $this->lang->getLanguageCodeById((int)$item['language_id']);
    $page_manager_id = (int)$item['pages_id'];
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/PageManager/rag', $language_code);

    $page_manager_name = isset($item['pages_title']) ? HTMLOverrideCommon::cleanHtmlForEmbedding($item['pages_title']) : '';
    $page_manager_description = isset($item['pages_html_text']) ? HTMLOverrideCommon::cleanHtmlForEmbedding($item['pages_html_text']) : '';
    $seo_page_manager_title = isset($item['page_manager_head_title_tag']) ? HTMLOverrideCommon::cleanHtmlForEmbedding($item['page_manager_head_title_tag']) : '';
    $seo_page_manager_description = isset($item['page_manager_head_desc_tag']) ? HTMLOverrideCommon::cleanHtmlForEmbedding($item['page_manager_head_desc_tag']) : '';
    $seo_page_manager_keywords = isset($item['page_manager_head_keywords_tag']) ? HTMLOverrideCommon::cleanHtmlForEmbedding($item['page_manager_head_keywords_tag']) : '';

    //********************
    // Build embedding with atomic keys + full content
    //********************

    // Extract document metadata using LLM (flexible for any document type)
    $doc_metadata = $this->extractDocumentMetadata($page_manager_name, $page_manager_description, $language_code);

    // Identity block repeated on EVERY chunk: without it only chunk 0 names the page.
    $identity_header = "[{$this->app->getDef('text_key_document_id')}]: $page_manager_id";

    if (!empty($page_manager_name)) {
      $identity_header .= "\n[{$this->app->getDef('text_key_document_title')}]: $page_manager_name";
    }

    // Part 1: Atomic metadata keys
    $embedding_data = $identity_header . "\n";
    $embedding_data .= "[{$this->app->getDef('text_key_domain')}]: {$this->app->getDef('text_value_domain_ecommerce')}\n";
    $embedding_data .= "[{$this->app->getDef('text_key_entity')}]: {$this->app->getDef('text_value_entity_document')}\n\n";

    // Document type from LLM extraction
    $document_type = $doc_metadata['document_type'] ?? 'general_page';
    $embedding_data .= "[{$this->app->getDef('text_key_document_type')}]: $document_type\n";

    // CRITICAL: Add authority markers for reranker
    // These tell the LLM reranker that PageManager documents are OFFICIAL POLICY
    // not just transaction records like Orders
    $embedding_data .= "[document.authority]: official_policy\n";
    $embedding_data .= "[source.type]: primary_documentation\n";

    $embedding_data .= "[{$this->app->getDef('text_key_document_language')}]: $language_code\n";

    // SEO metadata
    if (!empty($seo_page_manager_title)) {
      $embedding_data .= "[{$this->app->getDef('text_key_seo_title')}]: $seo_page_manager_title\n";
    }

    if (!empty($seo_page_manager_description)) {
      $embedding_data .= "[{$this->app->getDef('text_key_seo_description')}]: $seo_page_manager_description\n";
    }

    if (!empty($seo_page_manager_keywords)) {
      $embedding_data .= "[{$this->app->getDef('text_key_seo_keywords')}]: $seo_page_manager_keywords\n";
    }

    // Add extracted metadata as atomic keys
    $legal_clauses = [];

    if (!empty($doc_metadata['jurisdiction'])) {
      $embedding_data .= "[{$this->app->getDef('text_key_jurisdiction')}]: {$doc_metadata['jurisdiction']}\n";
      $legal_clauses['jurisdiction'] = $doc_metadata['jurisdiction'];
    }

    if (!empty($doc_metadata['party_seller'])) {
      $embedding_data .= "[{$this->app->getDef('text_key_party_seller')}]: {$doc_metadata['party_seller']}\n";
      $legal_clauses['party_seller'] = $doc_metadata['party_seller'];
    }

    if (!empty($doc_metadata['party_buyer'])) {
      $embedding_data .= "[{$this->app->getDef('text_key_party_buyer')}]: {$doc_metadata['party_buyer']}\n";
      $legal_clauses['party_buyer'] = $doc_metadata['party_buyer'];
    }

    if (!empty($doc_metadata['payment_methods']) && is_array($doc_metadata['payment_methods'])) {
      $payment_methods_str = implode(', ', $doc_metadata['payment_methods']);
      $embedding_data .= "[{$this->app->getDef('text_key_clause_payment_methods')}]: $payment_methods_str\n";

      // Readable labels in the page's language; an unlabelled code stays as is.
      $labels = [];
      foreach ($doc_metadata['payment_methods'] as $method) {
        $key = 'text_payment_method_' . $method;
        $label = $this->app->getDef($key);
        $labels[] = $label === $key ? $method : $label;
      }

      $embedding_data .= "[{$this->app->getDef('text_key_clause_payment_methods_labels')}]: " . implode(', ', $labels) . "\n";

      $legal_clauses['payment_methods'] = $payment_methods_str;
    }

    if (!empty($doc_metadata['delivery_method'])) {
      $embedding_data .= "[{$this->app->getDef('text_key_clause_delivery_method')}]: {$doc_metadata['delivery_method']}\n";
      $legal_clauses['delivery_method'] = $doc_metadata['delivery_method'];
    }

    if (!empty($doc_metadata['withdrawal_period'])) {
      $embedding_data .= "[{$this->app->getDef('text_key_clause_withdrawal')}]: {$doc_metadata['withdrawal_period']}\n";
      $legal_clauses['withdrawal_period'] = $doc_metadata['withdrawal_period'];
    }

    if (!empty($doc_metadata['warranty'])) {
      $embedding_data .= "[{$this->app->getDef('text_key_clause_warranty')}]: {$doc_metadata['warranty']}\n";
      $legal_clauses['warranty'] = $doc_metadata['warranty'];
    }

    if (!empty($doc_metadata['liability'])) {
      $embedding_data .= "[{$this->app->getDef('text_key_clause_liability')}]: {$doc_metadata['liability']}\n";
      $legal_clauses['liability'] = $doc_metadata['liability'];
    }

    if (!empty($doc_metadata['data_protection'])) {
      $embedding_data .= "[{$this->app->getDef('text_key_clause_data_protection')}]: {$doc_metadata['data_protection']}\n";
      $legal_clauses['data_protection'] = $doc_metadata['data_protection'];
    }

    if (!empty($doc_metadata['governing_law'])) {
      $embedding_data .= "[{$this->app->getDef('text_key_governing_law')}]: {$doc_metadata['governing_law']}\n";
      $legal_clauses['governing_law'] = $doc_metadata['governing_law'];
    }

    if (!empty($doc_metadata['court'])) {
      $embedding_data .= "[{$this->app->getDef('text_key_court')}]: {$doc_metadata['court']}\n";
      $legal_clauses['court'] = $doc_metadata['court'];
    }

    // Part 2: Full document content (for semantic search)
    if (!empty($page_manager_description)) {
      $embedding_data .= "\n--- DOCUMENT CONTENT ---\n\n";
      $embedding_data .= HTMLOverrideCommon::cleanHtmlForEmbedding($page_manager_description) . "\n";
    }

    // Extract atomic keys for metadata tags (no AI taxonomy)
    $tags = [];
    if (preg_match_all('/^\[([^\]]+)\]:/m', $embedding_data, $matches)) {
      $tags = array_unique($matches[1]);
    }

    // Generate embeddings
    $embeddedDocuments = NewVector::createEmbedding(null, $embedding_data, null, $identity_header);

    // Prepare base metadata for centralized chunk management
    $baseMetadata = [
      'brand_name' => $page_manager_name,
      'content' => $page_manager_description,
      'type' => 'pages_manager',  // Entity type (goes in 'type' column)
      'document_type' => $document_type,
      'tags' => $tags,
      'legal_clauses' => $legal_clauses,
      'source' => ['type' => 'manual', 'name' => 'manual']  // Goes in 'sourcetype' and 'sourcename' columns
    ];

    // Save all chunks using centralized method
    $result = NewVector::saveEmbeddingsWithChunks(
      $embeddedDocuments,
      'pages_manager_embedding',  // Table name (different from entity type!)
      $page_manager_id,
      (int)$item['language_id'],
      $baseMetadata,
      $this->app->db,
      $isUpdate
    );

    if (!$result['success']) {
      error_log("PageManager: Failed to save embeddings - " . $result['error']);
    } else {
      error_log("PageManager: Successfully saved {$result['chunks_saved']} chunk(s) for entity {$item['pages_id']}");
    }

    return (bool)$result['success'];
  }

  /**
   * Detect document type and extract metadata using LLM structured output
   * 
   * @param string $title Document title
   * @param string $content Document content (FULL content, not truncated)
   * @param string $language_code Language code
   * @return array Document metadata including type and clauses
   */
  private function extractDocumentMetadata(string $title, string $content, string $language_code): array
  {
    // Get prompt template from language file
    $prompt_template = $this->app->getDef('text_prompt_extract_metadata');
    
    // Replace placeholders with actual values
    $prompt = str_replace(
      ['{title}', '{language_code}', '{content}'],
      [$title, $language_code, $content],
      $prompt_template
    );

    try {
      // Use LLM to extract structured metadata
      // IMPORTANT: createTaxonomy signature is: ($text, $prompt, $language_code, $min_character)
      $response = $this->semantics->createTaxonomy($content, $prompt, $language_code, 100);
      
      // Log the raw response for debugging
      error_log("PageManager LLM Response: " . substr($response, 0, 500));
      
      // Try to extract JSON from response (in case there's markdown or extra text)
      if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $response, $matches)) {
        $json_str = $matches[0];
        $metadata = json_decode($json_str, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($metadata)) {
          error_log("PageManager: Successfully extracted metadata - document_type: " . ($metadata['document_type'] ?? 'unknown'));
          return $metadata;
        } else {
          error_log("PageManager: JSON decode error - " . json_last_error_msg());
        }
      } else {
        error_log("PageManager: No JSON found in LLM response");
      }
      
      // Fallback to basic detection if JSON parsing fails
      error_log("PageManager: Using fallback detection");
      return $this->fallbackMetadataDetection($title, $content);
    } catch (\Exception $e) {
      // Fallback to basic detection if LLM fails
      error_log("PageManager metadata extraction failed: " . $e->getMessage());
      return $this->fallbackMetadataDetection($title, $content);
    }
  }

  /**
   * Fallback metadata detection - minimal and agnostic
   * Returns generic metadata when LLM extraction fails
   * 
   * @param string $title Document title
   * @param string $content Document content
   * @return array Basic metadata with generic document type
   */
  private function fallbackMetadataDetection(string $title, string $content): array
  {
    // Fallback is intentionally minimal and agnostic
    // We don't try to guess document type or extract clauses
    // Just return a generic structure that won't break the system
    
    return [
      'document_type' => 'general_page',  // Always generic - let LLM handle specifics
      'jurisdiction' => null,
      'party_seller' => null,
      'party_buyer' => null,
      'payment_methods' => [],
      'delivery_method' => null,
      'withdrawal_period' => null,
      'warranty' => null,
      'liability' => null,
      'data_protection' => null,
      'governing_law' => null,
      'court' => null
    ];
  }
}
