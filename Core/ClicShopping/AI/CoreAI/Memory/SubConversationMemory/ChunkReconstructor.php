<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Memory\SubConversationMemory;

use LLPhant\Embeddings\Document;

/**
 * ChunkReconstructor
 *
 * Pure helper extracted verbatim from ConversationMemory (2026-06-20). Rebuilds
 * full interactions from the vector-store chunks produced when a long
 * interaction is split for embedding. Stateless: both methods are pure
 * transforms over their array inputs (no dependencies), called by
 * ConversationMemory::loadRecentHistory().
 */
class ChunkReconstructor
{
  /**
   * Groups search result documents (chunks) by their 'interaction_id'.
   * Also handles single (non-chunked) interactions.
   *
   * @param \LLPhant\Embeddings\Document[] $documents Search results from the vector store.
   * @return array Grouped results keyed by interaction ID, sorted by best similarity score.
   */
  public function groupChunksByInteraction(array $documents): array
  {
    $grouped = [];

    foreach ($documents as $doc) {
      if (!$doc instanceof Document) {
        continue; // Skip invalid entries
      }

      $metadata = $doc->metadata ?? [];
      $isChunked = $metadata['is_chunked'] ?? false;
      $score = $metadata['score'] ?? 0; // Assuming the score is stored in metadata upon retrieval

      if ($isChunked) {
        $interactionId = $metadata['interaction_id'] ?? 'unknown_chunked_' . uniqid();

        // Initialize group entry if it doesn't exist
        if (!isset($grouped[$interactionId])) {
          $grouped[$interactionId] = [
            'is_chunked' => true,
            'chunks' => [],
            'metadata' => $metadata, // Store metadata from the first chunk found
            'best_score' => 0.0,
          ];
        }

        // Add the current chunk detail
        $grouped[$interactionId]['chunks'][] = [
          'content' => $doc->content,
          'chunk_index' => $metadata['chunk_index'] ?? 0,
          'score' => $score,
        ];

        // Keep track of the best score found for this interaction
        if ($score > $grouped[$interactionId]['best_score']) {
          $grouped[$interactionId]['best_score'] = $score;
        }

      } else {
        // Handle single (non-chunked) interaction
        $interactionId = $metadata['interaction_id'] ?? 'single_' . uniqid();

        // Store non-chunked interaction directly
        $grouped[$interactionId] = [
          'is_chunked' => false,
          'content' => $doc->content,
          'metadata' => $metadata,
          'best_score' => $score,
        ];
      }
    }

    // Sort by best score in descending order
    uasort($grouped, function(array $a, array $b) {
      // Use floating point comparison
      return $b['best_score'] <=> $a['best_score'];
    });

    return $grouped;
  }

  /**
   * Reconstructs complete interactions from grouped results (chunks or singles).
   *
   * @param array $groupedResults Results grouped by interaction_id and sorted by score.
   * @param int $limit Max number of complete interactions to return.
   * @return array Formatted and reconstructed interactions.
   */
  public function reconstructInteractions(array $groupedResults, int $limit): array
  {
    $formatted = [];
    $count = 0;

    foreach ($groupedResults as $interactionId => $interaction) {
      if ($count >= $limit) {
        break;
      }

      $metadata = $interaction['metadata'];
      $bestScore = $interaction['best_score'];
      $isReconstructed = $interaction['is_chunked'];

      if ($interaction['is_chunked']) {
        // Sort chunks by index before concatenation
        usort($interaction['chunks'], function(array $a, array $b) {
          return $a['chunk_index'] <=> $b['chunk_index'];
        });

        // Reconstruct the full content by concatenating chunks
        $fullContent = '';
        foreach ($interaction['chunks'] as $chunk) {
          $fullContent .= $chunk['content'] . "\n";
        }

        $formatted[] = [
          'user_message' => $metadata['user_message'] ?? 'N/A',
          'system_response' => $metadata['system_response'] ?? 'N/A',
          'timestamp' => $metadata['timestamp'] ?? 0,
          'agent_type' => $metadata['agent_type'] ?? 'unknown',
          'similarity_score' => $bestScore,
          'is_reconstructed' => $isReconstructed,
          'chunk_count' => count($interaction['chunks']),
          'full_content' => trim($fullContent), // Optionally include for debugging
        ];

      } else {
        // Handle single interaction
        $formatted[] = [
          'user_message' => $metadata['user_message'] ?? 'N/A',
          'system_response' => $metadata['system_response'] ?? 'N/A',
          'timestamp' => $metadata['timestamp'] ?? 0,
          'agent_type' => $metadata['agent_type'] ?? 'unknown',
          'similarity_score' => $bestScore,
          'is_reconstructed' => $isReconstructed,
          'full_content' => $interaction['content'] ?? 'N/A', // The full content is just the single document content
        ];
      }

      $count++;
    }

    return $formatted;
  }




}
