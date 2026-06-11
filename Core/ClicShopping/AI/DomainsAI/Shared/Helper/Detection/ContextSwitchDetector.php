<?php
/**
 * ContextSwitchDetector Class
 * 
 * Détecte quand l'utilisateur change de sujet pour éviter que le contexte
 * conversationnel n'interfère avec le feedback learning
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Shared\Helper\Detection;

use AllowDynamicProperties;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\Shared\Patterns\Common\ContextResetPattern;
use ClicShopping\AI\DomainsAI\Semantic\Helper\SemanticDomainDetector;

#[AllowDynamicProperties]
class ContextSwitchDetector
{
  private SecurityLogger $securityLogger;
  private bool $debug;
  private SemanticDomainDetector $semanticDetector;

  /**
   * Constructeur
   *
   * @param bool $debug Active le mode debug
   */
  public function __construct(bool $debug = false)
  {
    $this->securityLogger = new SecurityLogger();
    $this->debug = $debug;
    $this->semanticDetector = new SemanticDomainDetector($debug);
  }

  /**
   * Détecte si la nouvelle question change de domaine par rapport au contexte
   *
   * @param string $newQuery Nouvelle question
   * @param array $conversationContext Contexte conversationnel actuel
   * @return array Résultat de la détection
   */
  public function detectContextSwitch(string $newQuery, array $conversationContext): array
  {
    // Extraire le domaine de la nouvelle question
    $newDomain = $this->extractDomain($newQuery);

    // Extraire le domaine du contexte récent
    $contextDomain = $this->extractContextDomain($conversationContext);

    // Détecter le changement
    $hasSwitch = $newDomain !== $contextDomain && $contextDomain !== 'unknown';

    if ($this->debug && $hasSwitch) {
      $this->securityLogger->logSecurityEvent(
        "Context switch detected: {$contextDomain} -> {$newDomain}",
        'info'
      );
    }

    return [
      'has_switch' => $hasSwitch,
      'previous_domain' => $contextDomain,
      'new_domain' => $newDomain,
      'confidence' => $this->calculateConfidence($newQuery, $newDomain),
      'should_clear_context' => $hasSwitch && $this->calculateConfidence($newQuery, $newDomain) > 0.7,
    ];
  }

  /**
   * Extrait le domaine principal d'une question
   * Utilise le SemanticDomainDetector pour une détection enrichie
   *
   * @param string $query Question à analyser
   * @return string Domaine détecté
   */
  private function extractDomain(string $query): string
  {
    // Utiliser le détecteur sémantique enrichi
    $detection = $this->semanticDetector->detectDomain($query);

    if ($this->debug && $detection['domain'] !== 'unknown') {
      $this->securityLogger->logSecurityEvent(
        "Semantic domain detected: {$detection['domain']} (score: {$detection['score']}, confidence: {$detection['confidence']})",
        'info'
      );
    }

    return $detection['domain'];
  }

  /**
   * Extrait le domaine du contexte conversationnel
   *
   * @param array $context Contexte conversationnel
   * @return string Domaine du contexte
   */
  private function extractContextDomain(array $context): string
  {
    if (empty($context['short_term_context'])) {
      return 'unknown';
    }

    // Analyser les derniers messages
    $recentMessages = array_slice($context['short_term_context'], -3);
    $combinedText = '';

    foreach ($recentMessages as $message) {
      $combinedText .= ' ' . ($message['content'] ?? '');
    }

    return $this->extractDomain($combinedText);
  }

  /**
   * Calcule la confiance de la détection
   * Utilise le score sémantique du SemanticDomainDetector
   *
   * @param string $query Question
   * @param string $domain Domaine détecté
   * @return float Confiance (0-1)
   */
  private function calculateConfidence(string $query, string $domain): float
  {
    if ($domain === 'unknown') {
      return 0.0;
    }

    // Utiliser le détecteur sémantique pour obtenir la confiance
    $detection = $this->semanticDetector->detectDomain($query);

    // Si le domaine correspond, retourner la confiance calculée
    if ($detection['domain'] === $domain) {
      return $detection['confidence'];
    }

    // Fallback sur l'ancienne méthode si nécessaire
    return 0.5;
  }

  /**
   * Détermine si le contexte doit être réinitialisé
   *
   * @param string $newQuery Nouvelle question
   * @param array $conversationContext Contexte actuel
   * @return bool True si le contexte doit être réinitialisé
   */
  public function shouldClearContext(string $newQuery, array $conversationContext): bool
  {
    $detection = $this->detectContextSwitch($newQuery, $conversationContext);
    return $detection['should_clear_context'];
  }

  /**
   * Détecte les marqueurs explicites de nouveau contexte
   *
   * @param string $query Question
   * @return bool True si des marqueurs sont détectés
   */
  public function hasExplicitContextReset(string $query): bool
  {
    return ContextResetPattern::hasResetMarker($query);
  }
}
