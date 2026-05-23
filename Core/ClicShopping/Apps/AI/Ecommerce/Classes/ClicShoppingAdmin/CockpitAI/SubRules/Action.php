<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubRules;

  /**
   * Action
   *
   * Immutable data structure representing a recommended action produced by the
   * CockpitAI rules engine (Requirements 13.1, 13.2, 13.5, 13.6).
   *
   * Fields:
   *  - code        : unique machine-readable identifier  (e.g. 'seo_optimization')
   *  - label       : human-readable short label          (e.g. 'Optimize SEO')
   *  - priority    : ActionPriority enum value           (Critical / High / Medium / Low)
   *  - description : detailed explanation for the UI / LLM prompt
   *  - exclusive   : when true, this action cancels all others if triggered
   *                  (e.g. 'consider_removal' for Q3 high-returns products)
   *  - metadata    : free-form key/value context (trigger details, thresholds, ...)
   *
   * Serialization: toArray() produces the canonical array used in Analysis_Report
   * JSON (§ action_plan.actions).
   */
  readonly class Action
  {
    public function __construct(
      public string         $code,
      public string         $label,
      public ActionPriority $priority,
      public string         $description,
      public bool           $exclusive = false,
      public array          $metadata  = [],
    ) {
    }

    /**
     * Factory: build an Action from an array (e.g. deserialized JSON metadata).
     *
     * @param array $data Keys: code, label, priority (string), description,
     *                    exclusive (bool, optional), metadata (array, optional)
     * @throws \ValueError If priority label is unknown
     * @throws \InvalidArgumentException If required keys are missing
     */
    public static function fromArray(array $data): self
    {
      $array = ['code', 'label', 'priority', 'description'];

      foreach ($array as $required) {
        if (!isset($data[$required])) {
          throw new \InvalidArgumentException("Action::fromArray() missing required key: '{$required}'");
        }
      }

      return new self(
        code:        (string) $data['code'],
        label:       (string) $data['label'],
        priority:    ActionPriority::fromLabel((string) $data['priority']),
        description: (string) $data['description'],
        exclusive:   (bool) ($data['exclusive'] ?? false),
        metadata:    (array) ($data['metadata']  ?? []),
      );
    }

    /**
     * Return a canonical array representation for JSON serialization.
     *
     * Structure matches Analysis_Report action_plan.actions[*]:
     * {
     *   "code":        "seo_optimization",
     *   "label":       "Optimize SEO",
     *   "priority":    "high",
     *   "description": "Current SEO score (44/100) is below threshold...",
     *   "exclusive":   false,
     *   "metadata":    {}
     * }
     */
    public function toArray(): array
    {
      return [
        'code'        => $this->code,
        'label'       => $this->label,
        'priority'    => $this->priority->label(),
        'description' => $this->description,
        'exclusive'   => $this->exclusive,
        'metadata'    => $this->metadata,
      ];
    }
  }
