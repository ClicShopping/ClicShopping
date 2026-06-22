<?php
/**
 * StatisticsTracker
 * 
 * Enregistre automatiquement les statistiques de performance pour le dashboard
 * Intègre avec OrchestratorAgent et autres agents pour tracking transparent
 */

namespace ClicShopping\AI\Infrastructure\Metrics;

use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * StatisticsTracker Class
 * 
 * All database queries now use DoctrineOrm instead of PDO
 */
class StatisticsTracker
{
  private $db;
  private string $prefix;
  private $startTime;
  private $interactionId;
  private $userId;
  private $sessionId;
  private $languageId;
  
  // Métriques collectées
  private $metrics = [
    'response_time_ms' => null,
    'api_provider' => null,
    'model_used' => null,
    'tokens_prompt' => null,
    'tokens_completion' => null,
    'tokens_total' => null,
    'api_cost_usd' => null,
    'agent_type' => null,
    'classification_type' => null,
    'confidence_score' => null,
    'security_score' => null,
    'response_quality' => null,
    'cache_hit' => false,
    'error_occurred' => false,
    'error_type' => null,
    'error_message' => null
  ];
  
  // Cache metrics (stored in metadata JSON)
  private $cacheMetrics = [
    'cache_type' => null,
    'cache_response_time_ms' => null,
    'uncached_response_time_ms' => null,
    'time_saved_ms' => null,
    'api_cost_saved_usd' => null,
    'cache_key' => null
  ];

  // Observe-first quality verdict detail (stored in metadata.quality JSON)
  private array $qualityVerdict = [];
  
  /**
   * Constructeur
   * 
   * @param int|null $userId ID utilisateur
   * @param string|null $sessionId ID session
   * @param int|null $languageId ID langue
   */
  public function __construct(?int $userId = null, ?string $sessionId = null, ?int $languageId = null)
  {
    $this->db = Registry::get('Db');
    $this->prefix = CLICSHOPPING::getConfig('db_table_prefix');
    $this->userId = $userId ?? 1;
    $this->sessionId = $sessionId ?? session_id();
    $this->languageId = $languageId ?? Registry::get('Language')->getId();
  }
  
  /**
   * Démarre le tracking d'une interaction
   * 
   * @return void
   */
  public function startTracking(): void
  {
    $this->startTime = microtime(true);
  }
  
  /**
   * Stops tracking and calculates response time
   * 
   * @return int Response time in milliseconds
   */
  public function stopTracking(): int
  {
    if ($this->startTime === null) {
      return 0;
    }
    
    $endTime = microtime(true);
    $responseTime = ($endTime - $this->startTime) * 1000; // Convertir en ms
    $this->metrics['response_time_ms'] = (int)round($responseTime);
    
    return $this->metrics['response_time_ms'];
  }
  
  /**
   * Définit l'ID de l'interaction
   * 
   * @param int $interactionId ID de l'interaction
   * @return self
   */
  public function setInteractionId(int $interactionId): self
  {
    $this->interactionId = $interactionId;
    return $this;
  }
  
  /**
   * Définit le type d'agent
   * 
   * @param string $agentType Type d'agent (AnalyticsAgent, SemanticAgent, etc.)
   * @return self
   */
  public function setAgentType(string $agentType): self
  {
    $this->metrics['agent_type'] = $agentType;
    return $this;
  }
  
  /**
   * Définit le type de classification
   * 
   * @param string $classificationType Type (analytics, semantic, hybrid)
   * @return self
   */
  public function setClassificationType(string $classificationType): self
  {
    $this->metrics['classification_type'] = $classificationType;
    return $this;
  }
  
  /**
   * Définit le score de confiance
   * 
   * @param float $confidence Score de confiance (0-1)
   * @return self
   */
  public function setConfidence(float $confidence): self
  {
    $this->metrics['confidence_score'] = round($confidence * 100, 2);
    return $this;
  }
  
  /**
   * Définit les informations API
   * 
   * @param string $provider Provider (openai, anthropic, ollama)
   * @param string $model Modèle utilisé
   * @return self
   */
  public function setApiInfo(string $provider, string $model): self
  {
    $this->metrics['api_provider'] = $provider;
    $this->metrics['model_used'] = $model;
    return $this;
  }
  
  /**
   * Définit les tokens utilisés
   * 
   * @param int $prompt Tokens du prompt
   * @param int $completion Tokens de la complétion
   * @return self
   */
  public function setTokens(int $prompt, int $completion): self
  {
    $this->metrics['tokens_prompt'] = $prompt;
    $this->metrics['tokens_completion'] = $completion;
    $this->metrics['tokens_total'] = $prompt + $completion;
    
    // Calculate cost automatically
    $this->calculateCost();
    
    return $this;
  }
  
  /**
   * Définit si la réponse vient du cache
   * 
   * @param bool $cacheHit True si cache hit
   * @return self
   */
  public function setCacheHit(bool $cacheHit): self
  {
    $this->metrics['cache_hit'] = $cacheHit;
    return $this;
  }
  
  /**
   * Sets cache metrics for performance tracking
   * 
   * @param string $cacheType Type of cache (embedding, semantic, sql, web, conversation)
   * @param bool $hit Whether this was a cache hit
   * @param int $cacheTime Response time with cache (ms)
   * @param int $uncachedTime Estimated response time without cache (ms)
   * @param float $costSaved Estimated API cost saved (USD)
   * @return self
   */
  public function setCacheMetrics(string $cacheType, bool $hit, int $cacheTime, int $uncachedTime, float $costSaved): self
  {
    // Set cache hit flag
    $this->metrics['cache_hit'] = $hit;
    
    // Calculate time saved
    $timeSaved = $uncachedTime - $cacheTime;
    
    // Store cache metrics
    $this->cacheMetrics = [
      'cache_type' => $cacheType,
      'cache_hit' => $hit,
      'cache_response_time_ms' => $cacheTime,
      'uncached_response_time_ms' => $uncachedTime,
      'time_saved_ms' => $timeSaved,
      'api_cost_saved_usd' => $costSaved,
      'cache_key' => null // Will be set if needed
    ];
    
    return $this;
  }
  
  /**
   * Sets the cache key for tracking
   * 
   * @param string $cacheKey Cache key used
   * @return self
   */
  public function setCacheKey(string $cacheKey): self
  {
    $this->cacheMetrics['cache_key'] = $cacheKey;
    return $this;
  }
  
  /**
   * Définit les scores de qualité et sécurité
   * 
   * @param float|null $quality Score de qualité (0-100)
   * @param float|null $security Score de sécurité (0-100)
   * @return self
   */
  public function setQualityScores(?float $quality = null, ?float $security = null): self
  {
    if ($quality !== null) {
      $this->metrics['response_quality'] = round($quality, 2);
    }
    if ($security !== null) {
      $this->metrics['security_score'] = round($security, 2);
    }
    return $this;
  }

  /**
   * Record the observe-first quality verdict DETAIL for this interaction under metadata.quality
   * (hallucination_risk, reliability, accuracy, detected_issues, to_verify) — so a poor answer is
   * surfaced even when no user feedback arrives. Headline scores (response_quality / security_score
   * columns) are set separately via setQualityScores(). Observe-only (no gate). cf
   * LlmResponseEvaluator::deriveQualitySignal().
   *
   * @param array $signal Output of LlmResponseEvaluator::deriveQualitySignal()
   * @return self
   */
  public function setQualityVerdict(array $signal): self
  {
    $this->qualityVerdict = [
      'hallucination_risk' => $signal['hallucination_risk'] ?? null,
      'reliability' => $signal['reliability'] ?? null,
      'accuracy' => $signal['accuracy'] ?? null,
      'detected_issues' => $signal['detected_issues'] ?? [],
      'to_verify' => (bool) ($signal['to_verify'] ?? false),
    ];

    return $this;
  }

  /**
   * Enregistre une erreur
   * 
   * @param string $errorType Type d'erreur
   * @param string $errorMessage Message d'erreur
   * @return self
   */
  public function setError(string $errorType, string $errorMessage): self
  {
    $this->metrics['error_occurred'] = true;
    $this->metrics['error_type'] = $errorType;
    $this->metrics['error_message'] = $errorMessage;
    return $this;
  }
  
  /**
   * Calculates API cost based on tokens and model
   * 
   * @return void
   */
  private function calculateCost(): void
  {
    if ($this->metrics['tokens_prompt'] === null || $this->metrics['tokens_completion'] === null || $this->metrics['model_used'] === null) {
      return;
    }

    $this->metrics['api_cost_usd'] = ApiCostCalculator::calculateCost(
      $this->metrics['model_used'],
      (int)$this->metrics['tokens_prompt'],
      (int)$this->metrics['tokens_completion']
    );
  }
  
  /**
   * Calculates estimated cost savings based on cache type
   * 
   * @param string $cacheType Type of cache (embedding, semantic, sql, web, conversation)
   * @param bool $hit Whether this was a cache hit
   * @return float Estimated cost saved in USD
   */
  public static function calculateCostSavings(string $cacheType, bool $hit): float
  {
    if (!$hit) {
      return 0.0;
    }
    
    // Estimated cost per API call by cache type
    $costPerCall = [
      'embedding' => 0.0001,  // OpenAI embedding API cost
      'semantic' => 0.0001,   // Embedding generation cost
      'sql' => 0.01,          // LLM SQL generation cost
      'web' => 0.05,          // SerpAPI search cost
      'conversation' => 0.001 // Small LLM call cost
    ];
    
    return $costPerCall[$cacheType] ?? 0.0;
  }
  
  /**
   * Saves statistics to database
   * 
   * @return bool True if success, false otherwise
   */
  public function save(): bool
  {
    if ($this->interactionId === null) {
      error_log("StatisticsTracker: Cannot save without interaction_id");
      return false;
    }
    
    try {
      // Arrêter le tracking si pas encore fait
      if ($this->metrics['response_time_ms'] === null && $this->startTime !== null) {
        $this->stopTracking();
      }
      
      // Build metadata JSON
      $metadata = [];
      
      // Add cache metrics if available
      if ($this->cacheMetrics['cache_type'] !== null) {
        $metadata = array_merge($metadata, $this->cacheMetrics);
      }

      // Add observe-first quality verdict if available
      if (!empty($this->qualityVerdict)) {
        $metadata['quality'] = $this->qualityVerdict;
      }

      // Convert metadata to JSON (null if empty)
      $metadataJson = !empty($metadata) ? json_encode($metadata) : null;
      
      // 🔧 MIGRATED TO DOCTRINEORM
      DoctrineOrm::execute("
        INSERT INTO {$this->prefix}rag_statistics 
        (interaction_id, response_time_ms, cache_hit, api_provider, model_used,
         tokens_prompt, tokens_completion, tokens_total, api_cost_usd,
         agent_type, classification_type, confidence_score, security_score,
         response_quality, error_occurred, error_type, error_message,
         user_id, session_id, language_id, metadata, date_added)
        VALUES 
        (?, ?, ?, ?, ?,
         ?, ?, ?, ?,
         ?, ?, ?, ?,
         ?, ?, ?, ?,
         ?, ?, ?, ?, NOW())
      ", [
        $this->interactionId,
        $this->metrics['response_time_ms'],
        $this->metrics['cache_hit'] ? 1 : 0,
        $this->metrics['api_provider'],
        $this->metrics['model_used'],
        $this->metrics['tokens_prompt'],
        $this->metrics['tokens_completion'],
        $this->metrics['tokens_total'],
        $this->metrics['api_cost_usd'],
        $this->metrics['agent_type'],
        $this->metrics['classification_type'],
        $this->metrics['confidence_score'],
        $this->metrics['security_score'],
        $this->metrics['response_quality'],
        $this->metrics['error_occurred'] ? 1 : 0,
        $this->metrics['error_type'],
        $this->metrics['error_message'],
        $this->userId,
        $this->sessionId,
        $this->languageId,
        $metadataJson
      ]);
      
      // ✅ ALWAYS LOG PERFORMANCE METRICS (Requirement 8.5)
      $logMessage = sprintf(
        '[RAG] Performance: interaction_id=%d, time=%dms, agent=%s, type=%s, confidence=%.2f, quality=%.2f, cache=%s',
        $this->interactionId,
        $this->metrics['response_time_ms'] ?? 0,
        $this->metrics['agent_type'] ?? 'unknown',
        $this->metrics['classification_type'] ?? 'unknown',
        $this->metrics['confidence_score'] ?? 0,
        $this->metrics['response_quality'] ?? 0,
        $this->metrics['cache_hit'] ? 'HIT' : 'MISS'
      );
      
      // Add cache metrics to log if available
      if ($this->cacheMetrics['cache_type'] !== null) {
        $logMessage .= sprintf(
          ', cache_type=%s, time_saved=%dms, cost_saved=$%.4f',
          $this->cacheMetrics['cache_type'],
          $this->cacheMetrics['time_saved_ms'] ?? 0,
          $this->cacheMetrics['api_cost_saved_usd'] ?? 0
        );
      }
      
      error_log($logMessage);
      
      return true;
      
    } catch (\Exception $e) {
      error_log("StatisticsTracker: Error saving statistics: " . $e->getMessage());
      return false;
    }
  }
  
  /**
   * Met à jour les colonnes de performance dans rag_chat_interactions
   * 
   * @return bool True si succès, false sinon
   */
  public function updateInteractionMetrics(): bool
  {
    if ($this->interactionId === null) {
      return false;
    }
    
    try {
      // 🔧 MIGRATED TO DOCTRINEORM
      DoctrineOrm::execute("
        UPDATE {$this->prefix}rag_chat_interactions 
        SET response_time = ?,
            tokens_used = ?,
            api_cost = ?
        WHERE interaction_id = ?
      ", [
        $this->metrics['response_time_ms'],
        $this->metrics['tokens_total'],
        $this->metrics['api_cost_usd'],
        $this->interactionId
      ]);
      
      return true;
      
    } catch (\Exception $e) {
      error_log("StatisticsTracker: Error updating interaction metrics: " . $e->getMessage());
      return false;
    }
  }
  
  /**
   * Complete save (statistics + interaction update)
   * 
   * @return bool True if success, false otherwise
   */
  public function saveAll(): bool
  {
    $statsOk = $this->save();
    $interactionOk = $this->updateInteractionMetrics();
    
    return $statsOk && $interactionOk;
  }
  
  /**
   * Récupère les métriques actuelles
   * 
   * @return array Métriques collectées
   */
  public function getMetrics(): array
  {
    return $this->metrics;
  }
  
  /**
   * Récupère une métrique spécifique
   * 
   * @param string $key Nom de la métrique
   * @return mixed Valeur de la métrique ou null
   */
  public function getMetric(string $key)
  {
    return $this->metrics[$key] ?? null;
  }
  
  /**
   * Récupère toutes les métriques
   * 
   * @return array Toutes les métriques
   */
  public function getAllMetrics(): array
  {
    return $this->metrics;
  }
  
  /**
   * Gets cache metrics
   * 
   * @return array Cache metrics
   */
  public function getCacheMetrics(): array
  {
    return $this->cacheMetrics;
  }
  
  /**
   * Gets a specific cache metric
   * 
   * @param string $key Metric key
   * @return mixed Metric value or null
   */
  public function getCacheMetric(string $key)
  {
    return $this->cacheMetrics[$key] ?? null;
  }
}
