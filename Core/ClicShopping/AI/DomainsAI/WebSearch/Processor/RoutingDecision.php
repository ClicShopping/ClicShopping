<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Processor;

/**
 * RoutingDecision - Value object representing intent routing data
 *
 * This immutable value object encapsulates the routing decision made by the IntentRouter,
 * including detected intent, selected modes, and location parameters.
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Processor
 */
class RoutingDecision
{
  /**
   * @var array Detected intent structure with fields:
   *            - product: Product name or category (string)
   *            - intent: Intent type (price_comparison|product_discovery|market_research)
   *            - location: Geographic location (string|null)
   *            - target_site: Specific site to search (string|null)
   *            - mode_hint: Explicit mode override (mode_a|mode_b|mode_c|hybrid|null)
   */
  private array $intent;

  /**
   * @var array Selected search modes (e.g., ['mode_b_google_shopping'] or ['mode_a_ai_overview', 'mode_c_rag_websearch'])
   */
  private array $selectedModes;

  /**
   * @var array Location parameters for API calls:
   *            - gl: Geolocation code (e.g., 'fr', 'us')
   *            - hl: Language code (e.g., 'fr', 'en')
   *            - currency: Currency code (e.g., 'EUR', 'USD')
   */
  private array $locationParams;

  /**
   * @var string Routing method used (llm|pattern|explicit)
   */
  private string $routingMethod;

  /**
   * @var bool Whether hybrid mode is active (multiple engines)
   */
  private bool $isHybridMode;

  /**
   * @var array Additional metadata for logging and debugging
   */
  private array $metadata;

  /**
   * Constructor
   *
   * @param array $intent Detected intent structure
   * @param array $selectedModes Array of selected mode identifiers
   * @param array $locationParams Location parameters for API calls
   * @param string $routingMethod Routing method used (llm|pattern|explicit)
   * @param array $metadata Additional metadata
   */
  public function __construct(
    array $intent,
    array $selectedModes,
    array $locationParams,
    string $routingMethod,
    array $metadata = []
  ) {
    $this->intent = $intent;
    $this->selectedModes = $selectedModes;
    $this->locationParams = $locationParams;
    $this->routingMethod = $routingMethod;
    $this->isHybridMode = count($selectedModes) > 1;
    $this->metadata = $metadata;
  }

  /**
   * Get detected intent
   *
   * @return array Intent structure
   */
  public function getIntent(): array
  {
    return $this->intent;
  }

  /**
   * Get selected search modes
   *
   * @return array Array of mode identifiers
   */
  public function getSelectedModes(): array
  {
    return $this->selectedModes;
  }

  /**
   * Get location parameters
   *
   * @return array Location parameters (gl, hl, currency)
   */
  public function getLocationParams(): array
  {
    return $this->locationParams;
  }

  /**
   * Get routing method
   *
   * @return string Routing method (llm|pattern|explicit)
   */
  public function getRoutingMethod(): string
  {
    return $this->routingMethod;
  }

  /**
   * Check if hybrid mode is active
   *
   * @return bool True if multiple engines selected
   */
  public function isHybridMode(): bool
  {
    return $this->isHybridMode;
  }

  /**
   * Get metadata
   *
   * @return array Additional metadata
   */
  public function getMetadata(): array
  {
    return $this->metadata;
  }

  /**
   * Get product name from intent
   *
   * @return string|null Product name or null
   */
  public function getProduct(): ?string
  {
    return $this->intent['product'] ?? null;
  }

  /**
   * Get intent type
   *
   * @return string|null Intent type (price_comparison|product_discovery|market_research) or null
   */
  public function getIntentType(): ?string
  {
    return $this->intent['intent'] ?? null;
  }

  /**
   * Get target site from intent
   *
   * @return string|null Target site domain or null
   */
  public function getTargetSite(): ?string
  {
    return $this->intent['target_site'] ?? null;
  }

  /**
   * Get location from intent
   *
   * @return string|null Location string or null
   */
  public function getLocation(): ?string
  {
    return $this->intent['location'] ?? null;
  }

  /**
   * Convert to array for logging and serialization
   *
   * @return array Complete routing decision as array
   */
  public function toArray(): array
  {
    return [
      'intent' => $this->intent,
      'selected_modes' => $this->selectedModes,
      'location_params' => $this->locationParams,
      'routing_method' => $this->routingMethod,
      'is_hybrid_mode' => $this->isHybridMode,
      'metadata' => $this->metadata
    ];
  }

  /**
   * Create RoutingDecision from array
   *
   * @param array $data Array representation of routing decision
   * @return self New RoutingDecision instance
   */
  public static function fromArray(array $data): self
  {
    return new self(
      $data['intent'] ?? [],
      $data['selected_modes'] ?? [],
      $data['location_params'] ?? [],
      $data['routing_method'] ?? 'unknown',
      $data['metadata'] ?? []
    );
  }
}
