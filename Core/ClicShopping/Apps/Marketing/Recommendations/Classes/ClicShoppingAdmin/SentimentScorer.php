<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Recommendations\Classes\ClicShoppingAdmin;

/**
 * SentimentScorer — couche de normalisation mathématique centrale.
 *
 * Espace de score unique : [-1, 1]
 *   -1  = sentiment très négatif
 *    0  = neutre
 *   +1  = sentiment très positif
 *
 * Toutes les sources (votes, GPT, notes, ventes, externe) doivent
 * passer par cette classe avant agrégation dans RecommendationsAdmin.
 */
class SentimentScorer
{
  // ---------------------------------------------------------------
  // Constantes de domaine
  // ---------------------------------------------------------------

  /** Espace de sortie unique pour tout le système */
  public const SCORE_MIN = -1.0;
  public const SCORE_MAX =  1.0;

  /** Note maximale possible (étoiles) */
  public const RATING_MAX = 5.0;

  // ---------------------------------------------------------------
  // Paramètres de lissage bayésien
  // ---------------------------------------------------------------

  private float $k;           // facteur de lissage (prior strength)
  private float $globalMean;  // prior : 0.0 = neutre

  public function __construct(float $k = 5.0, float $globalMean = 0.0)
  {
    $this->k          = $k;
    $this->globalMean = $globalMean;
  }

  // ---------------------------------------------------------------
  // API publique — normalisations vers [-1, 1]
  // ---------------------------------------------------------------

  /**
   * Ramène un score brut de votes (somme signée) dans [-1, 1].
   * Formule non-linéaire : évite la saturation sur les produits très populaires.
   *
   *   normalized = s / (|s| + 1)
   *
   * @param float $rawScore  Somme brute des votes sentiment (ex. : +12, -3)
   */
  public static function normalizeVoteScore(float $rawScore): float
  {
    return $rawScore / (abs($rawScore) + 1);
  }

  /**
   * Ramène une note [0, RATING_MAX] dans [-1, 1].
   * Formule linéaire : (rating / max) * 2 - 1
   * Exemple : 5/5 → +1.0 | 2.5/5 → 0.0 | 1/5 → -0.6
   *
   * @param float $rating     Note brute (0–5)
   * @param float $maxRating  Maximum possible (défaut : 5)
   */
  public static function normalizeRating(float $rating, float $maxRating = self::RATING_MAX): float
  {
    if ($maxRating <= 0) {
      return 0.0;
    }

    $normalized = ($rating / $maxRating) * 2.0 - 1.0;
    return self::clamp($normalized);
  }

  /**
   * Ramène un score [0, 1] dans [-1, 1].
   * Utilisé pour les sources sales et external qui sont déjà en [0,1].
   *
   *   result = score * 2 - 1
   *
   * @param float $score  Score en [0, 1]
   */
  public static function normalize01To11(float $score): float
  {
    $normalized = ($score * 2.0) - 1.0;
    return self::clamp($normalized);
  }

  /**
   * Borne un score quelconque dans [-1, 1].
   *
   * @param float $score  Score à borner
   */
  public static function clamp(float $score): float
  {
    return max(self::SCORE_MIN, min(self::SCORE_MAX, $score));
  }

  // ---------------------------------------------------------------
  // Lissage bayésien
  // ---------------------------------------------------------------

  /**
   * Applique un lissage bayésien pour stabiliser les scores
   * sur les produits avec peu d'avis.
   *
   * Formule : (score * n + globalMean * k) / (n + k)
   *
   * Avec peu d'avis (n petit) → score tire vers globalMean (0 = neutre).
   * Avec beaucoup d'avis (n grand) → score s'approche de la valeur observée.
   *
   * @param float $normalizedScore  Score déjà dans [-1, 1]
   * @param int   $n                Nombre d'avis
   */
  public function smooth(float $normalizedScore, int $n): float
  {
    if ($n <= 0) {
      return $this->globalMean;
    }

    $smoothed = ($normalizedScore * $n + $this->globalMean * $this->k) / ($n + $this->k);
    return self::clamp($smoothed);
  }

  // ---------------------------------------------------------------
  // Score final votes (agrégé depuis DB)
  // ---------------------------------------------------------------

  /**
   * Calcule le score de sentiment final à partir des votes bruts.
   * Combine normalisation non-linéaire + lissage bayésien.
   *
   * @param float $rawScore    Somme brute des votes sentiment
   * @param int   $reviewCount Nombre d'avis
   */
  public function compute(float $rawScore, int $reviewCount): float
  {
    if ($reviewCount <= 0) {
      return 0.0;
    }

    $normalized = self::normalizeVoteScore($rawScore);
    return $this->smooth($normalized, $reviewCount);
  }

  // ---------------------------------------------------------------
  // Seuil de significativité
  // ---------------------------------------------------------------

  /**
   * Indique si le nombre d'avis est suffisant pour que le score
   * soit statistiquement significatif.
   *
   * En dessous du seuil, le score doit être pondéré à la baisse
   * ou ignoré dans le calcul de recommandation.
   *
   * @param int $reviewCount  Nombre d'avis
   * @param int $threshold    Seuil minimum (configurable depuis l'admin)
   */
  public function isSignificant(int $reviewCount, int $threshold = 3): bool
  {
    return $reviewCount >= $threshold;
  }

  /**
   * Retourne un poids réduit si le score n'est pas significatif.
   * Permet une dégradation progressive plutôt qu'un on/off brutal.
   *
   * Exemple : 0 avis → 0.0 | 1 avis → 0.33 | 3 avis → 1.0 (seuil)
   *
   * @param int $reviewCount  Nombre d'avis
   * @param int $threshold    Seuil de significativité
   */
  public function getSignificanceWeight(int $reviewCount, int $threshold = 3): float
  {
    if ($reviewCount <= 0) {
      return 0.0;
    }

    if ($reviewCount >= $threshold) {
      return 1.0;
    }

    // Dégradation linéaire entre 0 et le seuil
    return $reviewCount / $threshold;
  }
}
