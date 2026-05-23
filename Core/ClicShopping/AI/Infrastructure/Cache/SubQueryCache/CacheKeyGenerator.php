<?php
/**
 * Cache Key Generator
 * Génère des clés de cache uniques et sécurisées
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Cache\SubQueryCache;


use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;

/**
 * Génère des clés de cache MD5 uniques basées sur la requête et le contexte
 */

class CacheKeyGenerator
{
  /**
   * Génère une clé de cache unique
   * 
   * @param string $userQuery Question de l'utilisateur
   * @param array $context Contexte additionnel
   * @return string Clé MD5 unique avec préfixe
   */
  public static function generate(string $userQuery, array $context = []): string
  {
    // Normalize query to English for consistent cache keys
    try {
      $normalized = SemanticAgent::translateToEnglish($userQuery, 120);
    } catch (\Throwable $e) {
      $normalized = $userQuery;
    }

    $data = [
      'query' => trim(strtolower($normalized)),
      'context' => self::normalizeContext($context)
    ];

    $baseKey = md5(json_encode($data));

    // Optional namespace by mode to avoid collisions (semantic/analytics/web)
    $mode = isset($context['mode']) ? strtolower((string)$context['mode']) : 'generic';
    return 'query_' . $mode . '_' . $baseKey;
  }
  
  /**
   * Normalise le contexte pour éviter les variations inutiles
   * 
   * @param array $context Contexte brut
   * @return array Contexte normalisé
   */
  private static function normalizeContext(array $context): array
  {
    // Supprimer les clés non pertinentes pour le cache
    // entity_id et entity_type sont stockés dans la table mais ne doivent pas affecter la clé
    $irrelevantKeys = [
      'timestamp',
      'request_id',
      'session_id',
      'entity_id',
      'entity_type',
      'interpretation'
    ];
    
    foreach ($irrelevantKeys as $key) {
      unset($context[$key]);
    }
    
    // Trier les clés pour cohérence
    ksort($context);
    
    return $context;
  }
  
  /**
   * Valide une clé de cache
   * 
   * @param string $key Clé à valider
   * @return bool True si valide
   */
  public static function isValid(string $key): bool
  {
    return preg_match('/^query_[a-z]+_[a-f0-9]{32}$/', $key) === 1;
  }
}
