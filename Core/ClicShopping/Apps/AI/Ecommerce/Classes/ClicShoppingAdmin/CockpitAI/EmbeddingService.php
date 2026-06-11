<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI;

use ClicShopping\AI\DomainsAI\Shared\Embedding\NewVector;
use ClicShopping\AI\Rag\MultiDBRAGManager;
use ClicShopping\OM\Registry;

/**
 * EmbeddingService
 *
 * Responsible for generating normalized content from metadata, creating embeddings,
 * and managing RAG context retrieval for CockpitAI analysis.
 *
 * Key responsibilities:
 *  - Generate content from metadata using versioned templates (Requirement 17)
 *  - Store embeddings with metadata in clic_products_cockpit_ai_embedding  table (Requirement 15)
 *  - Retrieve historical context (Top-3, date DESC, entity_id filtered) (Requirement 11)
 *  - Support embedding format versioning for backward compatibility (Requirement 15.3)
 *
 * Template v1.0 format (Requirement 17.1-17.9):
 *   Produit #{id} {name} | Analyse #{n} | {date} |
 *   Score produit {X}/100 : {factors} |
 *   Score commercial {Y}/100 : vues {v}, commandes {c}, conversion {tx}% |
 *   Quadrant {Q} : {label} |
 *   Stratégie : {strategy_x} + {strategy_y} |
 *   Actions : {actions} |
 *   Evolution : X {delta_x:+d}pts Y {delta_y:+d}pts trend:{trend}
 */
class EmbeddingService
{
  private const TABLE_NAME = 'products_cockpit_ai_embedding'; // Table name without prefix
  private const EMBEDDING_FORMAT_VERSION = '1.0';
  
  private MultiDBRAGManager $ragManager;
  private bool $debug;
  private mixed $db;

  public function __construct()
  {
    $this->ragManager = new MultiDBRAGManager();
    $this->debug = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG') && CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG === 'True';
    $this->db = Registry::get('Db');

  }

  /**
   * Store embedding with metadata in clic_products_cockpit_ai_embedding  table
   *
   * Generates content from metadata, creates embedding via MultiDBRAGManager,
   * and persists with full metadata JSON.
   *
   * Requirements 15.1-15.9:
   *  - Store in clic_products_cockpit_ai_embedding  table
   *  - Generate content from metadata using normalized template
   *  - Include embedding_format_version in metadata
   *  - Store as vector(3072)
   *  - Store type as ENUM (analysis, score_product, score_commercial, action_plan, history)
   *  - Store sourcetype as ENUM (manual, auto)
   *  - Link to product via entity_id
   *  - Support multi-language via language_id
   *  - Store date_modified for temporal sorting
   *
   * @param array $metadata Complete analysis metadata (Requirements 16.1-16.10)
   * @param int $productId Product ID (entity_id)
   * @param int $languageId Language ID
   * @return int|null Embedding ID if successful, null on failure
   */
  public function storeEmbedding(array $metadata, int $productId, int $languageId): ?int
  {
    try {
      // Résolution sans ambiguïté : on cherche le bloc interne 'metadata' s'il existe,
      // sinon on travaille directement sur le tableau reçu.
      $source = (isset($metadata['metadata']) && is_array($metadata['metadata']))
        ? $metadata['metadata']
        : $metadata;

      if (empty($source) ||
        (!isset($source['scores']) && !isset($source['analysis']['text']))) {

        if ($this->debug) {
          error_log("[CockpitAI] Store Aborted: Data incomplete for product " . $productId);
        }
        return null;
      }

      // Lecture de fallback_used sur tous les niveaux possibles selon la structure passée par ReportBuilder.
      // On collecte toutes les valeurs trouvées : si AU MOINS UNE est explicitement false, on considère
      // que c'est une vraie analyse LLM (le false du générateur prime sur tout default true d'un autre niveau).
      $fallbackValues = [];

      // Niveau 1 : source['analysis']['fallback_used']  (structure normale post-LlmAnalysisGenerator)
      if (isset($source['analysis']['fallback_used'])) {
        $fallbackValues[] = $source['analysis']['fallback_used'];
      }
      // Niveau 2 : metadata racine -> fallback_used  (si ReportBuilder l'a remonté)
      if (isset($metadata['fallback_used'])) {
        $fallbackValues[] = $metadata['fallback_used'];
      }
      // Niveau 3 : metadata['metadata']['analysis']['fallback_used']  (double-wrap de ReportBuilder)
      if (isset($metadata['metadata']['analysis']['fallback_used'])) {
        $fallbackValues[] = $metadata['metadata']['analysis']['fallback_used'];
      }

      // Si au moins un niveau dit explicitement false → vraie analyse, on stocke.
      // On ne bloque que si TOUS les niveaux trouvés disent true (ou si aucun niveau n'existe → on stocke).
      $hasExplicitFalse = in_array(false, $fallbackValues, true);
      $allTrue          = !empty($fallbackValues) && !$hasExplicitFalse && in_array(true, $fallbackValues, true);

      // Double-vérification de sécurité : si le texte d'analyse est long (>100 chars), c'est forcément une vraie analyse LLM.
      $analysisText = $source['analysis']['text'] ?? $source['analysis_text'] ?? $metadata['metadata']['analysis']['text'] ?? '';
      $hasRealText  = strlen($analysisText) > 100;

      if ($this->debug) {
        error_log("[CockpitAI] Store Check: fallback_values=" . json_encode($fallbackValues)
          . ", allTrue=" . ($allTrue ? 'true' : 'false')
          . ", hasExplicitFalse=" . ($hasExplicitFalse ? 'true' : 'false')
          . ", hasRealText=" . ($hasRealText ? 'true' : 'false')
          . " for product " . $productId);
      }

      // On bloque uniquement si tous les flags disent fallback ET qu'il n'y a pas de vrai texte LLM.
      if ($allTrue && !$hasRealText) {
        if ($this->debug) {
          error_log("[CockpitAI] Store Aborted: Confirmed fallback (no real text), skipping storage for product " . $productId);
        }
        return null;
      }

      $metadata['entity_id'] = $productId;
      $content = $this->generateContent($metadata);
      $analysisNumber = $metadata['history']['analysis_number'] ?? 1;

      $completeMetadata = [
        'version' => $metadata['version'] ?? '1.0',
        'schema' => $metadata['schema'] ?? 'CockpitAI.product.analysis',
        'embedding_format_version' => self::EMBEDDING_FORMAT_VERSION,

        'scores' => [
          'score_x' => (float)($source['scores']['score_x'] ?? 0),
          'score_y' => (float)($source['scores']['score_y'] ?? 0),
          'quadrant' => $metadata['scores']['quadrant'] ?? 'Q_intermediate',
        ],

        'seo' => [
          'score' => (int)($source['seo']['score'] ?? 0),
          'status' => $metadata['seo']['status'] ?? 'NOT_ANALYZED',
        ],

        'commercial_metrics' => [
          'views_30d' => (int)($source['commercial_metrics']['views_30d'] ?? 0),
          'orders' => (int)($source['commercial_metrics']['orders'] ?? 0),
          'conversion_rate' => (float)($source['commercial_metrics']['conversion_rate'] ?? 0.0),
          'returns' => (int)($source['commercial_metrics']['returns'] ?? 0),
        ],

        'feature_flags' => [
          'promo_active' => (bool)($source['feature_flags']['promo_active'] ?? false),
          'feature' => (bool)($source['feature_flags']['feature'] ?? false),
          'favorites' => (bool)($source['feature_flags']['favorites'] ?? false), // AJOUT : État favoris
          'reviews' => (int)($source['feature_flags']['reviews'] ?? 0),
          'recommendations' => (int)($source['feature_flags']['recommendations'] ?? 0),
        ],

        // Strategy preferences (Requirement 16.6)
        'strategy' => [
          'strategy_x' => $metadata['strategy']['strategy_x'] ?? 'quality',
          'strategy_y' => $metadata['strategy']['strategy_y'] ?? 'conversion',
        ],

        'actions' => $metadata['actions'] ?? [],

        'analysis' => [
            'text'          => $source['analysis']['text'] ?? $source['analysis_text'] ?? '',
            'fallback_used' => (bool)($source['analysis']['fallback_used'] ?? false),
        ],

        'history' => [
          'analysis_number' => (int)($source['history']['analysis_number'] ?? 1),
          'previous_embedding_id' => $metadata['history']['previous_embedding_id'] ?? null,
          'delta_x' => $metadata['history']['delta_x'] ?? 0,
          'delta_y' => $metadata['history']['delta_y'] ?? 0,
          'trend' => $metadata['history']['trend'] ?? 'stable',
          'is_latest' => true, // On marque celui-ci comme le dernier
        ],

        // Catalog normalization context (Requirement 7.1-7.5)
        'catalog_normalization' => $metadata['catalog_normalization'] ?? [],

        // Thresholds used (Requirement 8.1-8.5)
        'thresholds' => $metadata['thresholds'] ?? ['T_high' => 70, 'T_low' => 30],

        // Technical metadata (Requirement 16.10)
        'technical' => [
          'model_used' => $metadata['technical']['model_used'] ?? 'unknown',
          'pipeline_duration_ms' => $metadata['technical']['pipeline_duration_ms'] ?? 0,
          'timestamp' => $metadata['technical']['timestamp'] ?? date('Y-m-d\TH:i:s\Z'),
        ],

        // Product identification
        'entity_id' => $productId,
        'product_name' => $metadata['product_name'] ?? 'Unknown',
      ];


      // Step 1: Generate embedding vector from content using LLPhant
      $embeddedDocuments = NewVector::createEmbedding(null, $content, 128);
      
      if ($embeddedDocuments === null || empty($embeddedDocuments)) {
        if ($this->debug) {
          error_log("[CockpitAI] Failed to generate embedding for product_id={$productId}");
        }
        return null;
      }

      // Étape 2 : Préparation finale (Ajout du TYPE pour l'UI)
      $storageMetadata = array_merge([
        'type' => 'analysis', // FIX: Remplit le champ type qui était vide
        'source' =>
          [
            'type' => 'manual',
          'name' => 'CockpitAI'
          ]
      ], $completeMetadata);


     // Step 3: Store embeddings with chunks in database
      $result = NewVector::saveEmbeddingsWithChunks(
        $embeddedDocuments,
        self::TABLE_NAME,
        $productId,
        $languageId,
        $storageMetadata,
        $this->db,
        false  // isUpdate = false for insert
      );

      if ($this->debug) {
        $success = $result['success'] ?? false;
        $chunksSaved = $result['chunks_saved'] ?? 0;
        error_log("[CockpitAI] Embedding stored: product_id={$productId}, language_id={$languageId}, chunks={$chunksSaved}, success=" . ($success ? 'true' : 'false'));
      }

      // Return success status
      return ($result['success'] ?? false) ? 1 : null;

    } catch (\Throwable $e) {
      if ($this->debug) {
        error_log("[CockpitAI] Embedding storage failed: " . $e->getMessage());
      }
      return null;
    }
  }

  /**
   * Generate normalized content from metadata using template v1.0
   *
   * Template interpolates metadata fields into a structured format for embedding.
   * Only valid factors are included in the factors detail string.
   *
   * @param array $metadata Analysis metadata containing scores, factors, actions, history
   * @return string Normalized content string ready for embedding
   */
  public function generateContent(array $metadata): string
  {
    // Normalisation de l'accès aux données (gestion du niveau 'metadata')
    $src = isset($metadata['metadata']) ? $metadata['metadata'] : $metadata;

    // Préparation des variables pour le template
    $flags = $src['feature_flags'] ?? [];
    $history = $src['history'] ?? [];
    $scores = $src['scores'] ?? [];
    $metrics = $src['commercial_metrics'] ?? [];

    $template = <<<TEMPLATE
Template v1.0 format (Requirement 17):
Product ID: {id} | Name: {name}
Quality Score (X): {score_x}/100
Performance Score (Y): {score_y}/100
Quadrant: {quadrant}

Sales Status:
- Views (30 days): {views}
- Orders: {orders}
- Conversion Rate: {conversion}%

Product Status:

- On Sale: {promo}
- Featured Product: {featured}
- Favorite Product: {favorites}

History and Trends:
- Analysis #: {analysis_num}
- Quality Change (ΔX): {delta_x}
- Performance Change (ΔY): {delta_y}
- Trend: {trend}

Active Recommendations: {actions_count}
TEMPLATE;

    // Data mapping for interpolation
    $data = [
      'id' => $src['entity_id'] ?? 0,
      'name' => $src['product_name'] ?? 'Unknown',
      'score_x' => number_format((float)($scores['score_x'] ?? 0), 2),
      'score_y' => number_format((float)($scores['score_y'] ?? 0), 2),
      'quadrant' => $scores['quadrant'] ?? 'N / A',
      'views' => $metrics['views_30d'] ?? 0,
      'orders' => $metrics['orders'] ?? 0,
      'conversion' => $metrics['conversion_rate'] ?? 0,
      'promo' => ($flags['promo_active'] ?? false) ? 'YES' : 'NO',
      'featured' => ($flags['feature'] ?? false) ? 'YES' : 'NO',
      'favorites' => ($flags['favorites'] ?? false) ? 'YES' : 'NO', // CRITICAL for stability
      'analysis_num' => $history['analysis_number'] ?? 1,
      'delta_x' => $history['delta_x'] ?? 0,
      'delta_y' => $history['delta_y'] ?? 0,
      'trend' => $history['trend'] ?? 'stable',
      'actions_count'=> isset($src['actions']) ? count($src['actions']): 0
    ];

    return $this->interpolateTemplate($template, $data);
  }

  /**
   * Interpolate template variables with metadata values
   *
   * Replaces {variable} placeholders with corresponding metadata values.
   * Handles nested arrays and missing values gracefully.
   *
   * @param string $template Template string with {variable} placeholders
   * @param array $metadata Metadata array with values
   * @return string Interpolated content string
   */
  private function interpolateTemplate(string $template, array $metadata): string
  {
    // Normalisation de l'accès (gestion du cas où metadata est encapsulé)
    $src = isset($metadata['metadata']) ? $metadata['metadata'] : $metadata;

    // 1. Extraction des infos de base
    $entity_id = $src['entity_id'] ?? 0;
    $product_name = $src['product_name'] ?? 'Inconnu';

    // 2. Scores et Quadrant
    $scoreX = $src['scores']['score_x'] ?? 0;
    $scoreY = $src['scores']['score_y'] ?? 0;
    $quadrant = $src['scores']['quadrant'] ?? 'N/A';

    // 3. Facteurs détaillés (X)
    $factorsX = $src['factors_x'] ?? ($src['scores']['factors_x'] ?? null);
    $factorsXDetail = ($factorsX !== null) ? $this->buildFactorsDetail($factorsX) : '';

    // 4. Métriques commerciales
    $metrics = $src['commercial_metrics'] ?? [];
    $views = $metrics['views_30d'] ?? 0;
    $orders = $metrics['orders'] ?? 0;
    $conversion = $metrics['conversion_rate'] ?? 0.0;

    // 5. Flags d'état (CRITIQUE pour éviter les doublons d'actions)
    $flags = $src['feature_flags'] ?? [];
    $isPromo = ($flags['promo_active'] ?? false) ? 'YES' : 'NO';
    $isFeatured = ($flags['feature'] ?? false) ? 'YES' : 'NO';
    $isFavorite = ($flags['favorites'] ?? false) ? 'YES' : 'NO';

    // 6. Historique et Deltas
    $history = $src['history'] ?? [];
    $deltaX = $history['delta_x'] ?? 0;
    $deltaY = $history['delta_y'] ?? 0;
    $trend = $history['trend'] ?? 'stable';
    $analysisNum = $history['analysis_number'] ?? 1;

    // 7. Actions
    $actionsCount = count($src['actions'] ?? []);

    // Construction du tableau de remplacement final
    $replacements = [
      '{id}' => $entity_id,
      '{name}' => $product_name,
      '{score_x}' => number_format((float)$scoreX, 2),
      '{score_y}' => number_format((float)$scoreY, 2),
      '{quadrant}' => $quadrant,
      '{views}' => $views,
      '{orders}' => $orders,
      '{conversion}' => round($conversion * 100, 2), // Conversion en % pour le LLM
      '{promo}' => $isPromo,
      '{featured}' => $isFeatured,
      '{favorites}' => $isFavorite,
      '{delta_x}' => ($deltaX > 0 ? '+' : '') . $deltaX,
      '{delta_y}' => ($deltaY > 0 ? '+' : '') . $deltaY,
      '{trend}' => $trend,
      '{analysis_num}' => $analysisNum,
      '{factors_x_detail}' => $factorsXDetail,
      '{actions_count}' => $actionsCount,
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $template);
  }

  /**
   * Build factors detail string from factors array
   *
   * Only includes factors with status='valid' in the output.
   * Format: "factor1(0.65), factor2(0.80), factor3(0.55)"
   *
   * @param array $factors Array of factors with code, status, normalized values
   * @return string Comma-separated factors detail string
   */
  private function buildFactorsDetail(array $factors): string
  {
    $validFactors = [];

    ksort($factors);

    foreach ($factors as $code => $factor) {
      if (isset($factor['status']) && $factor['status'] === 'valid' && isset($factor['normalized'])) {
        $normalized = number_format($factor['normalized'], 2, '.', '');
        $validFactors[] = "{$code}:{$normalized}";
      }
    }

    return implode(',', $validFactors);
  }

  /**
   * Retrieve historical embeddings for RAG context
   *
   * Retrieves Top-K most recent embeddings for the specified product and language.
   * Results are sorted by date_modified DESC and filtered by entity_id.
   *
   * Requirements 11.1-11.5, 16.1-16.10:
   *  - Exactly K most recent embeddings (default 3) (Requirement 11.1)
   *  - Sorted by date DESC (Requirement 11.2)
   *  - Filtered by entity_id (product) (Requirement 11.3)
   *  - Filtered by language_id (Requirement 24.4)
   *  - Only content field included (metadata excluded from context) (Requirement 11.4)
   *
   * Uses direct database query for chronological retrieval (not semantic search).
   * This ensures we get the most recent analyses regardless of content similarity.
   *
   * @param int $productId Product ID to filter by
   * @param int $languageId Language ID to filter by
   * @param int $limit Number of embeddings to retrieve (default 3)
   * @return array Array of content strings (not full documents)
   */
  public function getHistoricalContext(int $productId, int $languageId, int $limit = 3): array
  {
    try {
      // Requirements 11.1-11.5: Top-K, date DESC, entity_id filtered
      // Correction SQL : LIMIT :limit (pas de signe =)
      $Qhistory = $this->db->prepare('SELECT metadata as report_json, 
                                             date_modified as date_added 
                                      FROM :table_products_cockpit_ai_embedding  
                                      WHERE entity_id = :entity_id 
                                      AND language_id = :language_id
                                      ORDER BY date_modified DESC 
                                      LIMIT :limit');

      $Qhistory->bindInt(':entity_id', $productId);
      $Qhistory->bindInt(':language_id', $languageId);
      $Qhistory->bindInt(':limit', $limit);
      $Qhistory->execute();

      $context = [];
      while ($row = $Qhistory->fetch()) {
        // 1. On décode le JSON contenu dans la colonne 'metadata' (alias report_json)
        $meta = json_decode($row['report_json'], true);

        // 2. On construit l'élément de contexte en allant chercher les scores DANS le JSON
        $context[] = [
          'date' => $row['date_added'],
          'scores' => [
            // On utilise les clés du JSON définies lors du stockage (Step 10)
            'x' => (float)($meta['scores']['score_x'] ?? 0),
            'y' => (float)($meta['scores']['score_y'] ?? 0)
          ],
          'analysis' => $meta // Contient tout le rapport pour le RAG
        ];
      }

      if ($this->debug) {
        error_log("[CockpitAI] Retrieved " . count($context) . " historical embeddings for product_id={$productId}");
      }

      return $context;

    } catch (\Throwable $e) {
      if ($this->debug) {
        error_log("[CockpitAI] Historical context retrieval failed: " . $e->getMessage());
      }
      return []; // Fallback (Requirement 10.5)
    }
  }

  /**
   * Get embedding format version
   *
   * @return string Current embedding format version
   */
  public function getEmbeddingFormatVersion(): string
  {
    return self::EMBEDDING_FORMAT_VERSION;
  }

  /**
   * Get table name for embeddings
   *
   * @return string Table name
   */
  public function getTableName(): string
  {
    return self::TABLE_NAME;
  }

  /**
   * Récupère l'analyse la plus récente pour le cache (Requirement 11 modifiée)
   * Utilisé par LlmAnalysisGenerator pour éviter les appels API inutiles.
   */
  public function getLatestEmbedding(int $productId, int $languageId): array
  {
    try {
      $Qcheck = $this->db->prepare('SELECT metadata 
                                  FROM :table_products_cockpit_ai_embedding  
                                  WHERE entity_id = :entity_id 
                                  AND language_id = :language_id
                                  ORDER BY date_modified DESC 
                                  LIMIT 1');

      $Qcheck->bindInt(':entity_id', $productId);
      $Qcheck->bindInt(':language_id', $languageId);
      $Qcheck->execute();

      if ($Qcheck->check()) {
        $meta = json_decode($Qcheck->value('metadata'), true);
        return [
          'analysis_text' => $meta['analysis']['text'] ?? '',
          'metadata'      => $meta,
          'date_added'    => $Qcheck->value('date_modified') // On utilise la date de la table
        ];
      }
    } catch (\Throwable $e) {
      if ($this->debug) error_log("[CockpitAI] getLatestEmbedding failed: " . $e->getMessage());
    }
    return [];
  }

  /**
   * Get human-readable quadrant label
   *
   * @param string $quadrant Quadrant code (Q1, Q2, Q3, Q4, Q_intermediate)
   * @return string Quadrant label
   */
  private function getQuadrantLabel(string $quadrant): string
  {
    $labels = [
      'Q1' => 'Scaling',
      'Q2' => 'Acquisition',
      'Q3' => 'Rework/Kill',
      'Q4' => 'Optimization',
      'Q_intermediate' => 'Monitoring',
    ];

    return $labels[$quadrant] ?? 'Unknown';
  }
}
