<?php
declare(strict_types=1);

/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Rag;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Config\DomainFields;

/**
 * RagContextFormatter - Document/context formatting for LLM prompts.
 *
 * Extracted from MultiDBRAGManager (god-class decomposition): a near-stateless helper
 * (only a debug flag) that turns retrieved documents into the context fed to the LLM —
 * building the bounded context block (priority docs kept full, others truncated, global
 * char budget) and deriving a human-readable document name from metadata.
 * MultiDBRAGManager delegates to this; behaviour unchanged.
 *
 * @package ClicShopping\AI\Rag
 * @since 2026-06-10
 */
class RagContextFormatter
{
  private bool $debug;

  public function __construct(bool $debug = false)
  {
    $this->debug = $debug;
  }

  /**
   * Build a bounded context string from retrieved documents.
   *
   * Priority documents keep their full content; others are truncated per document, and a
   * global character budget caps the total context size.
   *
   * @param array $documents Retrieved documents
   * @param int $maxCharsPerDoc Per-document truncation budget
   * @return string Context block
   */
  public function optimizeContext(array $documents, int $maxCharsPerDoc = 3000): string
  {
    $context = '';
    $totalChars = 0;
    $maxTotalChars = 60000; // Global limit: ~15,000 tokens

    // Function to detect priority documents
    $isPriorityDoc = function ($doc) {
      return isset($doc->metadata['priority_boost']) && $doc->metadata['priority_boost'] === true;
    };

    foreach ($documents as $i => $doc) {
      $documentName = $this->extractDocumentName($doc);

      // Priority documents get FULL content (no truncation)
      if ($isPriorityDoc($doc)) {
        $docContent = $doc->content; //  FULL CONTENT
        $label = $documentName . " (Priority Source)";

        if ($this->debug) {
          error_log("[INFO] Doc #{$i} PRIORITY ({$documentName}): " . strlen($docContent) . " chars (full content)");
        }
      } else {
        // Other documents are truncated
        $docContent = $doc->content;
        if (strlen($docContent) > $maxCharsPerDoc) {
          $docContent = mb_substr($docContent, 0, $maxCharsPerDoc) . "\n[...content truncated...]";
        }
        $label = $documentName;

        if ($this->debug) {
          error_log("[INFO] Doc #{$i} secondary ({$documentName}): " . strlen($docContent) . " chars (truncated)");
        }
      }

      // Check global limit
      if ($totalChars + strlen($docContent) > $maxTotalChars) {
        if ($this->debug) {
          error_log("⚠[warning] Context limit reached after " . ($i + 1) . " documents");
        }
        break;
      }

      $context .= "--- {$label} ---\n";
      $context .= $docContent . "\n\n";
      $totalChars += strlen($docContent);
    }

    if ($this->debug) {
      error_log("[stats] Context built: {$totalChars} chars (~" . round($totalChars / 4) . " tokens)");
    }

    return $context;
  }

  /**
   * Extract document name from document metadata
   *
   *
   * This method extracts the document name from metadata to use in prompts
   * instead of generic "Document 1", "Document 2" labels.
   *
   * Priority order:
   * 1. title (most common)
   * 2. document_name
   * 3. brand_name (for pages_manager)
   * 4. product_name (for products)
   * 5. category_name (for categories)
   * 6. name
   * 7. page_title
   * 8. source_table (as fallback)
   * 9. "Document" (last resort - changed from "Unknown Document" to avoid polluting LLM responses)
   *
   * @param object $doc Document object with metadata
   * @return string Document name
   */
  public function extractDocumentName($doc): string
  {
    // Try to get metadata
    $metadata = null;
    if (is_object($doc) && isset($doc->metadata)) {
      $metadata = $doc->metadata;
    } elseif (is_array($doc) && isset($doc['metadata'])) {
      $metadata = $doc['metadata'];
    }

    if ($metadata === null) {
      return "Document";
    }

    // Try different metadata fields in priority order
    $possibleFields = array_values(array_unique(array_merge(
      DomainFields::getPossibleFields(),
      ['title', 'document_name', 'page_title', 'name']
    )));

    foreach ($possibleFields as $field) {
      if (isset($metadata[$field]) && !empty($metadata[$field])) {
        $name = trim($metadata[$field]);

        // Clean up the name (remove extra whitespace, limit length)
        $name = preg_replace('/\s+/', ' ', $name);

        // Limit length to 100 chars for readability
        if (strlen($name) > 100) {
          $name = substr($name, 0, 97) . '...';
        }

        return $name;
      }
    }

    // Fallback: use source_table if available
    if (isset($metadata['source_table']) && !empty($metadata['source_table'])) {
      $tableName = $metadata['source_table'];

      // Remove prefix and _embedding suffix
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
      if (!empty($prefix) && str_starts_with($tableName, $prefix)) {
        $tableName = substr($tableName, strlen($prefix));
      }
      $tableName = str_replace('_embedding', '', $tableName);

      // Convert to readable format (e.g., "pages_manager_description" -> "Pages Manager Description")
      $tableName = str_replace('_', ' ', $tableName);
      $tableName = ucwords($tableName);

      return $tableName;
    }

    // Last resort: return generic name (changed from "Unknown Document" to "Document")

    return "Document";
  }

}
