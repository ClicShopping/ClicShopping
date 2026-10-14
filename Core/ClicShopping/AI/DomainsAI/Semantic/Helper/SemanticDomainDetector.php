<?php
/**
 * SemanticDomainDetector Class
 * 
 * Enriched semantic domain detection with support for synonyms,
 * technical terms and related concepts
 */

namespace ClicShopping\AI\DomainsAI\Semantic\Helper;


use ClicShopping\AI\Security\SecurityLogger;


class SemanticDomainDetector
{
  private SecurityLogger $securityLogger;
  private bool $debug;
  private array $enrichedDomains;

  private array $termWeights = [
    'core' => 1.0,
    'technical' => 0.9,
    'synonyms' => 0.8,
    'concepts' => 0.7,
    'attributes' => 0.6,
    'documents' => 0.6,
    'status' => 0.5,
    'hierarchy' => 0.5,
    'analysis' => 0.8,
  ];

  public function __construct(bool $debug = false)
  {
    $this->securityLogger = new SecurityLogger();
    $this->debug = $debug;

    if ($debug) {
      error_log("SemanticDomainDetector::__construct() - Pure LLM mode, using empty domains");
    }
    $this->enrichedDomains = [];
  }

  /**
   * Detects the domain of a question with semantic scoring
   *
   * @param string $query Question to analyze
   * @return array Result with domain, score and details
   */
  public function detectDomain(string $query): array
  {
    $query = mb_strtolower($query);
    $scores = [];

    foreach ($this->enrichedDomains as $domain => $termGroups) {
      $domainScore = 0;
      $matchedTerms = [];

      foreach ($termGroups as $groupType => $terms) {
        $weight = $this->termWeights[$groupType] ?? 0.5;

        foreach ($terms as $term) {
          if (str_contains($query, $term)) {
            $domainScore += $weight;
            $matchedTerms[] = [
              'term' => $term,
              'type' => $groupType,
              'weight' => $weight,
            ];
          }
        }
      }

      if ($domainScore > 0) {
        $scores[$domain] = [
          'score' => $domainScore,
          'matched_terms' => $matchedTerms,
        ];
      }
    }

    $scores = $this->disambiguateSimilarDomains($query, $scores);

    uasort($scores, function($a, $b) {
      return $b['score'] <=> $a['score'];
    });

    if (empty($scores)) {
      return [
        'domain' => 'unknown',
        'score' => 0.0,
        'confidence' => 0.0,
        'matched_terms' => [],
      ];
    }

    $topDomain = array_key_first($scores);
    $topScore = $scores[$topDomain]['score'];

    $secondScore = 0;
    if (count($scores) > 1) {
      $domains = array_keys($scores);
      $secondScore = $scores[$domains[1]]['score'];
    }

    $scoreDiff = $topScore - $secondScore;
    $confidence = min(1.0, ($topScore / 2.0) + ($scoreDiff / 2.0));

    return [
      'domain' => $topDomain,
      'score' => $topScore,
      'confidence' => $confidence,
      'matched_terms' => $scores[$topDomain]['matched_terms'],
      'all_scores' => $scores,
    ];
  }

  /**
   * Gets all terms of a domain (for compatibility)
   *
   * @param string $domain Domain name
   * @return array List of all terms
   */
  public function getDomainTerms(string $domain): array
  {
    if (!isset($this->enrichedDomains[$domain])) {
      return [];
    }

    $allTerms = [];
    foreach ($this->enrichedDomains[$domain] as $termGroup) {
      $allTerms = array_merge($allTerms, $termGroup);
    }

    return array_unique($allTerms);
  }

  /**
   * Disambiguates between similar domains by analyzing context
   *
   * @param string $query Question to analyze
   * @param array $scores Scores of all domains
   * @return array Adjusted scores after disambiguation
   */
  private function disambiguateSimilarDomains(string $query, array $scores): array
  {
    if (isset($scores['suppliers']) && isset($scores['manufacturers'])) {
      $supplierScore = $scores['suppliers']['score'];
      $manufacturerScore = $scores['manufacturers']['score'];
      
      if (abs($supplierScore - $manufacturerScore) < 0.5) {
        $supplierIndicators = ['purchase', 'buy', 'order-from', 'vendor', 'supply'];
        $manufacturerIndicators = ['brand', 'made-by', 'produced-by', 'origin'];
        
        $supplierBonus = 0;
        $manufacturerBonus = 0;
        
        foreach ($supplierIndicators as $indicator) {
          if (str_contains($query, $indicator)) {
            $supplierBonus += 1.0;
          }
        }
        
        foreach ($manufacturerIndicators as $indicator) {
          if (str_contains($query, $indicator)) {
            $manufacturerBonus += 1.0;
          }
        }
        
        $scores['suppliers']['score'] += $supplierBonus;
        $scores['manufacturers']['score'] += $manufacturerBonus;
        
        if (isset($scores['products']) && ($supplierBonus > 0 || $manufacturerBonus > 0)) {
          $scores['products']['score'] *= 0.7;
        }
      }
    }

    if (isset($scores['reviews']) && isset($scores['reviews_sentiment'])) {
      $reviewScore = $scores['reviews']['score'];
      $sentimentScore = $scores['reviews_sentiment']['score'];
      
      if (abs($reviewScore - $sentimentScore) < 0.5) {
        $sentimentIndicators = ['sentiment', 'feeling', 'positive', 'negative', 'emotion', 'analysis'];
        
        $sentimentBonus = 0;
        foreach ($sentimentIndicators as $indicator) {
          if (str_contains($query, $indicator)) {
            $sentimentBonus += 0.5;
          }
        }
        
        if ($sentimentBonus > 0) {
          $scores['reviews_sentiment']['score'] += $sentimentBonus;
          $scores['reviews']['score'] *= 0.7;
        } else {
          $scores['reviews']['score'] += 0.3;
        }
      }
    }

    if (isset($scores['products'])) {
      $productScore = $scores['products']['score'];
      
      foreach ($scores as $domain => $data) {
        if ($domain !== 'products' && $data['score'] > 0.5) {
          $scores['products']['score'] *= 0.95;
          break;
        }
      }
    }

    return $scores;
  }

  //********************
  //Not Used
  //********************
  /**
   * Adds custom terms to a domain
   *
   * @param string $domain Domain name
   * @param string $groupType Group type (core, technical, etc.)
   * @param array $terms Terms to add
   * @return void
   */
  public function addCustomTerms(string $domain, string $groupType, array $terms): void
  {
    if (!isset($this->enrichedDomains[$domain])) {
      $this->enrichedDomains[$domain] = [];
    }

    if (!isset($this->enrichedDomains[$domain][$groupType])) {
      $this->enrichedDomains[$domain][$groupType] = [];
    }

    $this->enrichedDomains[$domain][$groupType] = array_merge(
      $this->enrichedDomains[$domain][$groupType],
      $terms
    );

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "Added custom terms to domain '{$domain}' group '{$groupType}': " . implode(', ', $terms),
        'info'
      );
    }
  }

  /**
   * Gets the complete enriched domains
   *
   * @return array All domains with their terms
   */
  public function getEnrichedDomains(): array
  {
    return $this->enrichedDomains;
  }

  /**
   * Compares two domains to detect a significant change
   *
   * @param string $previousDomain Previous domain
   * @param string $newDomain New domain
   * @param float $newConfidence Confidence of the new domain
   * @return bool True if significant change
   */
  public function isSignificantChange(
    string $previousDomain,
    string $newDomain,
    float $newConfidence
  ): bool {
    if ($previousDomain === $newDomain) {
      return false;
    }

    if ($newConfidence >= 0.75) {
      return true;
    }

    if ($newConfidence >= 0.6) {
      return true;
    }

    return false;
  }
}
