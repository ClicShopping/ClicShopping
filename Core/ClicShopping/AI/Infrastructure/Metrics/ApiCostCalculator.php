<?php
/**
 * ApiCostCalculator
 * 
 * Calculates API costs based on tokens and models used
 * Maintains up-to-date pricing table for different providers
 */

namespace ClicShopping\AI\Infrastructure\Metrics;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

class ApiCostCalculator
{
  /**
   * Tarifs par modèle (prix par 1K tokens)
   * Format: [model => [input_cost, output_cost]]
   */
  private const PRICING = [
    // OpenAI
    'gpt-5' => [0.00125, 0.01],
    'gpt-5.2' => [0.00175, 0.00175],
    'gpt-5.2-pro' => [0.021, 0.021],
    'gpt-5-mini' => [0.00025, 0.00025],
    'gpt-5-nano' => [0.00005, 0.0004],
    'gpt-4.1' => [0.003, 0.003],
    'gpt-4.1-mini' => [0.0008, 0.0008],
    'gpt-4.1-nano' => [0.0002, 0.0002],
    'gpt-4' => [0.03, 0.06],
    'gpt-4-turbo' => [0.01, 0.03],
    'gpt-4-turbo-preview' => [0.01, 0.03],
    'gpt-3.5-turbo' => [0.0005, 0.0015],
    'gpt-3.5-turbo-16k' => [0.003, 0.004],
    
    // Anthropic Claude
    'claude-3-opus' => [0.015, 0.075],
    'claude-3-sonnet' => [0.003, 0.015],
    'claude-3-haiku' => [0.00025, 0.00125],
    'claude-2.1' => [0.008, 0.024],
    'claude-2' => [0.008, 0.024],
    
    // Mistral
    'mistral-large' => [0.008, 0.024],
    'mistral-medium' => [0.0027, 0.0081],
    'mistral-small' => [0.002, 0.006],
    
    // Local (gratuit)
    'llama2' => [0.0, 0.0],
    'llama-2-70b' => [0.0, 0.0],
    'mistral-7b' => [0.0, 0.0],
    'mixtral-8x7b' => [0.0, 0.0],
    'mistral' => [0.0, 0.0],
    'phi4' => [0.0, 0.0],
    'gemma' => [0.0, 0.0],
    'ollama' => [0.0, 0.0],
    'local' => [0.0, 0.0]
  ];

  /**
   * Mapping from configured model IDs to pricing keys.
   */
  private const MODEL_ALIASES = [
    'anth-sonnet' => 'claude-3-sonnet',
    'anth-opus' => 'claude-3-opus',
    'anth-haiku' => 'claude-3-haiku',
    'mistral-large-latest' => 'mistral-large'
  ];
  
  /**
   * Calculates total cost of an API call
   * 
   * @param string $model Model name
   * @param int $promptTokens Prompt tokens
   * @param int $completionTokens Completion tokens
   * @return float Total cost in USD
   */
  public static function calculateCost(string $model, int $promptTokens, int $completionTokens): float
  {
    // Normaliser le nom du modèle
    $model = self::normalizeModelName($model);
    
    // Chercher le modèle exact ou un match partiel
    $pricing = self::findModelPricing($model);
    
    if ($pricing === null) {
      $fallbackModel = self::getFallbackModel($model);
      if ($fallbackModel !== $model) {
        $pricing = self::findModelPricing($fallbackModel);
      }
    }

    if ($pricing === null) {
      // Modèle inconnu, utiliser un coût par défaut conservateur
      return self::calculateDefaultCost($promptTokens, $completionTokens);
    }
    
    [$inputCost, $outputCost] = $pricing;
    
    // Calculate cost
    $promptCost = ($promptTokens / 1000) * $inputCost;
    $completionCost = ($completionTokens / 1000) * $outputCost;
    
    return round($promptCost + $completionCost, 6);
  }
  
  /**
   * Trouve le pricing pour un modèle (avec match partiel)
   * 
   * @param string $model Nom du modèle
   * @return array|null [input_cost, output_cost] ou null
   */
  private static function findModelPricing(string $model): ?array
  {
    // Match exact
    if (isset(self::PRICING[$model])) {
      return self::PRICING[$model];
    }
    
    // Match partiel (ex: "gpt-4-0613" match "gpt-4")
    foreach (self::PRICING as $knownModel => $pricing) {
      if (str_contains($model, $knownModel)) {
        return $pricing;
      }
    }
    
    // Détection par provider
    if (str_contains($model, 'gpt')) {
      return self::PRICING['gpt-3.5-turbo']; // Défaut OpenAI
    }
    if (str_contains($model, 'claude')) {
      return self::PRICING['claude-3-haiku']; // Défaut Anthropic (le moins cher)
    }
    if (str_contains($model, 'mistral')) {
      return self::PRICING['mistral-small']; // Défaut Mistral
    }
    if (str_contains($model, 'llama') || str_contains($model, 'ollama')) {
      return [0.0, 0.0]; // Local, gratuit
    }
    
    return null;
  }

  /**
   * Normalise le nom du modèle et applique les alias connus.
   *
   * @param string $model Nom du modèle
   * @return string Nom normalisé
   */
  private static function normalizeModelName(string $model): string
  {
    $model = strtolower(trim($model));

    if (isset(self::MODEL_ALIASES[$model])) {
      return self::MODEL_ALIASES[$model];
    }

    $providerMap = self::getConfiguredModelProviderMap();
    if (isset($providerMap[$model]) && $providerMap[$model] === 'lmstudio') {
      return 'local';
    }

    return $model;
  }

  /**
   * Récupère le modèle de fallback depuis la configuration si disponible.
   *
   * @param string $model Nom du modèle
   * @return string Modèle de fallback normalisé
   */
  private static function getFallbackModel(string $model): string
  {
    if (class_exists(Gpt::class)) {
      $fallback = strtolower(Gpt::getTechnicalFallbackModel());
      if ($fallback !== '') {
        $fallback = self::normalizeModelName($fallback);
        if ($fallback !== $model) {
          return $fallback;
        }
      }
    }

    return $model;
  }

  /**
   * Retourne la map des providers des modèles configurés.
   *
   * @return array [model_id => provider]
   */
  private static function getConfiguredModelProviderMap(): array
  {
    static $cache = null;

    if ($cache !== null) {
      return $cache;
    }

    $cache = [];

    if (!class_exists(Gpt::class)) {
      return $cache;
    }

    foreach (Gpt::getGptModel() as $model) {
      if (!isset($model['id'])) {
        continue;
      }

      $id = strtolower($model['id']);
      $provider = strtolower($model['provider'] ?? 'openai');
      $cache[$id] = $provider;
    }

    return $cache;
  }
  
  /**
   * Calculates default cost for unknown models
   * 
   * @param int $promptTokens Prompt tokens
   * @param int $completionTokens Completion tokens
   * @return float Estimated cost in USD
   */
  private static function calculateDefaultCost(int $promptTokens, int $completionTokens): float
  {
    // Utiliser le coût de gpt-3.5-turbo comme référence
    $defaultInputCost = 0.0005;
    $defaultOutputCost = 0.0015;
    
    $promptCost = ($promptTokens / 1000) * $defaultInputCost;
    $completionCost = ($completionTokens / 1000) * $defaultOutputCost;
    
    return round($promptCost + $completionCost, 6);
  }
  
  /**
   * Estime le coût d'un prompt avant l'appel API
   * 
   * @param string $model Nom du modèle
   * @param int $estimatedPromptTokens Tokens estimés du prompt
   * @param int $estimatedCompletionTokens Tokens estimés de la complétion
   * @return float Coût estimé en USD
   */
  public static function estimateCost(string $model, int $estimatedPromptTokens, int $estimatedCompletionTokens): float
  {
    return self::calculateCost($model, $estimatedPromptTokens, $estimatedCompletionTokens);
  }
  
  /**
   * Récupère les informations de pricing pour un modèle
   * 
   * @param string $model Nom du modèle
   * @return array ['input_cost' => float, 'output_cost' => float, 'currency' => 'USD']
   */
  public static function getModelPricing(string $model): array
  {
    $model = self::normalizeModelName($model);
    $pricing = self::findModelPricing($model);
    
    if ($pricing === null) {
      return [
        'input_cost' => 0.0005,
        'output_cost' => 0.0015,
        'currency' => 'USD',
        'note' => 'Default pricing (model unknown)'
      ];
    }
    
    return [
      'input_cost' => $pricing[0],
      'output_cost' => $pricing[1],
      'currency' => 'USD',
      'per' => '1K tokens'
    ];
  }
  
  /**
   * Liste tous les modèles supportés avec leurs prix
   * 
   * @return array Liste des modèles et prix
   */
  public static function getAllPricing(): array
  {
    $result = [];
    
    foreach (self::PRICING as $model => $pricing) {
      $result[$model] = [
        'input_cost' => $pricing[0],
        'output_cost' => $pricing[1],
        'currency' => 'USD',
        'per' => '1K tokens'
      ];
    }
    
    return $result;
  }
  
  /**
   * Calculates total cost for a set of interactions
   * 
   * @param array $interactions List of interactions with tokens
   * @return array ['total_cost' => float, 'by_model' => array]
   */
  public static function calculateBatchCost(array $interactions): array
  {
    $totalCost = 0.0;
    $byModel = [];
    
    foreach ($interactions as $interaction) {
      $model = $interaction['model'] ?? 'unknown';
      $promptTokens = $interaction['prompt_tokens'] ?? 0;
      $completionTokens = $interaction['completion_tokens'] ?? 0;
      
      $cost = self::calculateCost($model, $promptTokens, $completionTokens);
      $totalCost += $cost;
      
      if (!isset($byModel[$model])) {
        $byModel[$model] = [
          'count' => 0,
          'total_cost' => 0.0,
          'total_tokens' => 0
        ];
      }
      
      $byModel[$model]['count']++;
      $byModel[$model]['total_cost'] += $cost;
      $byModel[$model]['total_tokens'] += $promptTokens + $completionTokens;
    }
    
    return [
      'total_cost' => round($totalCost, 6),
      'by_model' => $byModel,
      'currency' => 'USD'
    ];
  }
}
